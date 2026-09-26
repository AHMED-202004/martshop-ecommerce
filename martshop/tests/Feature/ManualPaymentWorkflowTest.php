<?php

namespace Tests\Feature;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\PaymentMethodService;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ManualPaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
        Storage::fake('local');
    }

    public function test_owner_submits_private_payment_proof_with_server_snapshots(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $admin = $this->admin();
        $order = $this->order($customer, 100);
        $method = $this->method();

        $this->actingAs($customer)
            ->post(route('payments.store', $order), $this->paymentPayload($method, 'REF-PRIVATE-1', 100))
            ->assertRedirect(route('payments.create', $order));

        $payment = Payment::query()->firstOrFail();
        $proof = $payment->proof()->firstOrFail();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(10000, $payment->amount);
        $this->assertSame('REF-PRIVATE-1', $payment->provider_ref);
        $this->assertSame('100.00', $payment->meta['expected_amount']);
        $this->assertSame($method->account_identifier, $payment->meta['payment_method']['account_identifier']);
        $this->assertSame(OrderPaymentStatus::PendingReview, $order->fresh()->payment_status);
        Storage::disk('local')->assertExists($proof->path);
        Storage::disk('public')->assertMissing($proof->path);
        $this->assertTrue(AuditLog::query()->where('action', 'payment.submitted')->exists());

        $this->actingAs($other)->get(route('payment-proofs.show', $proof))->assertForbidden();
        $this->actingAs($customer)->get(route('payment-proofs.show', $proof))->assertOk();
        $this->actingAs($admin)->get(route('payment-proofs.show', $proof))->assertOk();
    }

    public function test_payment_cannot_be_submitted_before_all_merchant_orders_are_confirmed(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 100, OrderStatus::PendingConfirmation);
        $method = $this->method();

        $this->actingAs($customer)
            ->from(route('orders.history'))
            ->post(route('payments.store', $order), $this->paymentPayload($method, 'REF-EARLY-1', 100))
            ->assertRedirect(route('orders.history'))
            ->assertSessionHasErrors('payment');

        $this->assertDatabaseCount('payments', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles('payment-proofs'));
    }

    public function test_submission_is_idempotent_and_transfer_reference_cannot_be_reused(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $order = $this->order($customer, 100);
        $otherOrder = $this->order($otherCustomer, 80);
        $method = $this->method();
        $key = (string) Str::uuid();
        $payload = $this->paymentPayload($method, 'shared-ref-22', 100, $key);

        $this->actingAs($customer)->post(route('payments.store', $order), $payload)->assertRedirect();
        $this->actingAs($customer)->post(route('payments.store', $order), $payload)->assertRedirect();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_proofs', 1);

        $this->actingAs($otherCustomer)
            ->from(route('payments.create', $otherOrder))
            ->post(route('payments.store', $otherOrder), $this->paymentPayload($method, 'SHARED-REF-22', 80))
            ->assertRedirect(route('payments.create', $otherOrder))
            ->assertSessionHasErrors('reference_number');
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_exact_payment_acceptance_is_transactional_and_idempotent(): void
    {
        $customer = User::factory()->create();
        $admin = $this->admin();
        $order = $this->order($customer, 150);
        $method = $this->method();
        $this->actingAs($customer)
            ->post(route('payments.store', $order), $this->paymentPayload($method, 'REF-ACCEPT-1', 150));
        $payment = Payment::query()->firstOrFail();

        $decision = ['decision' => 'accepted', 'lock_version' => $payment->lock_version, 'current_password' => 'password'];
        $this->actingAs($admin)
            ->patch(route('admin.payments.update', $payment), $decision)
            ->assertRedirect(route('admin.payments.show', $payment));

        $this->assertSame(PaymentStatus::Accepted, $payment->fresh()->status);
        $this->assertSame(OrderPaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertNotNull($payment->fresh()->open_key);
        $this->assertTrue(AuditLog::query()->where('action', 'payment.reviewed')->exists());

        // A repeated identical financial decision does not apply a second transition.
        $this->actingAs($admin)
            ->patch(route('admin.payments.update', $payment), $decision)
            ->assertRedirect();
        $this->assertSame(1, AuditLog::query()->where('action', 'payment.reviewed')->count());
        $this->assertSame(1, $payment->fresh()->lock_version);
    }

    public function test_mismatched_amount_cannot_be_accepted_and_requires_the_correct_decision(): void
    {
        $customer = User::factory()->create();
        $admin = $this->admin();
        $order = $this->order($customer, 100);
        $method = $this->method();
        $this->actingAs($customer)
            ->post(route('payments.store', $order), $this->paymentPayload($method, 'REF-SHORT-1', 90));
        $payment = Payment::query()->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.payments.show', $payment))
            ->patch(route('admin.payments.update', $payment), [
                'decision' => 'accepted', 'lock_version' => 0, 'current_password' => 'password',
            ])
            ->assertRedirect(route('admin.payments.show', $payment))
            ->assertSessionHasErrors('decision');
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);

        $this->actingAs($admin)
            ->patch(route('admin.payments.update', $payment), [
                'decision' => 'short_amount', 'reason' => 'المبلغ أقل من المطلوب', 'lock_version' => 0, 'current_password' => 'password',
            ])
            ->assertRedirect();
        $this->assertSame(PaymentStatus::ShortAmount, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->open_key);
        $this->assertSame(OrderPaymentStatus::ActionRequired, $order->fresh()->payment_status);
    }

    public function test_only_finance_permissions_can_manage_methods_and_review_payments(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 100);
        $method = $this->method();
        $this->actingAs($customer)
            ->post(route('payments.store', $order), $this->paymentPayload($method, 'REF-AUTH-1', 100));
        $payment = Payment::query()->firstOrFail();

        $this->actingAs($customer)->get(route('admin.payments.index'))->assertForbidden();
        $this->actingAs($customer)->patch(route('admin.payments.update', $payment), [
            'decision' => 'accepted', 'lock_version' => 0,
        ])->assertForbidden();
        $this->actingAs($customer)->get(route('admin.payment-methods.index'))->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.payment-methods.store'), [
            'name' => 'New Wallet',
            'slug' => 'new-wallet',
            'account_name' => 'Mart Platform',
            'account_identifier' => '0599000000',
            'instructions' => 'أرسل المبلغ ثم ارفع الإثبات.',
            'is_active' => 1,
            'current_password' => 'password',
        ])->assertRedirect();
        $this->assertDatabaseHas('payment_methods', ['slug' => 'new-wallet', 'is_active' => true]);
        $this->assertTrue(AuditLog::query()->where('action', 'payment_method.created')->exists());
    }

    public function test_payment_method_management_is_private_reauthenticated_and_never_audits_account_values(): void
    {
        $admin = $this->admin();
        $method = $this->method();
        $page = $this->actingAs($admin)->get(route('admin.payment-methods.index'))
            ->assertOk()->assertSee('WALLET-123')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
        $this->assertStringContainsString("default-src 'none'", $page->headers->get('Content-Security-Policy'));

        $failed = $this->actingAs($admin)
            ->from(route('admin.payment-methods.index'))
            ->post(route('admin.payment-methods.store'), [
                'name' => 'Rejected Wallet',
                'slug' => 'rejected-wallet',
                'account_name' => 'PRIVATE ACCOUNT NAME',
                'account_identifier' => 'PRIVATE ACCOUNT IDENTIFIER',
                'instructions' => 'PRIVATE TRANSFER INSTRUCTIONS',
                'is_active' => 1,
                'current_password' => 'wrong-password',
            ])->assertRedirect(route('admin.payment-methods.index'))
            ->assertSessionHasErrors('current_password');
        $oldInput = $failed->getSession()->getOldInput();
        foreach (['current_password', 'account_name', 'account_identifier', 'instructions'] as $sensitiveKey) {
            $this->assertArrayNotHasKey($sensitiveKey, $oldInput);
        }
        $this->assertDatabaseMissing('payment_methods', ['slug' => 'rejected-wallet']);

        $this->actingAs($admin)->put(route('admin.payment-methods.update', $method), [
            'name' => 'Updated Wallet',
            'slug' => 'updated-wallet',
            'account_name' => 'NEW PRIVATE NAME',
            'account_identifier' => 'NEW PRIVATE IDENTIFIER',
            'instructions' => 'NEW PRIVATE INSTRUCTIONS',
            'sort_order' => 3,
            'is_active' => 1,
            'current_password' => 'password',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('NEW PRIVATE IDENTIFIER', $method->fresh()->account_identifier);
        $audit = AuditLog::query()->where('action', 'payment_method.updated')->firstOrFail();
        $serializedAudit = json_encode($audit->only(['before', 'after', 'metadata']));
        foreach (['WALLET-123', 'NEW PRIVATE NAME', 'NEW PRIVATE IDENTIFIER', 'NEW PRIVATE INSTRUCTIONS'] as $secret) {
            $this->assertStringNotContainsString($secret, $serializedAudit);
        }
        $this->assertTrue($audit->metadata['account_details_changed']);

        $ordinary = User::factory()->create();
        $this->assertThrows(
            fn () => app(PaymentMethodService::class)->create([
                'name' => 'Service bypass',
                'slug' => 'service-bypass',
            ], $ordinary),
            HttpException::class,
        );
        $this->assertDatabaseMissing('payment_methods', ['slug' => 'service-bypass']);
    }

    private function order(
        User $customer,
        float $total,
        OrderStatus $status = OrderStatus::Confirmed,
    ): Order {
        return Order::query()->create([
            'user_id' => $customer->id,
            'subtotal' => $total,
            'shipping_fee' => 0,
            'total' => $total,
            'currency' => 'ILS',
            'status' => $status,
            'payment_method' => 'manual_transfer',
            'payment_status' => OrderPaymentStatus::Unpaid,
        ]);
    }

    private function method(): PaymentMethod
    {
        return PaymentMethod::query()->create([
            'name' => 'Test Wallet',
            'slug' => 'test-wallet',
            'type' => 'manual_transfer',
            'is_active' => true,
            'account_name' => 'Mart Platform',
            'account_identifier' => 'WALLET-123',
            'instructions' => 'Transfer the exact amount.',
        ]);
    }

    private function paymentPayload(
        PaymentMethod $method,
        string $reference,
        float $amount,
        ?string $idempotencyKey = null,
    ): array {
        return [
            'payment_method_id' => $method->id,
            'reference_number' => $reference,
            'amount' => $amount,
            'transferred_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'sender_name' => 'Test Sender',
            'sender_account' => '0599000000',
            'proof' => UploadedFile::fake()->createWithContent(
                'proof.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nAAAAABJRU5ErkJggg=='),
            ),
            'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
        ];
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }
}
