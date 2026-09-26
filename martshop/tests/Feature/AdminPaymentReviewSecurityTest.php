<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\{AuditLog, Order, Payment, User};
use App\Services\{MerchantLedgerService, PaymentReviewService};
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminPaymentReviewSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_review_pages_are_private_and_hide_payment_data_from_unauthorized_users(): void
    {
        $customer = User::factory()->create();
        $admin = $this->admin();
        $payment = $this->payment($customer, 'PRIVATE-REFERENCE');

        $response = $this->actingAs($admin)->get(route('admin.payments.show', $payment))->assertOk()
            ->assertSee('PRIVATE-REFERENCE')->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $shown = $response->viewData('payment');
        foreach (['open_key', 'idempotency_key', 'user_id', 'accepted_at', 'rejected_at'] as $column) {
            $this->assertArrayNotHasKey($column, $shown->getAttributes());
        }
        $this->assertEqualsCanonicalizing(['id', 'user_id', 'total'], array_keys($shown->order->getAttributes()));
        $this->assertEqualsCanonicalizing(['id', 'name', 'email'], array_keys($shown->order->user->getAttributes()));
        $this->assertTrue($shown->relationLoaded('method'));
        $this->assertTrue($shown->relationLoaded('proof'));
        $this->assertNull($shown->method);
        $this->assertNull($shown->proof);

        foreach (['open_key', 'idempotency_key', 'provider_ref', 'sender_name', 'sender_account', 'meta', 'review_notes'] as $key) {
            $this->assertArrayNotHasKey($key, $payment->toArray());
        }

        $this->actingAs(User::factory()->create())->get(route('admin.payments.show', $payment))
            ->assertForbidden()->assertDontSee('PRIVATE-REFERENCE')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_wrong_current_password_cannot_review_or_flash_the_password(): void
    {
        $customer = User::factory()->create();
        $admin = $this->admin();
        $payment = $this->payment($customer);

        $this->actingAs($admin)->from(route('admin.payments.show', $payment))
            ->patch(route('admin.payments.update', $payment), [
                'decision' => 'accepted', 'lock_version' => 0, 'current_password' => 'incorrect-secret',
            ])->assertRedirect(route('admin.payments.show', $payment))
            ->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.current_password');

        $this->assertSame('pending', $payment->fresh()->status->value);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
    }

    public function test_reviewer_cannot_decide_a_payment_owned_by_the_same_account(): void
    {
        $reviewer = $this->admin();
        $payment = $this->payment($reviewer);

        $this->actingAs($reviewer)->from(route('admin.payments.show', $payment))
            ->patch(route('admin.payments.update', $payment), [
                'decision' => 'accepted', 'lock_version' => 0, 'current_password' => 'password',
            ])->assertRedirect(route('admin.payments.show', $payment))->assertSessionHasErrors('payment');

        $this->assertSame('pending', $payment->fresh()->status->value);
        $this->assertSame('unpaid', $payment->order->fresh()->payment_status->value);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_index_is_permission_scoped_private_and_read_only(): void
    {
        $customer = User::factory()->create();
        $admin = $this->admin();
        $this->payment($customer, 'PENDING-REFERENCE');
        $before = Payment::query()->orderBy('id')->get()->toJson();

        $response = $this->actingAs($admin)->get(route('admin.payments.index'))->assertOk()
            ->assertSee('PENDING-REFERENCE')->assertHeader('Referrer-Policy', 'no-referrer');
        $listed = $response->viewData('pending')->getCollection()->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['id', 'order_id', 'payment_method_id', 'amount', 'currency', 'provider', 'provider_ref', 'status'],
            array_keys($listed->getAttributes()),
        );
        $this->assertFalse($listed->relationLoaded('proof'));
        $this->assertEqualsCanonicalizing(['id', 'user_id'], array_keys($listed->order->getAttributes()));
        $this->assertEqualsCanonicalizing(['id', 'name'], array_keys($listed->order->user->getAttributes()));
        $this->assertSame($before, Payment::query()->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_payment_review_and_sale_ledger_services_require_verification_permission(): void
    {
        $customer = User::factory()->create();
        $ordinary = User::factory()->create();
        $payment = $this->payment($customer);

        $this->assertThrows(
            fn () => app(PaymentReviewService::class)->review(
                $payment,
                PaymentStatus::Accepted,
                $ordinary,
                null,
                0,
            ),
            HttpException::class,
        );
        $this->assertThrows(
            fn () => app(MerchantLedgerService::class)->recordAcceptedPayment(
                $payment->order,
                $payment,
                $ordinary,
            ),
            HttpException::class,
        );
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function payment(User $customer, string $reference = 'PAYMENT-REFERENCE'): Payment
    {
        $order = Order::create([
            'user_id' => $customer->id, 'subtotal' => 100, 'shipping_fee' => 0, 'total' => 100,
            'currency' => 'ILS', 'status' => 'confirmed', 'payment_method' => 'manual_transfer', 'payment_status' => 'unpaid',
        ]);

        return Payment::create([
            'order_id' => $order->id, 'user_id' => $customer->id, 'order_no' => 'ORDER-'.$order->id,
            'amount' => 10000, 'currency' => 'ILS', 'provider' => 'manual', 'provider_ref' => $reference,
            'sender_name' => 'Private Sender', 'sender_account' => 'PRIVATE-ACCOUNT', 'status' => 'pending',
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }
}
