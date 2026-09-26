<?php

namespace Tests\Feature;

use App\Enums\{RefundDestinationStatus, RefundStatus};
use App\Models\{AuditLog, Delivery, Merchant, MerchantOrder, Order, Payment, Permission, RefundDestination, RefundRequest, Role, User};
use App\Services\{AuditLogger, DeliveryDisputeService, MerchantLedgerService, RefundDestinationService, RefundReviewService};
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RefundDestinationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_owner_submits_encrypted_immutable_snapshot_idempotently_without_moving_money(): void
    {
        [$refund, $customer] = $this->scenario();
        $this->actingAs($customer)->get(route('refund-destinations.index', $refund))->assertOk();
        foreach ([1, 2] as $retry) {
            $this->post(route('refund-destinations.store', $refund), $this->data())->assertSessionHasNoErrors();
        }
        $destination = RefundDestination::query()->sole();
        $this->assertSame(RefundDestinationStatus::Pending, $destination->status);
        $this->assertSame('PS123456789101234', $destination->recipient_snapshot['account_identifier']);
        $this->assertSame('1234', $destination->last_four);
        $this->assertSame(1, $refund->fresh()->lock_version);
        $this->assertSame(1, AuditLog::where('action', 'refund_destination.submitted')->count());
        $stored = DB::table('refund_destinations')->first()->recipient_snapshot;
        foreach (['PS123456789101234', 'Private Recipient', 'Private Provider'] as $private) {
            $this->assertStringNotContainsString($private, $stored);
            $this->assertStringNotContainsString($private, $destination->toJson());
            $this->assertStringNotContainsString($private, AuditLog::all()->toJson());
        }
        $this->assertDatabaseCount('ledger_entries', 3);
        $this->assertSame(RefundStatus::Requested, $refund->fresh()->status);
    }

    public function test_reauthentication_validation_and_ownership_prevent_untrusted_updates(): void
    {
        [$refund, $customer] = $this->scenario();
        $this->actingAs(User::factory()->create())->post(route('refund-destinations.store', $refund), $this->data())->assertForbidden();
        $this->get(route('refund-destinations.index', $refund))->assertForbidden();
        $this->actingAs($customer);
        $bad = $this->data();
        $bad['current_password'] = 'wrong';
        $this->post(route('refund-destinations.store', $refund), $bad)->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.account_identifier')->assertSessionMissing('_old_input.account_name')
            ->assertSessionMissing('_old_input.provider_name')->assertSessionMissing('_old_input.current_password');
        $bad = $this->data();
        $bad['account_identifier'] = '-----';
        $this->post(route('refund-destinations.store', $refund), $bad)->assertSessionHasErrors('destination');
        $bad['account_identifier'] = '<script>bad()</script>';
        $this->post(route('refund-destinations.store', $refund), $bad)->assertSessionHasErrors('account_identifier');
        $this->assertDatabaseCount('refund_destinations', 0);
    }

    public function test_full_details_require_owner_or_separate_permission_and_read_access_is_audited(): void
    {
        [$refund, $customer] = $this->scenario();
        $destination = app(RefundDestinationService::class)->submit($refund, $customer, $this->data(), 0);
        $reviewer = $this->staff('refunds.review');
        $this->actingAs($reviewer)->get(route('refund-destinations.show', $destination))->assertForbidden();
        $this->get(route('admin.refund-destinations.index'))->assertForbidden();
        $this->post(route('admin.refund-destinations.review', $destination), $this->reviewData())->assertForbidden();
        $staff = $this->staff('refund-destinations.review');
        $this->actingAs($staff)->get(route('admin.refund-destinations.index'))->assertOk()
            ->assertDontSee('Private Recipient')->assertDontSee('PS123456789101234');
        foreach ([$customer, $staff] as $viewer) {
            $this->actingAs($viewer)->get(route('refund-destinations.show', $destination))->assertOk()
                ->assertSee('PS123456789101234')->assertHeader('Referrer-Policy', 'no-referrer')
                ->assertHeader('X-Frame-Options', 'DENY')->assertDontSee('<script', false);
        }
        $this->assertSame(2, AuditLog::where('action', 'refund_destination.viewed')->count());
        $this->actingAs($staff)->get(route('admin.refunds.index'))->assertForbidden();
    }

    public function test_admin_queue_excludes_reviewers_own_destination_and_models_hide_private_fields(): void
    {
        [$refund, $customer] = $this->scenario();
        $destination = app(RefundDestinationService::class)->submit($refund, $customer, $this->data(), 0);
        $customer->assignRole('admin');

        $this->actingAs($customer)->get(route('admin.refund-destinations.index'))
            ->assertOk()->assertDontSee('وسيلة #'.$destination->id);

        $serializedRefund = $refund->fresh()->toJson();
        $this->assertStringNotContainsString('Refund for damaged item', $serializedRefund);
        $this->assertStringNotContainsString('product_minor', $serializedRefund);
        $this->assertStringNotContainsString('review_reason', $serializedRefund);
    }

    public function test_verification_is_separate_from_amount_approval_requires_attestation_and_is_idempotent(): void
    {
        [$refund, $customer, $admin] = $this->scenario();
        $destination = app(RefundDestinationService::class)->submit($refund, $customer, $this->data(), 0);
        $this->actingAs($admin);
        $bad = $this->reviewData();
        unset($bad['ownership_confirmed']);
        $this->post(route('admin.refund-destinations.review', $destination), $bad)->assertSessionHasErrors('ownership_confirmed');
        $bad = $this->reviewData();
        $bad['current_password'] = 'incorrect';
        $this->post(route('admin.refund-destinations.review', $destination), $bad)->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.review_notes');
        foreach ([1, 2] as $retry) {
            $this->post(route('admin.refund-destinations.review', $destination), $this->reviewData())->assertSessionHasNoErrors();
        }
        $this->assertSame(RefundDestinationStatus::Verified, $destination->fresh()->status);
        $this->assertSame(RefundStatus::Requested, $refund->fresh()->status);
        $this->assertSame(2, $refund->fresh()->lock_version);
        $this->assertSame(1, AuditLog::where('action', 'refund_destination.reviewed')->count());
        $this->assertStringNotContainsString('Protected verification notes', DB::table('refund_destinations')->first()->review_notes);
        $this->assertStringNotContainsString('Protected verification notes', AuditLog::all()->toJson());
        $this->assertDatabaseCount('ledger_entries', 3);
        $this->assertSame('open', $refund->dispute->status);
        $this->post(route('admin.refunds.review', $refund), [
            'decision' => 'approved', 'reason' => 'Old page before destination changed', 'lock_version' => 0, 'current_password' => 'password',
        ])->assertSessionHasErrors('refund');
    }

    public function test_verified_destination_cannot_be_overwritten_and_replacement_needs_fresh_verification(): void
    {
        [$refund, $customer, $admin] = $this->scenario();
        $service = app(RefundDestinationService::class);
        $first = $service->submit($refund, $customer, $this->data(), 0);
        $service->review($first, $admin, RefundDestinationStatus::Verified, 'Checked ownership independently', 0, true);
        $changed = $this->data();
        $changed['account_identifier'] = 'PS999999999999999';
        $changed['lock_version'] = 2;
        $this->actingAs($customer)->post(route('refund-destinations.store', $refund), $changed)->assertSessionHasErrors('destination');
        $this->post(route('refund-destinations.revoke', $first), ['current_password' => 'password', 'lock_version' => 0])->assertSessionHasErrors('destination');
        $this->post(route('refund-destinations.revoke', $first), ['current_password' => 'password', 'lock_version' => 1])->assertSessionHasNoErrors();
        $this->assertSame(RefundDestinationStatus::Revoked, $first->fresh()->status);
        $this->assertNull($first->fresh()->active_key);
        $this->assertSame('PS123456789101234', $first->fresh()->recipient_snapshot['account_identifier']);
        $changed['lock_version'] = $refund->fresh()->lock_version;
        $this->post(route('refund-destinations.store', $refund), $changed)->assertSessionHasNoErrors();
        $active = $refund->fresh()->activeDestination;
        $this->assertSame(RefundDestinationStatus::Pending, $active->status);
        $this->assertNotSame($first->id, $active->id);
        $this->assertDatabaseCount('refund_destinations', 2);
        $this->actingAs($admin)->post(route('admin.refund-destinations.review', $first), $this->reviewData())->assertSessionHasErrors('destination');
        $this->assertSame(RefundDestinationStatus::Pending, $active->fresh()->status);
        $this->assertDatabaseCount('ledger_entries', 3);
    }

    public function test_rejected_destination_retains_history_and_can_be_replaced_on_an_approved_refund(): void
    {
        [$refund, $customer, $admin] = $this->scenario();
        app(RefundReviewService::class)->review($refund, $admin, RefundStatus::Approved, 'Approved full refund', 0);
        $service = app(RefundDestinationService::class);
        $first = $service->submit($refund, $customer, $this->data(), 1);
        $service->review($first, $admin, RefundDestinationStatus::Rejected, 'Ownership could not be verified', 0, false);
        $second = $service->submit($refund, $customer, $this->data(), $refund->fresh()->lock_version);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(RefundDestinationStatus::Rejected, $first->fresh()->status);
        $this->assertSame(RefundDestinationStatus::Pending, $second->status);
        $this->assertSame(RefundStatus::Approved, $refund->fresh()->status);
        $this->assertDatabaseCount('ledger_entries', 3);
    }

    public function test_self_review_and_closed_refund_mutations_are_blocked(): void
    {
        [$refund, $customer, $admin] = $this->scenario();
        $destination = app(RefundDestinationService::class)->submit($refund, $customer, $this->data(), 0);
        $customer->assignRole('admin');
        $this->actingAs($customer)->post(route('admin.refund-destinations.review', $destination), $this->reviewData())->assertForbidden();
        app(RefundReviewService::class)->review($refund, $admin, RefundStatus::Rejected, 'Refund not justified', 1);
        $this->actingAs($admin)->post(route('admin.refund-destinations.review', $destination), $this->reviewData())->assertSessionHasErrors('destination');
        $this->actingAs($customer)->post(route('refund-destinations.revoke', $destination), ['current_password' => 'password', 'lock_version' => 0])->assertSessionHasErrors('destination');
        $this->assertSame(RefundDestinationStatus::Pending, $destination->fresh()->status);
    }

    public function test_audit_failure_rolls_back_destination_and_refund_version(): void
    {
        [$refund, $customer, $admin] = $this->scenario();
        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new \RuntimeException('Audit unavailable'));
        try {
            app(RefundDestinationService::class)->submit($refund, $customer, $this->data(), 0);
            $this->fail('Should fail');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertDatabaseCount('refund_destinations', 0);
        $this->assertSame(0, $refund->fresh()->lock_version);
    }

    public function test_snapshots_cannot_be_modified_deleted_or_dropped_by_rollback(): void
    {
        [$refund, $customer] = $this->scenario();
        $destination = app(RefundDestinationService::class)->submit($refund, $customer, $this->data(), 0);
        foreach (['update', 'delete'] as $operation) {
            try {
                $operation === 'update' ? $destination->update(['recipient_snapshot' => ['account_identifier' => 'altered']]) : $destination->delete();
                $this->fail('Should fail');
            } catch (\LogicException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
            $destination->refresh();
        }
        $migration = require database_path('migrations/2026_09_02_000018_create_refund_destinations.php');
        try {
            $migration->down();
            $this->fail('Should not drop non-empty history');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('non-empty', $exception->getMessage());
        }
        $this->assertDatabaseCount('refund_destinations', 1);
    }

    private function data(): array
    {
        return ['type' => 'bank_account', 'provider_name' => 'Private Provider', 'account_name' => 'Private Recipient',
            'account_identifier' => 'ps12 3456-789101234', 'current_password' => 'password', 'lock_version' => 0];
    }

    private function reviewData(): array
    {
        return ['decision' => 'verified', 'ownership_confirmed' => 1, 'review_notes' => 'Protected verification notes',
            'current_password' => 'password', 'lock_version' => 0];
    }

    private function staff(string $permission): User
    {
        $role = Role::query()->create(['name' => $permission, 'slug' => $permission, 'is_system' => false]);
        $role->permissions()->attach(Permission::where('slug', $permission)->sole());
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function scenario(): array
    {
        $customer = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $merchant = Merchant::query()->create([
            'user_id' => User::factory()->create()->id, 'legal_name' => 'Refund merchant',
            'identity_number' => 'RFD-'.$customer->id, 'phone' => '0599000000', 'date_of_birth' => '1990-01-01',
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
        $refund = app(RefundReviewService::class)->request($delivery, $customer, 'Refund for damaged item');

        return [$refund, $customer, $admin];
    }
}
