<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Order, Payment, PaymentMethod, PaymentProof, User};
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class PaymentProofSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
        Storage::fake('local');
    }

    public function test_payment_form_is_private_and_denies_another_customer(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = $this->order($owner);
        PaymentMethod::create([
            'name' => 'Wallet', 'slug' => 'wallet', 'type' => 'manual_transfer', 'is_active' => true,
            'account_name' => 'Mart', 'account_identifier' => 'WALLET-PRIVATE', 'instructions' => 'Transfer exactly.',
        ]);

        $response = $this->actingAs($owner)->get(route('payments.create', $order))->assertOk()
            ->assertSee('WALLET-PRIVATE')->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->actingAs($other)->get(route('payments.create', $order))->assertForbidden()
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertDontSee('WALLET-PRIVATE');
    }

    public function test_invalid_submission_does_not_flash_private_transfer_fields(): void
    {
        $owner = User::factory()->create();
        $order = $this->order($owner);
        $this->actingAs($owner)->from(route('payments.create', $order))
            ->post(route('payments.store', $order), [
                'reference_number' => 'PRIVATE-REFERENCE',
                'sender_name' => 'Private Sender',
                'sender_account' => 'PRIVATE-ACCOUNT',
                'amount' => '123.45',
                'transferred_at' => now()->format('Y-m-d H:i:s'),
                'idempotency_key' => 'not-a-uuid',
            ])->assertRedirect(route('payments.create', $order))->assertSessionHasErrors()
            ->assertSessionMissing('_old_input.reference_number')
            ->assertSessionMissing('_old_input.sender_name')
            ->assertSessionMissing('_old_input.sender_account')
            ->assertSessionMissing('_old_input.amount')
            ->assertSessionMissing('_old_input.transferred_at')
            ->assertSessionMissing('_old_input.idempotency_key');
    }

    public function test_valid_proof_is_checksum_verified_downloaded_and_audited_for_owner(): void
    {
        $owner = User::factory()->create();
        [$payment, $proof] = $this->proof($owner, '%PDF-1.4 valid payment proof');

        $response = $this->actingAs($owner)->get(route('payment-proofs.show', $proof))->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('payment-proof-'.$proof->id.'.pdf', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($owner->id, AuditLog::where('action', 'payment.proof_viewed')->sole()->actor_id);
        $this->assertSame($payment->id, $proof->payment_id);
    }

    public function test_tampered_checksum_size_or_path_is_rejected_without_audit(): void
    {
        $owner = User::factory()->create();
        foreach (['hash', 'size', 'path', 'disk'] as $problem) {
            [, $proof] = $this->proof($owner, '%PDF-1.4 proof '.$problem);
            if ($problem === 'hash') {
                DB::table('payment_proofs')->where('id', $proof->id)->update(['sha256' => str_repeat('0', 64)]);
            } elseif ($problem === 'size') {
                DB::table('payment_proofs')->where('id', $proof->id)->update(['size' => $proof->size + 1]);
            } elseif ($problem === 'path') {
                Storage::disk('local')->put('outside.pdf', 'outside secret');
                DB::table('payment_proofs')->where('id', $proof->id)->update(['path' => '../outside.pdf']);
            } else {
                DB::table('payment_proofs')->where('id', $proof->id)->update(['disk' => 'unconfigured']);
            }
            $this->actingAs($owner)->get(route('payment-proofs.show', $proof))->assertNotFound();
        }
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_payment_proof_metadata_is_hidden_and_immutable(): void
    {
        $owner = User::factory()->create();
        [, $proof] = $this->proof($owner, '%PDF-1.4 immutable payment proof');

        foreach (['disk', 'path', 'original_name', 'mime_type', 'size', 'sha256'] as $key) {
            $this->assertArrayNotHasKey($key, $proof->toArray());
        }
        $this->assertThrows(
            fn () => $proof->update(['size' => 1]),
            \LogicException::class,
        );
        $this->assertThrows(
            fn () => $proof->delete(),
            \LogicException::class,
        );
    }

    public function test_unauthorized_customer_is_denied_before_proof_contents_are_served(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [, $proof] = $this->proof($owner, '%PDF-1.4 owner only');
        $this->actingAs($other)->get(route('payment-proofs.show', $proof))->assertForbidden()
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function proof(User $owner, string $contents): array
    {
        $order = $this->order($owner);
        $payment = Payment::create([
            'order_id' => $order->id, 'user_id' => $owner->id, 'order_no' => 'ORDER-'.$order->id,
            'amount' => 10000, 'currency' => 'ILS', 'provider' => 'manual', 'status' => 'pending',
        ]);
        $file = UploadedFile::fake()->createWithContent('proof.pdf', $contents);
        $path = 'payment-proofs/'.$payment->id.'/00000000-0000-4000-8000-'.str_pad((string) $payment->id, 12, '0', STR_PAD_LEFT).'.pdf';
        Storage::disk('local')->put($path, $contents);
        $proof = PaymentProof::create([
            'payment_id' => $payment->id, 'disk' => 'local', 'path' => $path,
            'original_name' => $file->getClientOriginalName(), 'mime_type' => 'application/pdf',
            'size' => strlen($contents), 'sha256' => hash('sha256', $contents), 'created_at' => now(),
        ]);

        return [$payment, $proof];
    }

    private function order(User $owner): Order
    {
        return Order::create([
            'user_id' => $owner->id, 'subtotal' => 100, 'shipping_fee' => 0, 'total' => 100,
            'currency' => 'ILS', 'status' => 'confirmed', 'payment_method' => 'manual_transfer', 'payment_status' => 'unpaid',
        ]);
    }
}
