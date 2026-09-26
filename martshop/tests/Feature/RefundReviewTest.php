<?php

namespace Tests\Feature;

use App\Enums\RefundStatus;
use App\Models\{AuditLog, Delivery, LedgerEntry, Merchant, MerchantOrder, Order, Payment, RefundRequest, User};
use App\Services\{AuditLogger, DeliveryDisputeService, MerchantLedgerService, RefundReviewService};
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RefundReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_customer_requests_once_using_snapshot_prices_not_browser_amount(): void
    {
        [$delivery, $customer] = $this->disputedDelivery();
        $this->actingAs($customer)->post(route('refund-requests.store', $delivery), [
            'reason' => 'The item is damaged', 'amount' => 999999, 'status' => 'approved',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('refund-requests.store', $delivery), ['reason' => 'Retry request'])->assertSessionHasNoErrors();
        $refund = RefundRequest::query()->sole();
        $this->assertSame(12000, $refund->amount);
        $this->assertSame(9500, $refund->amount_snapshot['merchant_net_minor']);
        $this->assertSame(2000, $refund->amount_snapshot['delivery_minor']);
        $this->assertSame(RefundStatus::Requested, $refund->status);
        $this->assertDatabaseCount('ledger_entries', 3);
        $this->assertSame(1, AuditLog::where('action', 'refund.requested')->count());
        $this->get(route('orders.history'))->assertOk()->assertSee($refund->reference);
    }

    public function test_approval_is_audited_idempotent_and_cannot_release_or_pay_funds(): void
    {
        [$delivery, $customer, $admin] = $this->disputedDelivery();
        $refund = app(RefundReviewService::class)->request($delivery, $customer, 'Damaged item');
        $this->actingAs($admin)->get(route('admin.refunds.index'))->assertOk()->assertSee($refund->reference);
        foreach ([1, 2] as $retry) {
            $this->post(route('admin.refunds.review', $refund), $this->reviewData())->assertSessionHasNoErrors();
        }
        $this->assertSame(RefundStatus::Approved, $refund->refresh()->status);
        $this->assertSame($admin->id, $refund->reviewed_by);
        $this->assertSame(1, $refund->lock_version);
        $this->assertSame(1, AuditLog::where('action', 'refund.reviewed')->count());
        $this->assertDatabaseCount('ledger_entries', 3);
        $this->assertSame('open', $delivery->dispute->fresh()->status);
        $this->assertNull($delivery->fresh()->settled_at);
        $this->post(route('admin.settlements.close-dispute', $delivery), [
            'reason' => 'Try to close dispute', 'current_password' => 'password',
        ])
            ->assertSessionHasErrors('dispute');
        $this->assertDatabaseCount('ledger_entries', 3);
        $this->actingAs($customer)->get(route('orders.history'))->assertOk()->assertSee('لم يُحوّل المبلغ');
    }

    public function test_pending_blocks_close_and_rejection_does_not_automatically_lift_hold(): void
    {
        [$delivery, $customer, $admin] = $this->disputedDelivery();
        $refund = app(RefundReviewService::class)->request($delivery, $customer, 'Damaged item');
        $this->actingAs($admin)->post(route('admin.settlements.close-dispute', $delivery), [
            'reason' => 'Try to close dispute', 'current_password' => 'password',
        ])
            ->assertSessionHasErrors('dispute');
        $this->post(route('admin.refunds.review', $refund), $this->reviewData('rejected'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('ledger_entries', 3);
        $this->assertSame('open', $delivery->dispute->fresh()->status);
        $this->post(route('admin.settlements.close-dispute', $delivery), [
            'reason' => 'Reviewed and resolved dispute', 'current_password' => 'password',
        ])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('ledger_entries', 5);
        $this->assertSame('closed', $delivery->dispute->fresh()->status);
        $this->assertSame(RefundStatus::Rejected, $refund->fresh()->status);
    }

    public function test_access_is_limited_to_owner_and_refund_permission_and_disallows_self_review(): void
    {
        [$delivery, $customer, $admin] = $this->disputedDelivery();
        $this->get(route('admin.refunds.index'))->assertRedirect(route('login'));
        $other = User::factory()->create();
        $this->actingAs($other)->post(route('refund-requests.store', $delivery), ['reason' => 'Not my order'])->assertForbidden();
        $refund = app(RefundReviewService::class)->request($delivery, $customer, 'Damaged item');
        foreach ([$customer, $other] as $user) {
            $this->actingAs($user)->get(route('admin.refunds.index'))->assertForbidden();
            $this->post(route('admin.refunds.review', $refund), $this->reviewData())->assertForbidden();
            $response = $this->get(route('orders.history'))->assertOk();
            if ($user === $other) {
                $response->assertDontSee($refund->reference);
            }
        }
        $customer->assignRole('admin');
        $this->actingAs($customer)->post(route('admin.refunds.review', $refund), $this->reviewData())->assertForbidden();
        $this->assertSame(RefundStatus::Requested, $refund->fresh()->status);
    }

    public function test_refund_and_settlement_pages_are_private_and_exclude_the_reviewers_own_claims(): void
    {
        [$delivery, $customer, $admin] = $this->disputedDelivery();
        $refund = app(RefundReviewService::class)->request($delivery, $customer, 'PRIVATE REFUND CLAIM');

        $refundResponse = $this->actingAs($admin)->get(route('admin.refunds.index'))
            ->assertOk()->assertSee($refund->reference)->assertSee('PRIVATE REFUND CLAIM')
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $refundResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString("default-src 'none'", $refundResponse->headers->get('Content-Security-Policy'));

        $settlementResponse = $this->get(route('admin.settlements.index'))
            ->assertOk()->assertSee($delivery->reference)->assertSee('Received damaged item')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $settlementResponse->headers->get('Cache-Control'));

        $other = User::factory()->create();
        $this->actingAs($other)->get(route('admin.refunds.index'))->assertForbidden()
            ->assertDontSee('PRIVATE REFUND CLAIM')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get(route('admin.settlements.index'))->assertForbidden()
            ->assertDontSee('Received damaged item')->assertHeader('Referrer-Policy', 'no-referrer');

        $customer->assignRole('admin');
        $this->actingAs($customer)->get(route('admin.refunds.index'))->assertOk()
            ->assertDontSee($refund->reference)->assertDontSee('PRIVATE REFUND CLAIM');
        $this->get(route('admin.settlements.index'))->assertOk()
            ->assertDontSee($delivery->reference)->assertDontSee('Received damaged item');
    }

    public function test_closing_a_dispute_requires_password_and_cannot_be_done_by_its_customer_reviewer(): void
    {
        [$delivery, , $admin] = $this->disputedDelivery();
        $ledgerBefore = LedgerEntry::query()->orderBy('id')->get()->toJson();
        $auditBefore = AuditLog::query()->count();

        $this->actingAs($admin)->from(route('admin.settlements.index'))
            ->post(route('admin.settlements.close-dispute', $delivery), [
                'reason' => 'PRIVATE CLOSURE REASON',
                'current_password' => 'wrong-password',
            ])->assertRedirect(route('admin.settlements.index'))
            ->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.reason');
        $this->assertSame('open', $delivery->dispute->fresh()->status);
        $this->assertSame($ledgerBefore, LedgerEntry::query()->orderBy('id')->get()->toJson());
        $this->assertSame($auditBefore, AuditLog::query()->count());

        $this->post(route('admin.settlements.close-dispute', $delivery), [
            'reason' => 'Reviewed and safely resolved',
            'current_password' => 'password',
        ])->assertSessionHasNoErrors();
        $this->assertSame('closed', $delivery->dispute->fresh()->status);

        [$ownDelivery, $customer] = $this->disputedDelivery();
        $customer->assignRole('admin');
        $ownLedgerBefore = LedgerEntry::query()->orderBy('id')->get()->toJson();
        $ownAuditBefore = AuditLog::query()->count();
        $this->actingAs($customer)->post(route('admin.settlements.close-dispute', $ownDelivery), [
            'reason' => 'Cannot close my own customer claim',
            'current_password' => 'password',
        ])->assertForbidden();
        $this->assertSame('open', $ownDelivery->dispute->fresh()->status);
        $this->assertSame($ownLedgerBefore, LedgerEntry::query()->orderBy('id')->get()->toJson());
        $this->assertSame($ownAuditBefore, AuditLog::query()->count());
    }

    public function test_review_requires_password_reason_valid_transition_and_fresh_version(): void
    {
        [$delivery, $customer, $admin] = $this->disputedDelivery();
        $refund = app(RefundReviewService::class)->request($delivery, $customer, 'Damaged item');
        $this->actingAs($admin);
        foreach ([['current_password', 'wrong'], ['reason', ''], ['decision', 'paid'], ['lock_version', 5]] as [$key, $value]) {
            $data = $this->reviewData();
            $data[$key] = $value;
            $this->post(route('admin.refunds.review', $refund), $data)->assertSessionHasErrors()
                ->assertSessionMissing('_old_input.current_password');
            $this->assertSame(RefundStatus::Requested, $refund->fresh()->status);
        }
        $this->post(route('admin.refunds.review', $refund), $this->reviewData('rejected'))->assertSessionHasNoErrors();
        $this->post(route('admin.refunds.review', $refund), $this->reviewData())->assertSessionHasErrors('refund');
        $this->assertSame(RefundStatus::Rejected, $refund->fresh()->status);
    }

    public function test_missing_payment_and_changed_payment_or_snapshot_fail_closed(): void
    {
        foreach (['missing_payment', 'payment_amount', 'payment_status', 'currency', 'suborder_total', 'held_balance', 'settled', 'closed'] as $case) {
            [$delivery, $customer] = $this->disputedDelivery();
            $payment = $delivery->order->payments()->sole();
            match ($case) {
                'missing_payment' => DB::table('ledger_entries')->where('merchant_order_id', $delivery->merchant_order_id)->update(['payment_id' => null]),
                'payment_amount' => $payment->update(['amount' => 1]),
                'payment_status' => $payment->update(['status' => 'rejected']),
                'currency' => $payment->update(['currency' => 'USD']),
                'suborder_total' => $delivery->merchantOrder->update(['total' => 121]),
                'held_balance' => DB::table('ledger_entries')->where('merchant_order_id', $delivery->merchant_order_id)->where('status', 'held')->update(['net_amount' => 9400]),
                'settled' => $delivery->update(['settled_at' => now()]),
                'closed' => $delivery->dispute->update(['status' => 'closed']),
            };
            $this->actingAs($customer)->post(route('refund-requests.store', $delivery), ['reason' => 'Damaged item'])
                ->assertSessionHasErrors('refund');
        }
        $this->assertDatabaseCount('refund_requests', 0);
    }

    public function test_approval_rechecks_frozen_snapshot_and_held_balance(): void
    {
        [$delivery, $customer, $admin] = $this->disputedDelivery();
        $refund = app(RefundReviewService::class)->request($delivery, $customer, 'Damaged item');
        $delivery->merchantOrder->update(['delivery_fee' => 10, 'service_fee' => 10]);
        $this->actingAs($admin)->post(route('admin.refunds.review', $refund), $this->reviewData())->assertSessionHasErrors('refund');
        $this->assertSame(RefundStatus::Requested, $refund->fresh()->status);
        $this->assertSame(2000, $refund->fresh()->amount_snapshot['delivery_minor']);
        $this->assertDatabaseCount('ledger_entries', 3);
    }

    public function test_failed_audit_rolls_back_review_and_request(): void
    {
        [$delivery, $customer, $admin] = $this->disputedDelivery();
        $refund = app(RefundReviewService::class)->request($delivery, $customer, 'Damaged item');
        [$secondDelivery, $secondCustomer] = $this->disputedDelivery();
        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new \RuntimeException('Audit unavailable'));
        try {
            app(RefundReviewService::class)->review($refund, $admin, RefundStatus::Approved, 'Approved after review', 0);
            $this->fail('Review should fail');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertSame(RefundStatus::Requested, $refund->fresh()->status);
        $this->assertNull($refund->fresh()->reviewed_by);
        try {
            app(RefundReviewService::class)->request($secondDelivery, $secondCustomer, 'Damaged item');
            $this->fail('Request should fail');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertDatabaseCount('refund_requests', 1);
        $this->assertDatabaseCount('ledger_entries', 6);
    }

    public function test_request_snapshots_and_records_cannot_be_edited_or_deleted(): void
    {
        [$delivery, $customer] = $this->disputedDelivery();
        $refund = app(RefundReviewService::class)->request($delivery, $customer, '<script>bad()</script> damaged');
        foreach (['update', 'delete'] as $operation) {
            try {
                $operation === 'update' ? $refund->update(['amount' => 1]) : $refund->delete();
                $this->fail('Mutation should fail');
            } catch (\LogicException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
            $refund->refresh();
        }
        $this->assertSame(12000, $refund->amount);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->get(route('admin.refunds.index'))->assertOk()->assertDontSee('<script>bad()</script>', false)
            ->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false);
    }

    private function reviewData(string $decision = 'approved'): array
    {
        return ['decision' => $decision, 'reason' => 'Reviewed evidence and full amount',
            'lock_version' => 0, 'current_password' => 'password'];
    }

    private function disputedDelivery(): array
    {
        $customer = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $merchant = Merchant::query()->create([
            'user_id' => User::factory()->create()->id, 'legal_name' => 'Refund merchant',
            'identity_number' => 'RF-'.$customer->id, 'phone' => '0599000000', 'date_of_birth' => '1990-01-01',
            'address' => 'Private merchant address', 'business_type' => 'Retail', 'verification_status' => 'verified',
        ]);
        $order = Order::query()->create([
            'user_id' => $customer->id, 'subtotal' => 100, 'shipping_fee' => 20, 'total' => 120,
            'currency' => 'ILS', 'status' => 'confirmed', 'payment_method' => 'manual_transfer', 'payment_status' => 'paid',
        ]);
        $suborder = MerchantOrder::query()->create([
            'order_id' => $order->id, 'merchant_id' => $merchant->id, 'group_key' => 'merchant:'.$merchant->id,
            'status' => 'confirmed', 'product_subtotal' => 100, 'delivery_fee' => 20, 'service_fee' => 0,
            'commission_amount' => 5, 'total' => 120, 'currency' => 'ILS',
        ]);
        $payment = Payment::query()->create([
            'order_id' => $order->id, 'user_id' => $customer->id, 'order_no' => (string) $order->id,
            'amount' => 12000, 'currency' => 'ILS', 'status' => 'accepted', 'provider' => 'manual',
        ]);
        app(MerchantLedgerService::class)->recordAcceptedPayment($order, $payment, $admin);
        $delivery = Delivery::query()->create([
            'order_id' => $order->id, 'merchant_order_id' => $suborder->id, 'delivery_worker_id' => $admin->id,
            'status' => 'delivered', 'reference' => 'DEL-'.$order->id, 'assignment_key' => 'delivery:'.$order->id,
            'origin_snapshot' => [], 'destination_snapshot' => [], 'assigned_at' => now(), 'delivered_at' => now(),
            'settlement_hold_days' => 7, 'settlement_due_at' => now()->addDays(7),
        ]);
        app(DeliveryDisputeService::class)->open($delivery, $customer, 'Received damaged item');

        return [$delivery->fresh(), $customer, $admin];
    }
}
