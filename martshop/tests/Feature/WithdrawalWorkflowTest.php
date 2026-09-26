<?php

namespace Tests\Feature;

use App\Enums\LedgerEntryType;
use App\Enums\LedgerStatus;
use App\Enums\MerchantPayoutMethodStatus;
use App\Enums\MerchantVerificationStatus;
use App\Enums\WithdrawalStatus;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\Location;
use App\Models\MarketplaceSetting;
use App\Models\Merchant;
use App\Models\MerchantPayoutMethod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WithdrawalProof;
use App\Models\WithdrawalRequest;
use App\Services\MerchantLedgerService;
use App\Services\WithdrawalService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WithdrawalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, LocationSeeder::class]);
        Storage::fake('local');
    }

    public function test_withdrawal_pages_are_private_and_unauthorized_users_cannot_see_destinations(): void
    {
        $merchant = $this->merchant();
        $method = $this->payoutMethod($merchant);
        $method->update(['status' => MerchantPayoutMethodStatus::Pending, 'reviewed_at' => null]);

        $merchantResponse = $this->actingAs($merchant->user)
            ->get(route('merchant.withdrawals.index'))
            ->assertOk()
            ->assertSee('•••• 3456')
            ->assertDontSee('0599123456')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $merchantResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString("default-src 'none'", $merchantResponse->headers->get('Content-Security-Policy'));
        $this->assertEqualsCanonicalizing(
            ['id', 'user_id', 'verification_status'],
            array_keys($merchantResponse->viewData('merchant')->getAttributes()),
        );
        $merchantMethod = $merchantResponse->viewData('methods')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['id', 'merchant_id', 'provider_name', 'last_four', 'status'],
            array_keys($merchantMethod->getAttributes()),
        );

        $admin = $this->admin();
        $adminResponse = $this->actingAs($admin)
            ->get(route('admin.withdrawals.index'))
            ->assertOk()
            ->assertSee('0599123456')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $adminResponse->headers->get('Cache-Control'));
        $adminMethod = $adminResponse->viewData('payoutMethods')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['id', 'merchant_id', 'type', 'provider_name', 'account_name', 'account_identifier', 'status', 'lock_version'],
            array_keys($adminMethod->getAttributes()),
        );
        $this->assertEqualsCanonicalizing(
            ['id', 'user_id', 'legal_name'],
            array_keys($adminMethod->merchant->getAttributes()),
        );
        $this->assertFalse($adminMethod->merchant->relationLoaded('user'));
        foreach (['account_name', 'account_identifier', 'account_identifier_hash', 'review_notes'] as $key) {
            $this->assertArrayNotHasKey($key, $method->toArray());
        }

        $this->actingAs(User::factory()->create())
            ->get(route('admin.withdrawals.index'))
            ->assertForbidden()
            ->assertDontSee('0599123456')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_admin_withdrawal_mutations_require_current_password_without_flashing_financial_input(): void
    {
        $merchant = $this->merchant();
        $method = $this->payoutMethod($merchant);
        $method->update(['status' => MerchantPayoutMethodStatus::Pending, 'reviewed_at' => null]);
        $admin = $this->admin();

        $this->actingAs($admin)->from(route('admin.withdrawals.index'))
            ->patch(route('admin.withdrawals.payout-methods.update', $method), [
                'decision' => MerchantPayoutMethodStatus::Verified->value,
                'notes' => 'ملاحظة مالية خاصة',
                'lock_version' => 0,
                'current_password' => 'wrong-password',
            ])->assertRedirect(route('admin.withdrawals.index'))
            ->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.notes');
        $this->assertSame(MerchantPayoutMethodStatus::Pending, $method->fresh()->status);

        $withdrawal = $this->withdrawal($merchant, $method, WithdrawalStatus::Requested, 0, 'WDR-REAUTH-REVIEW');
        $this->actingAs($admin)->from(route('admin.withdrawals.index'))
            ->patch(route('admin.withdrawals.review', $withdrawal), [
                'decision' => WithdrawalStatus::Approved->value,
                'lock_version' => 0,
                'current_password' => 'wrong-password',
            ])->assertRedirect(route('admin.withdrawals.index'))
            ->assertSessionHasErrors('current_password');
        $this->assertSame(WithdrawalStatus::Requested, $withdrawal->fresh()->status);

        $paidCandidate = $this->withdrawal($merchant, $method, WithdrawalStatus::Approved, 1, 'WDR-REAUTH-PAY');
        $this->actingAs($admin)->from(route('admin.withdrawals.index'))
            ->post(route('admin.withdrawals.pay', $paidCandidate), [
                'transaction_reference' => 'PRIVATE-TRANSFER-REFERENCE',
                'transferred_at' => now()->subMinute()->format('Y-m-d H:i:s'),
                'proof' => $this->proof(),
                'lock_version' => 1,
                'current_password' => 'wrong-password',
            ])->assertRedirect(route('admin.withdrawals.index'))
            ->assertSessionHasErrors('current_password')
            ->assertSessionMissing('_old_input.transaction_reference')
            ->assertSessionMissing('_old_input.transferred_at');
        $this->assertSame(WithdrawalStatus::Approved, $paidCandidate->fresh()->status);
        $this->assertDatabaseCount('withdrawal_proofs', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('withdrawal-proofs'));

        $beforePolicy = MarketplaceSetting::query()->where('key', 'withdrawals.enabled')->value('value');
        $this->actingAs($admin)->from(route('admin.withdrawals.index'))
            ->put(route('admin.withdrawals.settings.update'), [
                'enabled' => 1,
                'minimum_amount' => '50.00',
                'maximum_amount' => '100.00',
                'daily_limit' => '120.00',
                'weekly_limit' => '200.00',
                'current_password' => 'wrong-password',
            ])->assertRedirect(route('admin.withdrawals.index'))
            ->assertSessionHasErrors('current_password');
        $this->assertSame($beforePolicy, MarketplaceSetting::query()->where('key', 'withdrawals.enabled')->value('value'));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_reviewer_cannot_review_or_pay_own_merchant_withdrawals(): void
    {
        $admin = $this->admin();
        $merchant = $this->merchant($admin);
        $method = $this->payoutMethod($merchant);
        $method->update(['status' => MerchantPayoutMethodStatus::Pending, 'reviewed_at' => null]);

        $this->actingAs($admin)->get(route('admin.withdrawals.index'))
            ->assertOk()->assertDontSee('0599123456');
        $this->actingAs($admin)->from(route('admin.withdrawals.index'))
            ->patch(route('admin.withdrawals.payout-methods.update', $method), [
                'decision' => MerchantPayoutMethodStatus::Verified->value,
                'lock_version' => 0,
                'current_password' => 'password',
            ])->assertRedirect(route('admin.withdrawals.index'))
            ->assertSessionHasErrors('payout_method');
        $this->assertSame(MerchantPayoutMethodStatus::Pending, $method->fresh()->status);

        $method->update(['status' => MerchantPayoutMethodStatus::Verified, 'reviewed_at' => now()]);
        $requested = $this->withdrawal($merchant, $method, WithdrawalStatus::Requested, 0, 'WDR-SELF-REVIEW');
        $this->actingAs($admin)->from(route('admin.withdrawals.index'))
            ->patch(route('admin.withdrawals.review', $requested), [
                'decision' => WithdrawalStatus::Approved->value,
                'lock_version' => 0,
                'current_password' => 'password',
            ])->assertRedirect(route('admin.withdrawals.index'))
            ->assertSessionHasErrors('withdrawal');
        $this->assertSame(WithdrawalStatus::Requested, $requested->fresh()->status);

        $approved = $this->withdrawal($merchant, $method, WithdrawalStatus::Approved, 1, 'WDR-SELF-PAY');
        $this->actingAs($admin)->from(route('admin.withdrawals.index'))
            ->post(route('admin.withdrawals.pay', $approved), [
                'transaction_reference' => 'SELF-PAY-BLOCKED',
                'transferred_at' => now()->subMinute()->format('Y-m-d H:i:s'),
                'proof' => $this->proof(),
                'lock_version' => 1,
                'current_password' => 'password',
            ])->assertRedirect(route('admin.withdrawals.index'))
            ->assertSessionHasErrors('withdrawal');
        $this->assertSame(WithdrawalStatus::Approved, $approved->fresh()->status);
        $this->assertDatabaseCount('withdrawal_proofs', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('withdrawal-proofs'));
    }

    public function test_withdrawal_proof_download_verifies_path_size_and_hash_before_audit(): void
    {
        $merchant = $this->merchant();
        $method = $this->payoutMethod($merchant);
        $withdrawal = $this->withdrawal($merchant, $method, WithdrawalStatus::Paid, 2, 'WDR-PROOF-INTEGRITY');
        $contents = '%PDF-1.4 private withdrawal proof';
        $path = 'withdrawal-proofs/'.$withdrawal->id.'/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, $contents);
        $proof = WithdrawalProof::query()->create([
            'withdrawal_request_id' => $withdrawal->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => "private\r\nproof.pdf",
            'mime_type' => 'application/pdf',
            'size' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'uploaded_by' => $merchant->user_id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($merchant->user)
            ->get(route('withdrawal-proofs.show', $proof))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('withdrawal-proof-'.$proof->id.'.pdf', $response->headers->get('Content-Disposition'));
        $this->assertSame(1, AuditLog::query()->where('action', 'withdrawal.proof_viewed')->count());

        DB::table('withdrawal_proofs')->where('id', $proof->id)->update(['size' => strlen($contents) + 1]);
        $this->actingAs($merchant->user)->get(route('withdrawal-proofs.show', $proof))->assertNotFound();
        DB::table('withdrawal_proofs')->where('id', $proof->id)->update(['size' => strlen($contents), 'sha256' => str_repeat('0', 64)]);
        $this->actingAs($merchant->user)->get(route('withdrawal-proofs.show', $proof))->assertNotFound();
        DB::table('withdrawal_proofs')->where('id', $proof->id)->update(['sha256' => hash('sha256', $contents), 'path' => 'withdrawal-proofs/'.$withdrawal->id.'/../outside.pdf']);
        $this->actingAs($merchant->user)->get(route('withdrawal-proofs.show', $proof))->assertNotFound();
        DB::table('withdrawal_proofs')->where('id', $proof->id)->update(['path' => $path, 'disk' => 'public']);
        $this->actingAs($merchant->user)->get(route('withdrawal-proofs.show', $proof))->assertNotFound();
        $this->assertSame(1, AuditLog::query()->where('action', 'withdrawal.proof_viewed')->count());

        foreach (['disk', 'path', 'original_name', 'mime_type', 'size', 'sha256'] as $key) {
            $this->assertArrayNotHasKey($key, $proof->toArray());
        }
        $this->assertThrows(fn () => $proof->update(['size' => 1]), \LogicException::class);
        $this->assertThrows(fn () => $proof->delete(), \LogicException::class);
    }

    public function test_withdrawal_review_and_payment_services_require_approval_permission(): void
    {
        $merchant = $this->merchant();
        $method = $this->payoutMethod($merchant);
        $ordinary = User::factory()->create();
        $requested = $this->withdrawal($merchant, $method, WithdrawalStatus::Requested, 0, 'WDR-DIRECT-REVIEW');
        $approved = $this->withdrawal($merchant, $method, WithdrawalStatus::Approved, 1, 'WDR-DIRECT-PAY');
        $service = app(WithdrawalService::class);
        $ledger = app(MerchantLedgerService::class);

        $this->assertThrows(
            fn () => $service->review($requested, WithdrawalStatus::Approved, $ordinary, null, 0),
            HttpException::class,
        );
        $this->assertThrows(
            fn () => $service->markPaid($approved, [
                'transaction_reference' => 'DIRECT-BYPASS',
                'transferred_at' => now()->toDateTimeString(),
                'lock_version' => 1,
            ], $this->proof(), $ordinary),
            HttpException::class,
        );
        $this->assertThrows(
            fn () => $ledger->holdWithdrawal($requested, $ordinary),
            HttpException::class,
        );
        $this->assertThrows(
            fn () => $ledger->releaseWithdrawal($requested, $ordinary),
            HttpException::class,
        );
        $this->assertThrows(
            fn () => $ledger->payWithdrawal($approved, $ordinary),
            HttpException::class,
        );
        $this->assertSame(WithdrawalStatus::Requested, $requested->fresh()->status);
        $this->assertSame(WithdrawalStatus::Approved, $approved->fresh()->status);
        $this->assertDatabaseCount('withdrawal_proofs', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('withdrawal-proofs'));
    }

    public function test_payout_method_requires_reauthentication_is_encrypted_and_needs_admin_verification(): void
    {
        $merchant = $this->merchant();
        $payload = [
            'type' => 'mobile_wallet',
            'provider_name' => 'Jawwal Pay',
            'account_name' => 'Verified Merchant',
            'account_identifier' => '0599 123 456',
            'current_password' => 'wrong-password',
        ];

        $this->actingAs($merchant->user)
            ->from(route('merchant.withdrawals.index'))
            ->post(route('merchant.payout-methods.store'), $payload)
            ->assertRedirect(route('merchant.withdrawals.index'))
            ->assertSessionHasErrors('current_password');
        $this->assertDatabaseCount('merchant_payout_methods', 0);

        $payload['current_password'] = 'password';
        $this->actingAs($merchant->user)
            ->post(route('merchant.payout-methods.store'), $payload)
            ->assertRedirect();

        $method = MerchantPayoutMethod::query()->firstOrFail();
        $rawIdentifier = DB::table('merchant_payout_methods')->where('id', $method->id)->value('account_identifier');
        $this->assertSame(MerchantPayoutMethodStatus::Pending, $method->status);
        $this->assertSame('0599 123 456', $method->account_identifier);
        $this->assertNotSame('0599 123 456', $rawIdentifier);
        $this->assertSame('3456', $method->last_four);
        $this->assertStringNotContainsString('0599 123 456', json_encode(
            AuditLog::query()->where('action', 'payout_method.registered')->firstOrFail()->after
        ));
        $this->actingAs($merchant->user)
            ->get(route('merchant.withdrawals.index'))
            ->assertOk()
            ->assertSee('•••• 3456')
            ->assertDontSee('0599 123 456');

        $admin = $this->admin();
        $this->actingAs($admin)
            ->get(route('admin.withdrawals.index'))
            ->assertOk()
            ->assertSee('0599 123 456');
        $decision = ['decision' => 'verified', 'lock_version' => 0, 'current_password' => 'password'];
        $this->actingAs($admin)
            ->patch(route('admin.withdrawals.payout-methods.update', $method), $decision)
            ->assertRedirect();
        $this->assertSame(MerchantPayoutMethodStatus::Verified, $method->fresh()->status);
        $this->assertSame(1, $method->fresh()->lock_version);

        // Repeating the same decision is safe and does not create another audit transition.
        $this->actingAs($admin)
            ->patch(route('admin.withdrawals.payout-methods.update', $method), $decision)
            ->assertRedirect();
        $this->assertSame(1, AuditLog::query()->where('action', 'payout_method.reviewed')->count());
    }

    public function test_only_available_balance_can_be_requested_and_the_hold_is_idempotent(): void
    {
        $this->enableWithdrawals();
        $merchant = $this->merchant();
        $method = $this->payoutMethod($merchant);
        $this->credit($merchant, LedgerStatus::Available, 10000);
        $this->credit($merchant, LedgerStatus::Pending, 50000);
        $this->credit($merchant, LedgerStatus::Held, 10000);

        $tooLarge = $this->withdrawalPayload($method, '120.00');
        $this->actingAs($merchant->user)
            ->from(route('merchant.withdrawals.index'))
            ->post(route('merchant.withdrawals.store'), $tooLarge)
            ->assertRedirect(route('merchant.withdrawals.index'))
            ->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('withdrawal_requests', 0);

        $payload = $this->withdrawalPayload($method, '80.00');
        $this->actingAs($merchant->user)
            ->post(route('merchant.withdrawals.store'), $payload)
            ->assertRedirect();
        $this->actingAs($merchant->user)
            ->post(route('merchant.withdrawals.store'), $payload)
            ->assertRedirect();

        $withdrawal = WithdrawalRequest::query()->firstOrFail();
        $balances = app(MerchantLedgerService::class)->balancesForMerchant($merchant->id);
        $this->assertSame(8000, $withdrawal->amount);
        $this->assertSame(2000, $balances['available']['ILS']);
        $this->assertSame(18000, $balances['held']['ILS']);
        $this->assertSame(50000, $balances['pending']['ILS']);
        $this->assertDatabaseCount('withdrawal_requests', 1);
        $this->assertSame(2, $withdrawal->ledgerEntries()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'ledger.withdrawal_held')->count());
        $this->assertSame('•••• 3456', $withdrawal->destination_snapshot['masked_identifier']);
        $this->assertStringNotContainsString('0599123456', json_encode($withdrawal->destination_snapshot));

        $merchant->update(['verification_status' => MerchantVerificationStatus::Suspended]);
        $this->actingAs($merchant->user)
            ->post(route('merchant.withdrawals.store'), $this->withdrawalPayload($method, '10.00'))
            ->assertSessionHasErrors('withdrawal');
        $this->assertDatabaseCount('withdrawal_requests', 1);
    }

    public function test_policy_is_permissioned_configurable_and_enforces_request_limits(): void
    {
        $merchant = $this->merchant();
        $method = $this->payoutMethod($merchant);
        $this->credit($merchant, LedgerStatus::Available, 30000);

        $this->actingAs($merchant->user)
            ->post(route('merchant.withdrawals.store'), $this->withdrawalPayload($method, '50.00'))
            ->assertSessionHasErrors('withdrawal');
        $this->assertDatabaseCount('withdrawal_requests', 0);

        $admin = $this->admin();
        $policy = [
            'enabled' => 1,
            'minimum_amount' => '50.00',
            'maximum_amount' => '100.00',
            'daily_limit' => '120.00',
            'weekly_limit' => '200.00',
            'current_password' => 'password',
        ];
        $this->actingAs($admin)
            ->put(route('admin.withdrawals.settings.update'), $policy)
            ->assertRedirect();
        $this->assertSame('1', MarketplaceSetting::query()->where('key', 'withdrawals.enabled')->value('value'));
        $this->assertTrue(AuditLog::query()->where('action', 'withdrawal_policy.updated')->exists());

        $this->actingAs($merchant->user)
            ->post(route('merchant.withdrawals.store'), $this->withdrawalPayload($method, '40.00'))
            ->assertSessionHasErrors('amount');
        $this->actingAs($merchant->user)
            ->post(route('merchant.withdrawals.store'), $this->withdrawalPayload($method, '110.00'))
            ->assertSessionHasErrors('amount');
        $this->actingAs($merchant->user)
            ->post(route('merchant.withdrawals.store'), $this->withdrawalPayload($method, '70.00'))
            ->assertRedirect();
        $this->actingAs($merchant->user)
            ->post(route('merchant.withdrawals.store'), $this->withdrawalPayload($method, '60.00'))
            ->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('withdrawal_requests', 1);

        $ordinaryUser = User::factory()->create();
        $this->actingAs($ordinaryUser)
            ->put(route('admin.withdrawals.settings.update'), $policy)
            ->assertForbidden();

        $settingsRole = Role::query()->create(['name' => 'Withdrawal Settings', 'slug' => 'withdrawal-settings']);
        $settingsRole->permissions()->attach(
            Permission::query()->where('slug', 'withdrawals.settings')->firstOrFail()
        );
        $settingsUser = User::factory()->create();
        $settingsUser->assignRole($settingsRole);
        $this->actingAs($settingsUser)
            ->get(route('admin.withdrawals.index'))
            ->assertOk()
            ->assertDontSee('0599123456');
    }

    public function test_rejection_releases_the_hold_once_and_approval_keeps_it_held(): void
    {
        $this->enableWithdrawals();
        $merchant = $this->merchant();
        $method = $this->payoutMethod($merchant);
        $this->credit($merchant, LedgerStatus::Available, 10000);
        $admin = $this->admin();

        $this->actingAs($merchant->user)
            ->post(route('merchant.withdrawals.store'), $this->withdrawalPayload($method, '80.00'));
        $withdrawal = WithdrawalRequest::query()->firstOrFail();
        $rejection = [
            'decision' => WithdrawalStatus::Rejected->value,
            'notes' => 'بيانات الاستلام تحتاج تصحيحًا',
            'lock_version' => 0,
            'current_password' => 'password',
        ];
        $this->actingAs($admin)
            ->patch(route('admin.withdrawals.review', $withdrawal), $rejection)
            ->assertRedirect();
        $this->actingAs($admin)
            ->patch(route('admin.withdrawals.review', $withdrawal), $rejection)
            ->assertRedirect();

        $balances = app(MerchantLedgerService::class)->balancesForMerchant($merchant->id);
        $this->assertSame(WithdrawalStatus::Rejected, $withdrawal->fresh()->status);
        $this->assertSame(10000, $balances['available']['ILS']);
        $this->assertSame(0, $balances['held']['ILS']);
        $this->assertSame(4, $withdrawal->ledgerEntries()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'ledger.withdrawal_released')->count());

        $this->actingAs($merchant->user)
            ->post(route('merchant.withdrawals.store'), $this->withdrawalPayload($method, '60.00'));
        $approved = WithdrawalRequest::query()->latest('id')->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.withdrawals.review', $approved), [
            'decision' => WithdrawalStatus::Approved->value,
            'lock_version' => 0,
            'current_password' => 'password',
        ])->assertRedirect();
        $this->assertSame(WithdrawalStatus::Approved, $approved->fresh()->status);
        $this->assertSame(2, $approved->ledgerEntries()->count());
        $this->assertSame(6000, app(MerchantLedgerService::class)
            ->balancesForMerchant($merchant->id)['held']['ILS']);
    }

    public function test_paid_withdrawal_moves_held_funds_once_and_stores_a_private_proof(): void
    {
        $this->enableWithdrawals();
        $merchant = $this->merchant();
        $otherMerchant = $this->merchant();
        $method = $this->payoutMethod($merchant);
        $this->credit($merchant, LedgerStatus::Available, 10000);
        $admin = $this->admin();
        $this->actingAs($merchant->user)
            ->post(route('merchant.withdrawals.store'), $this->withdrawalPayload($method, '80.00'));
        $withdrawal = WithdrawalRequest::query()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.withdrawals.review', $withdrawal), [
            'decision' => WithdrawalStatus::Approved->value,
            'lock_version' => 0,
            'current_password' => 'password',
        ]);

        $payment = [
            'transaction_reference' => 'BANK-TRANSFER-1001',
            'transferred_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'proof' => $this->proof(),
            'lock_version' => 1,
            'current_password' => 'password',
        ];
        $this->actingAs($admin)
            ->post(route('admin.withdrawals.pay', $withdrawal), $payment)
            ->assertRedirect();

        $withdrawal->refresh();
        $proof = $withdrawal->proof()->firstOrFail();
        $balances = app(MerchantLedgerService::class)->balancesForMerchant($merchant->id);
        $this->assertSame(WithdrawalStatus::Paid, $withdrawal->status);
        $this->assertSame('BANK-TRANSFER-1001', $withdrawal->transaction_reference);
        $this->assertSame(2000, $balances['available']['ILS']);
        $this->assertSame(0, $balances['held']['ILS']);
        $this->assertSame(8000, $balances['withdrawn']['ILS']);
        $this->assertSame(4, $withdrawal->ledgerEntries()->count());
        Storage::disk('local')->assertExists($proof->path);
        Storage::disk('public')->assertMissing($proof->path);

        $payment['proof'] = $this->proof();
        $this->actingAs($admin)->post(route('admin.withdrawals.pay', $withdrawal), $payment)->assertRedirect();
        $this->assertDatabaseCount('withdrawal_proofs', 1);
        $this->assertSame(4, $withdrawal->ledgerEntries()->count());

        $this->actingAs($otherMerchant->user)
            ->get(route('withdrawal-proofs.show', $proof))->assertForbidden();
        $this->actingAs($merchant->user)
            ->get(route('withdrawal-proofs.show', $proof))->assertOk();
        $this->actingAs($admin)
            ->get(route('withdrawal-proofs.show', $proof))->assertOk();
    }

    public function test_failed_financial_move_rolls_back_database_and_private_file(): void
    {
        $merchant = $this->merchant();
        $method = $this->payoutMethod($merchant);
        $admin = $this->admin();
        $withdrawal = WithdrawalRequest::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_payout_method_id' => $method->id,
            'amount' => 5000,
            'currency' => 'ILS',
            'status' => WithdrawalStatus::Approved,
            'reference' => 'WDR-BROKEN-TEST',
            'idempotency_key' => (string) Str::uuid(),
            'destination_snapshot' => ['masked_identifier' => $method->maskedIdentifier()],
            'requested_at' => now(),
            'approved_at' => now(),
            'lock_version' => 1,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.withdrawals.index'))
            ->post(route('admin.withdrawals.pay', $withdrawal), [
                'transaction_reference' => 'BROKEN-TRANSFER',
                'transferred_at' => now()->subMinute()->format('Y-m-d H:i:s'),
                'proof' => $this->proof(),
                'lock_version' => 1,
                'current_password' => 'password',
            ])
            ->assertRedirect(route('admin.withdrawals.index'))
            ->assertSessionHasErrors('amount');

        $this->assertSame(WithdrawalStatus::Approved, $withdrawal->fresh()->status);
        $this->assertDatabaseCount('withdrawal_proofs', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles('withdrawal-proofs'));
    }

    private function merchant(?User $user = null): Merchant
    {
        $user ??= User::factory()->create();
        return Merchant::query()->create([
            'user_id' => $user->id,
            'location_id' => Location::query()->firstOrFail()->id,
            'legal_name' => 'Withdrawal Merchant '.$user->id,
            'identity_number' => 'WITHDRAWAL-'.$user->id.'-'.fake()->unique()->numerify('######'),
            'phone' => '0599'.fake()->unique()->numerify('######'),
            'date_of_birth' => now()->subYears(30),
            'address' => 'Merchant address',
            'business_type' => 'Retail',
            'verification_status' => MerchantVerificationStatus::Verified,
        ]);
    }

    private function payoutMethod(Merchant $merchant): MerchantPayoutMethod
    {
        return MerchantPayoutMethod::query()->create([
            'merchant_id' => $merchant->id,
            'type' => 'mobile_wallet',
            'provider_name' => 'Jawwal Pay',
            'account_name' => $merchant->legal_name,
            'account_identifier' => '0599123456',
            'status' => MerchantPayoutMethodStatus::Verified,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now()->subHour(),
        ]);
    }

    private function credit(Merchant $merchant, LedgerStatus $status, int $amount): LedgerEntry
    {
        $key = 'test-credit-'.Str::uuid();
        return LedgerEntry::query()->create([
            'merchant_id' => $merchant->id,
            'entry_type' => LedgerEntryType::Adjustment,
            'direction' => 'credit',
            'amount' => $amount,
            'net_amount' => $amount,
            'status' => $status,
            'currency' => 'ILS',
            'reference' => $key,
            'idempotency_key' => $key,
            'created_at' => now(),
        ]);
    }

    private function withdrawal(
        Merchant $merchant,
        MerchantPayoutMethod $method,
        WithdrawalStatus $status,
        int $lockVersion,
        string $reference,
    ): WithdrawalRequest {
        return WithdrawalRequest::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_payout_method_id' => $method->id,
            'amount' => 5000,
            'currency' => 'ILS',
            'status' => $status,
            'reference' => $reference,
            'idempotency_key' => (string) Str::uuid(),
            'destination_snapshot' => [
                'provider_name' => $method->provider_name,
                'masked_identifier' => $method->maskedIdentifier(),
            ],
            'requested_at' => now()->subHour(),
            'approved_at' => in_array($status, [WithdrawalStatus::Approved, WithdrawalStatus::Paid], true) ? now()->subMinutes(30) : null,
            'paid_at' => $status === WithdrawalStatus::Paid ? now()->subMinutes(10) : null,
            'lock_version' => $lockVersion,
        ]);
    }

    private function withdrawalPayload(
        MerchantPayoutMethod $method,
        string $amount,
        ?string $idempotencyKey = null,
    ): array {
        return [
            'merchant_payout_method_id' => $method->id,
            'amount' => $amount,
            'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
            'current_password' => 'password',
        ];
    }

    private function enableWithdrawals(): void
    {
        MarketplaceSetting::query()->where('key', 'withdrawals.enabled')->update(['value' => '1']);
    }

    private function proof(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'withdrawal-proof.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nAAAAABJRU5ErkJggg=='),
        );
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }
}
