<?php

namespace Tests\Feature;

use App\Enums\{LedgerStatus, RefundDestinationStatus, RefundStatus};
use App\Models\{AuditLog, Delivery, LedgerEntry, MarketplaceSetting, Merchant, MerchantOrder, Order, Payment, Permission, RefundRequest, RefundTransfer, Role, User};
use App\Services\{AuditLogger, DeliveryDisputeService, MerchantLedgerService, RefundDestinationService, RefundReviewService, RefundTransferService};
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RefundTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
        Storage::fake('local');
        $this->freezeTime();
    }

    public function test_preparation_is_idempotent_freezes_recipient_and_does_not_move_money(): void
    {
        [$refund, $customer, $admin] = $this->scenario();
        $data = $this->preparation($refund);
        $this->actingAs($admin)->get(route('admin.refund-transfers.show', $refund))->assertOk();
        foreach ([1, 2] as $retry) {
            $this->post(route('admin.refund-transfers.prepare', $refund), $data)->assertSessionHasNoErrors();
        }
        $transfer = RefundTransfer::query()->sole();
        $this->assertSame(RefundStatus::Processing, $refund->fresh()->status);
        $this->assertSame(12000, $transfer->amount);
        $this->assertSame(9500, $transfer->merchant_amount);
        $this->assertSame(500, $transfer->commission_amount);
        $this->assertSame(2000, $transfer->delivery_amount);
        $this->assertSame(0, $transfer->service_amount);
        $this->assertStringNotContainsString('PS123456789101234', DB::table('refund_transfers')->value('recipient_snapshot'));
        $this->assertStringNotContainsString('Private Recipient', $transfer->toJson());
        $this->assertStringNotContainsString('PS123456789101234', AuditLog::all()->toJson());
        $this->assertSame(1, AuditLog::where('action', 'refund.transfer_prepared')->count());
        $this->assertDatabaseCount('ledger_entries', 3);
        $this->assertNull($transfer->paid_at);
        $destination = $refund->fresh()->activeDestination;
        $this->actingAs($customer)->post(route('refund-destinations.revoke', $destination), [
            'lock_version' => $destination->lock_version, 'current_password' => 'password',
        ])->assertSessionHasErrors('destination');
        $this->assertSame(RefundDestinationStatus::Verified, $destination->fresh()->status);
        $this->actingAs($admin)->get(route('admin.refund-transfers.show', $refund))->assertOk()->assertSee('تسجيل الحوالة');
    }

    public function test_paid_transfer_moves_only_merchant_net_and_is_idempotent_with_private_proof(): void
    {
        [$refund, $customer, $admin] = $this->scenario();
        $transfer = $this->prepare($refund, $admin);
        $proof = $this->proof('first');
        $data = $this->receipt($refund, $proof);
        $this->actingAs($admin);
        foreach ([1, 2] as $retry) {
            $this->post(route('admin.refund-transfers.paid', $refund), $data)->assertSessionHasNoErrors();
        }
        $transfer->refresh();
        $this->assertSame(RefundStatus::Paid, $refund->fresh()->status);
        $this->assertNotNull($transfer->paid_at);
        $this->assertSame('BANK-RFD-123', $transfer->transaction_reference);
        $this->assertSame($admin->id, $transfer->paid_by);
        Storage::disk('local')->assertExists($transfer->proof_path);
        $this->assertCount(1, Storage::disk('local')->allFiles('refund-proofs'));
        $this->assertDatabaseCount('ledger_entries', 5);
        $merchantId = $refund->saleEntry->merchant_id;
        $ledger = app(MerchantLedgerService::class);
        $this->assertSame(0, $ledger->bucketBalance($merchantId, LedgerStatus::Held));
        $this->assertSame(0, $ledger->bucketBalance($merchantId, LedgerStatus::Available));
        $this->assertSame(0, $ledger->bucketBalance($merchantId, LedgerStatus::Pending));
        $this->assertSame(9500, $ledger->bucketBalance($merchantId, LedgerStatus::Refunded));
        $this->assertSame('refunded', $refund->dispute->fresh()->status);
        $this->assertSame(1, AuditLog::where('action', 'refund.paid')->count());
        $this->post(route('admin.settlements.close-dispute', $refund->dispute->delivery), [
            'reason' => 'Try to release again', 'current_password' => 'password',
        ])->assertSessionHasErrors('dispute');
        $this->actingAs($customer)->get(route('orders.history'))->assertOk()->assertSee('تنزيل إثبات حوالة الاسترداد');
        $this->get(route('refund-transfers.proof', $transfer))->assertOk()->assertDownload('refund-proof-'.$transfer->id.'.pdf')
            ->assertHeader('Content-Type', 'application/octet-stream');
        $this->actingAs(User::factory()->create())->get(route('refund-transfers.proof', $transfer))->assertForbidden();
        $this->assertSame(12000, $refund->saleEntry->payment->amount);
        $this->assertSame(9500, $refund->saleEntry->net_amount);
    }

    public function test_role_permission_password_and_self_processing_are_enforced(): void
    {
        [$refund, $customer, $admin] = $this->scenario();
        $role = Role::create(['name' => 'Refund reviewer only', 'slug' => 'review-only']);
        $role->permissions()->attach(Permission::where('slug', 'refunds.review')->sole());
        $reviewer = User::factory()->create();
        $reviewer->assignRole($role);
        $this->actingAs($reviewer)->get(route('admin.refund-transfers.index'))->assertForbidden();
        $this->post(route('admin.refund-transfers.prepare', $refund), $this->preparation($refund))->assertForbidden();
        $data = $this->preparation($refund);
        $data['current_password'] = 'incorrect';
        $this->actingAs($admin)->post(route('admin.refund-transfers.prepare', $refund), $data)->assertSessionHasErrors('current_password');
        $customer->assignRole('admin');
        $this->actingAs($customer)->post(route('admin.refund-transfers.prepare', $refund), $this->preparation($refund))->assertForbidden();
        $this->assertDatabaseCount('refund_transfers', 0);
        $this->prepare($refund, $admin);
        $this->actingAs($customer)->post(route('admin.refund-transfers.paid', $refund), $this->receipt($refund, $this->proof()))->assertForbidden();
        $this->assertDatabaseCount('ledger_entries', 3);
    }

    public function test_prepare_rejects_unapproved_unrefreshed_and_changed_destinations(): void
    {
        [$refund, $customer, $admin] = $this->scenario();
        $this->actingAs($admin);
        $data = $this->preparation($refund);
        foreach (['lock_version', 'destination_version', 'destination_id'] as $key) {
            $bad = $data;
            $bad[$key] += 999;
            $this->post(route('admin.refund-transfers.prepare', $refund), $bad)->assertSessionHasErrors('refund');
        }
        $refund->update(['status' => RefundStatus::Requested]);
        $this->post(route('admin.refund-transfers.prepare', $refund), $data)->assertSessionHasErrors('refund');
        $refund->update(['status' => RefundStatus::Approved]);
        $refund->activeDestination->update(['status' => RefundDestinationStatus::Pending]);
        $this->post(route('admin.refund-transfers.prepare', $refund), $data)->assertSessionHasErrors('refund');
        $this->assertDatabaseCount('refund_transfers', 0);
    }

    public function test_invalid_receipts_leave_preparation_and_ledger_untouched(): void
    {
        [$refund, $customer, $admin] = $this->scenario();
        $this->prepare($refund, $admin);
        $proof = $this->proof();
        $this->actingAs($admin);
        foreach ([['amount', '0.01'], ['lock_version', 999], ['current_password', 'incorrect'],
            ['transferred_at', now()->subMinute()->toDateTimeString()], ['transferred_at', now()->addDay()->toDateTimeString()],
            ['transfer_confirmed', false], ['proof', UploadedFile::fake()->create('shell.php', 1, 'text/x-php')],
            ['proof', UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')]] as [$key, $value]) {
            $data = $this->receipt($refund, $proof);
            $data[$key] = $value;
            $this->post(route('admin.refund-transfers.paid', $refund), $data)->assertSessionHasErrors();
        }
        $this->assertSame(RefundStatus::Processing, $refund->fresh()->status);
        $this->assertDatabaseCount('ledger_entries', 3);
        $this->assertSame([], Storage::disk('local')->allFiles('refund-proofs'));
    }

    public function test_duplicate_reference_and_proof_cannot_record_another_refund(): void
    {
        [$first, , $admin] = $this->scenario();
        [$second, , $otherAdmin] = $this->scenario();
        $this->prepare($first, $admin);
        $this->prepare($second, $otherAdmin);
        $proof = $this->proof('first');
        $firstData = $this->receipt($first, $proof);
        app(RefundTransferService::class)->recordPaid($first, $admin, $firstData, $proof);
        $otherProof = $this->proof('second');
        $secondData = $this->receipt($second, $otherProof);
        $this->actingAs($otherAdmin)->post(route('admin.refund-transfers.paid', $second), $secondData)->assertSessionHasErrors('refund');
        $secondData['transaction_reference'] = 'DIFFERENT-REF';
        $secondData['proof'] = $proof;
        $this->post(route('admin.refund-transfers.paid', $second), $secondData)->assertSessionHasErrors('refund');
        $this->assertSame(RefundStatus::Processing, $second->fresh()->status);
        $this->assertCount(1, Storage::disk('local')->allFiles('refund-proofs'));
        $secondData['proof'] = $otherProof;
        $this->post(route('admin.refund-transfers.paid', $second), $secondData)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('ledger_entries', 10);
    }

    public function test_audit_failure_rolls_back_all_financial_state_and_cleans_only_uncommitted_proof(): void
    {
        [$refund, , $admin] = $this->scenario();
        $transfer = $this->prepare($refund, $admin);
        $realAudit = app(AuditLogger::class);
        $this->mock(AuditLogger::class)->shouldReceive('record')->andReturnUsing(function ($action, ...$args) use ($realAudit) {
            if ($action === 'refund.paid') {
                throw new \RuntimeException('Audit unavailable');
            }
            return $realAudit->record($action, ...$args);
        });
        $proof = $this->proof();
        $data = $this->receipt($refund, $proof);
        try {
            app(RefundTransferService::class)->recordPaid($refund, $admin, $data, $proof);
            $this->fail('Expected rollback');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertSame(RefundStatus::Processing, $refund->fresh()->status);
        $this->assertSame('open', $refund->dispute->fresh()->status);
        $this->assertNull($transfer->fresh()->paid_at);
        $this->assertNull($transfer->fresh()->proof_path);
        $this->assertDatabaseCount('ledger_entries', 3);
        $this->assertSame([], Storage::disk('local')->allFiles('refund-proofs'));
        $this->assertSame(0, AuditLog::where('action', 'ledger.refund_recorded')->count());
        $this->app->instance(AuditLogger::class, $realAudit);
        app(RefundTransferService::class)->recordPaid($refund, $admin, $data, $proof);
        $this->assertDatabaseCount('ledger_entries', 5);
    }

    public function test_changed_financial_state_after_preparation_fails_closed(): void
    {
        [$refund, , $admin] = $this->scenario();
        $this->prepare($refund, $admin);
        $refund->saleEntry->merchantOrder->update(['delivery_fee' => 10, 'service_fee' => 10]);
        $proof = $this->proof();
        $this->actingAs($admin)->post(route('admin.refund-transfers.paid', $refund), $this->receipt($refund, $proof))->assertSessionHasErrors('refund');
        $this->assertSame(RefundStatus::Processing, $refund->fresh()->status);
        $this->assertDatabaseCount('ledger_entries', 3);
        $this->assertSame([], Storage::disk('local')->allFiles('refund-proofs'));
    }

    public function test_paid_records_are_immutable_and_conflicting_retries_are_not_silently_accepted(): void
    {
        [$refund, , $admin] = $this->scenario();
        $transfer = $this->prepare($refund, $admin);
        $proof = $this->proof();
        $data = $this->receipt($refund, $proof);
        app(RefundTransferService::class)->recordPaid($refund, $admin, $data, $proof);
        $data['transaction_reference'] = 'CHANGED-REF';
        $this->actingAs($admin)->post(route('admin.refund-transfers.paid', $refund), $data)->assertSessionHasErrors('refund');
        foreach (['update', 'delete'] as $operation) {
            $transfer->refresh();
            try {
                $operation === 'update' ? $transfer->update(['transaction_reference' => 'tampered']) : $transfer->delete();
                $this->fail('Should fail');
            } catch (\LogicException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
        $this->assertDatabaseCount('ledger_entries', 5);
    }

    public function test_zero_net_refund_never_becomes_a_delayed_settlement(): void
    {
        [$refund, , $admin] = $this->scenario(100);
        $this->prepare($refund, $admin);
        $proof = $this->proof('zero-net');
        app(RefundTransferService::class)->recordPaid($refund, $admin, $this->receipt($refund, $proof), $proof);
        MarketplaceSetting::updateOrCreate(['key' => 'settlement.auto_release_enabled'], ['value' => '1']);
        $this->travel(8)->days();
        $this->artisan('settlements:release-due')->expectsOutputToContain('Released: 0; failed: 0')->assertExitCode(0);
        $this->assertNull($refund->dispute->delivery->fresh()->settled_at);
        $this->assertSame(0, $refund->fresh()->transfer->merchant_amount);
        $this->assertDatabaseCount('ledger_entries', 5);
    }

    public function test_total_preparations_cannot_exceed_the_accepted_payment(): void
    {
        [$refund, $customer, $admin] = $this->scenario();
        $this->prepare($refund, $admin);
        // Build a second suborder claiming the same payment to exercise the aggregate guard.
        $suborder = $refund->saleEntry->merchantOrder->replicate();
        $suborder->group_key = 'test-second-suborder';
        $suborder->save();
        $delivery = $refund->dispute->delivery->replicate();
        $delivery->merchant_order_id = $suborder->id;
        $delivery->reference = 'SECOND-DEL';
        $delivery->assignment_key = 'SECOND-DEL';
        $delivery->save();
        $sale = $refund->saleEntry->replicate();
        $sale->merchant_order_id = $suborder->id;
        $sale->reference = 'second-sale';
        $sale->idempotency_key = 'second-sale';
        $sale->save();
        app(DeliveryDisputeService::class)->open($delivery, $customer, 'Second damaged suborder');
        $second = app(RefundReviewService::class)->request($delivery, $customer, 'Second refund request');
        $this->approveAndVerify($second, $customer, $admin);
        $this->actingAs($admin)->post(route('admin.refund-transfers.prepare', $second), $this->preparation($second))->assertSessionHasErrors('refund');
        $this->assertDatabaseCount('refund_transfers', 1);
    }

    public function test_cancellation_preserves_history_money_and_dispute_and_allows_one_replacement(): void
    {
        [$refund, $customer, $admin] = $this->scenario();
        $oldPreparation = $this->preparation($refund);
        $first = $this->prepare($refund, $admin);
        $snapshot = $first->recipient_snapshot;
        $data = $this->cancellation($refund);
        $this->actingAs($admin)->post(route('admin.refund-transfers.cancel', $first), $data)->assertSessionHasNoErrors();
        $first->refresh();
        $this->assertNotNull($first->cancelled_at);
        $this->assertNull($first->active_key);
        $this->assertNull($first->paid_at);
        $this->assertSame($admin->id, $first->cancelled_by);
        $this->assertSame($snapshot, $first->recipient_snapshot);
        $this->assertSame($data['cancellation_reason'], $first->cancellation_reason);
        $this->assertStringNotContainsString($data['cancellation_reason'], DB::table('refund_transfers')->value('cancellation_reason'));
        $this->assertStringNotContainsString($data['cancellation_reason'], $first->toJson());
        $this->assertStringNotContainsString($data['cancellation_reason'], AuditLog::all()->toJson());
        $this->assertSame(RefundStatus::Approved, $refund->fresh()->status);
        $this->assertNull($refund->fresh()->transfer);
        $this->assertSame('open', $refund->dispute->fresh()->status);
        $this->assertDatabaseCount('ledger_entries', 3);
        $this->assertSame(9500, app(MerchantLedgerService::class)->bucketBalance($refund->saleEntry->merchant_id, LedgerStatus::Held));
        $this->assertSame([], Storage::disk('local')->allFiles('refund-proofs'));
        $this->post(route('admin.refund-transfers.prepare', $refund), $oldPreparation)->assertSessionHasErrors('refund');
        $replacement = $this->prepare($refund, $admin);
        $this->assertNotSame($first->id, $replacement->id);
        $this->assertSame($replacement->id, $refund->fresh()->transfer->id);
        $this->assertSame(2, $refund->transfers()->count());
        $version = $refund->fresh()->lock_version;
        $this->post(route('admin.refund-transfers.cancel', $first), $data)->assertSessionHasNoErrors();
        $this->assertSame($version, $refund->fresh()->lock_version);
        $this->assertSame(RefundStatus::Processing, $refund->fresh()->status);
        $this->assertNull($replacement->fresh()->cancelled_at);
        $this->assertSame(1, AuditLog::where('action', 'refund.transfer_cancelled')->count());
        $this->get(route('admin.refund-transfers.show', $refund))->assertOk()
            ->assertSee('المحاولة #'.$first->id)->assertSee('المحاولة #'.$replacement->id)->assertSee($data['cancellation_reason']);
        $this->get(route('refund-transfers.proof', $first))->assertNotFound();
        $proof = $this->proof('replacement');
        $this->post(route('admin.refund-transfers.paid', $refund), $this->receipt($refund, $proof))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('ledger_entries', 5);
        $this->assertSame(RefundStatus::Paid, $refund->fresh()->status);
        $this->actingAs($customer)->get(route('orders.history'))->assertOk()
            ->assertSee(route('refund-transfers.proof', $replacement), false)
            ->assertDontSee(route('refund-transfers.proof', $first), false);
    }

    public function test_stale_paid_forms_cannot_record_a_cancelled_or_replacement_attempt(): void
    {
        [$refund, , $admin] = $this->scenario();
        $first = $this->prepare($refund, $admin);
        $oldReceipt = $this->receipt($refund, $this->proof());
        app(RefundTransferService::class)->cancelPreparation($first, $admin, $this->cancellation($refund));
        $this->actingAs($admin)->post(route('admin.refund-transfers.paid', $refund), $oldReceipt)->assertSessionHasErrors('refund');
        $replacement = $this->prepare($refund, $admin);
        // Even substituting the current version cannot redirect an old receipt to the new attempt.
        $oldReceipt['lock_version'] = $refund->fresh()->lock_version;
        $this->post(route('admin.refund-transfers.paid', $refund), $oldReceipt)->assertSessionHasErrors('refund');
        $this->assertNull($replacement->fresh()->paid_at);
        $this->assertDatabaseCount('ledger_entries', 3);
        $this->assertSame([], Storage::disk('local')->allFiles('refund-proofs'));
    }

    public function test_cancellation_requires_separate_permission_password_attestation_version_and_nonowner(): void
    {
        [$refund, $customer, $admin] = $this->scenario();
        $transfer = $this->prepare($refund, $admin);
        $data = $this->cancellation($refund);
        $role = Role::create(['name' => 'Pay only', 'slug' => 'pay-only']);
        $role->permissions()->attach(Permission::where('slug', 'refunds.pay')->sole());
        $operator = User::factory()->create();
        $operator->assignRole($role);
        $this->actingAs($operator)->get(route('admin.refund-transfers.show', $refund))->assertOk()
            ->assertDontSee('name="not_sent_confirmed"', false);
        $this->post(route('admin.refund-transfers.cancel', $transfer), $data)->assertForbidden();
        $customer->assignRole('admin');
        $this->actingAs($customer)->post(route('admin.refund-transfers.cancel', $transfer), $data)->assertForbidden();
        $this->actingAs($admin);
        foreach (['current_password' => 'wrong', 'not_sent_confirmed' => false, 'lock_version' => 999, 'cancellation_reason' => ''] as $field => $value) {
            $this->post(route('admin.refund-transfers.cancel', $transfer), array_replace($data, [$field => $value]))->assertSessionHasErrors();
        }
        $this->assertNull(session('_old_input.cancellation_reason'));
        $this->assertNull($transfer->fresh()->cancelled_at);
        $this->assertSame(RefundStatus::Processing, $refund->fresh()->status);
        $this->assertDatabaseCount('ledger_entries', 3);
    }

    public function test_paid_or_partially_recorded_transfer_cannot_be_cancelled(): void
    {
        [$refund, , $admin] = $this->scenario();
        $transfer = $this->prepare($refund, $admin);
        $this->actingAs($admin);
        foreach (['transaction_reference' => 'TRACE-123', 'proof_path' => 'private-proof.pdf', 'paid_by' => $admin->id,
            'paid_at' => now()->toDateTimeString(), 'transferred_at' => now()->toDateTimeString(),
            'proof_mime' => 'application/pdf', 'proof_size' => 0, 'proof_sha256' => str_repeat('a', 64)] as $field => $value) {
            DB::table('refund_transfers')->where('id', $transfer->id)->update([$field => $value]);
            $this->post(route('admin.refund-transfers.cancel', $transfer), $this->cancellation($refund))->assertSessionHasErrors('refund');
            DB::table('refund_transfers')->where('id', $transfer->id)->update([$field => null]);
        }
        $proof = $this->proof('paid');
        app(RefundTransferService::class)->recordPaid($refund, $admin, $this->receipt($refund, $proof), $proof);
        $this->post(route('admin.refund-transfers.cancel', $transfer), $this->cancellation($refund))->assertSessionHasErrors('refund');
        $this->assertSame(RefundStatus::Paid, $refund->fresh()->status);
        $this->assertNull($transfer->fresh()->cancelled_at);
        Storage::disk('local')->assertExists($transfer->fresh()->proof_path);
        $this->assertDatabaseCount('ledger_entries', 5);
    }

    public function test_cancelled_history_is_immutable_and_migration_cannot_discard_it(): void
    {
        [$refund, , $admin] = $this->scenario();
        $transfer = $this->prepare($refund, $admin);
        app(RefundTransferService::class)->cancelPreparation($transfer, $admin, $this->cancellation($refund));
        foreach (['update', 'delete'] as $operation) {
            try {
                $transfer->refresh();
                $operation === 'update' ? $transfer->update(['cancellation_reason' => 'Changed']) : $transfer->delete();
                $this->fail('Terminal history must be preserved');
            } catch (\LogicException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
        $migration = require database_path('migrations/2026_09_02_000020_add_refund_transfer_cancellation.php');
        try {
            $migration->down();
            $this->fail('Non-empty migration rollback must be refused');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('non-empty', $exception->getMessage());
        }
        $this->assertSame(1, $refund->transfers()->count());
        $this->assertNotNull($transfer->fresh()->cancelled_at);
    }

    public function test_cancellation_audit_failure_rolls_back_attempt_and_parent(): void
    {
        [$refund, , $admin] = $this->scenario();
        $transfer = $this->prepare($refund, $admin);
        $version = $refund->fresh()->lock_version;
        $this->mock(AuditLogger::class, function ($mock) {
            $mock->shouldReceive('record')->once()->withArgs(fn ($action, ...$args) => $action === 'refund.transfer_cancelled')
                ->andThrow(new \RuntimeException('cancel audit unavailable'));
        });
        try {
            app(RefundTransferService::class)->cancelPreparation($transfer, $admin, $this->cancellation($refund));
            $this->fail('Audit failure should propagate');
        } catch (\RuntimeException $exception) {
            $this->assertSame('cancel audit unavailable', $exception->getMessage());
        }
        $this->assertSame(RefundStatus::Processing, $refund->fresh()->status);
        $this->assertSame($version, $refund->fresh()->lock_version);
        $this->assertNull($transfer->fresh()->cancelled_at);
        $this->assertSame('refund:'.$refund->id, $transfer->fresh()->active_key);
        $this->assertDatabaseCount('ledger_entries', 3);
        $this->assertSame(0, AuditLog::where('action', 'refund.transfer_cancelled')->count());
    }

    public function test_customer_can_replace_destination_after_cancellation_but_must_verify_again(): void
    {
        [$refund, $customer, $admin] = $this->scenario();
        $first = $this->prepare($refund, $admin);
        app(RefundTransferService::class)->cancelPreparation($first, $admin, $this->cancellation($refund));
        $destination = $refund->fresh()->activeDestination;
        $this->actingAs($customer)->post(route('refund-destinations.revoke', $destination), [
            'lock_version' => $destination->lock_version, 'current_password' => 'password',
        ])->assertSessionHasNoErrors();
        $new = app(RefundDestinationService::class)->submit($refund, $customer, [
            'type' => 'bank_account', 'provider_name' => 'New Provider', 'account_name' => 'New Recipient',
            'account_identifier' => 'PS999456789101234',
        ], $refund->fresh()->lock_version);
        $this->actingAs($admin)->post(route('admin.refund-transfers.prepare', $refund), $this->preparation($refund))->assertSessionHasErrors('refund');
        app(RefundDestinationService::class)->review($new, $admin, RefundDestinationStatus::Verified, 'Fresh independent check', 0, true);
        $replacement = $this->prepare($refund, $admin);
        $this->assertSame($new->id, $replacement->refund_destination_id);
        $this->assertSame('PS999456789101234', $replacement->recipient_snapshot['account_identifier']);
        $this->assertSame('PS123456789101234', $first->fresh()->recipient_snapshot['account_identifier']);
    }

    public function test_migration_preserves_existing_prepared_and_paid_attempts_and_restores_empty_schema(): void
    {
        [$first, , $admin] = $this->scenario();
        [$second, , $otherAdmin] = $this->scenario();
        $this->prepare($first, $admin);
        $this->prepare($second, $otherAdmin);
        $proof = $this->proof('migration-paid');
        app(RefundTransferService::class)->recordPaid($second, $otherAdmin, $this->receipt($second, $proof), $proof);
        $oldRows = DB::table('refund_transfers')->orderBy('id')->get()->map(function ($row) {
            return array_diff_key((array) $row, array_flip(['active_key', 'cancelled_by', 'cancelled_at', 'cancellation_reason']));
        })->all();
        // Reconstruct the previous schema using only isolated in-memory test fixtures.
        DB::table('refund_transfers')->delete();
        $migration = require database_path('migrations/2026_09_02_000020_add_refund_transfer_cancellation.php');
        $migration->down();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('refund_transfers', 'active_key'));
        DB::table('refund_transfers')->insert($oldRows);
        $migration->up();
        foreach ($oldRows as $row) {
            $migrated = (array) DB::table('refund_transfers')->where('id', $row['id'])->sole();
            $this->assertSame($row, array_intersect_key($migrated, $row));
            $this->assertSame('refund:'.$row['refund_request_id'], $migrated['active_key']);
            $this->assertNull($migrated['cancelled_at']);
        }
        $this->assertNotNull($second->fresh()->transfer->paid_at);
        $this->assertNull($first->fresh()->transfer->paid_at);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $duplicate = $first->fresh()->transfer->replicate();
        $this->expectException(\Illuminate\Database\QueryException::class);
        $duplicate->save();
    }

    public function test_cancellation_rejects_changed_financial_state_without_releasing_preparation(): void
    {
        [$refund, , $admin] = $this->scenario();
        $transfer = $this->prepare($refund, $admin);
        $refund->saleEntry->merchantOrder->update(['delivery_fee' => 10, 'service_fee' => 10]);
        $this->actingAs($admin)->post(route('admin.refund-transfers.cancel', $transfer), $this->cancellation($refund))
            ->assertSessionHasErrors('refund');
        $this->assertNull($transfer->fresh()->cancelled_at);
        $this->assertSame(RefundStatus::Processing, $refund->fresh()->status);
        $this->assertDatabaseCount('ledger_entries', 3);
    }

    public function test_queue_separates_status_and_currency_without_counting_cancelled_attempts_twice(): void
    {
        [$approved, , $admin] = $this->scenario();
        [$processing, , $operator] = $this->scenario();
        for ($i = 0; $i < 2; $i++) {
            $attempt = $this->prepare($processing, $operator);
            app(RefundTransferService::class)->cancelPreparation($attempt, $operator, $this->cancellation($processing));
        }
        $this->prepare($processing, $operator);
        [$paid, , $payer] = $this->scenario(currency: 'USD');
        $this->prepare($paid, $payer);
        $proof = $this->proof('queue-paid');
        app(RefundTransferService::class)->recordPaid($paid, $payer, $this->receipt($paid, $proof), $proof);
        [$excluded] = $this->scenario();
        $excluded->update(['status' => RefundStatus::Requested]);
        $tables = ['refund_requests', 'refund_destinations', 'refund_transfers', 'ledger_entries', 'payments', 'delivery_disputes', 'audit_logs'];
        $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()]);
        $response = $this->actingAs($admin)->get(route('admin.refund-transfers.index'))->assertOk();
        $response->assertViewHas('refunds', fn ($rows) => $rows->total() === 3);
        $response->assertViewHas('summary', function ($groups) {
            $this->assertCount(3, $groups);
            $amounts = $groups->mapWithKeys(fn ($row) => [$row->status->value.':'.$row->currency => (int) $row->total_minor])->all();
            $this->assertSame(['approved:ILS' => 12000, 'paid:USD' => 12000, 'processing:ILS' => 12000], $amounts);
            $this->assertSame(3, (int) $groups->sum('refund_count'));
            return true;
        });
        $response->assertSee('محاولات تجهيز ملغاة محفوظة: 2')->assertSee('لم يُسجّل إثبات الحوالة بعد')
            ->assertDontSee($excluded->reference)->assertDontSee('PS123456789101234')->assertDontSee('Private Recipient')
            ->assertDontSee('BANK-RFD-123')->assertDontSee($paid->fresh()->transfer->proof_path)
            ->assertDontSee($this->cancellation($processing)['cancellation_reason'])
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        foreach ($before as $table => $rows) {
            $this->assertSame($rows, DB::table($table)->orderBy('id')->get()->toJson(), $table.' must remain unchanged');
        }
        $this->get(route('admin.refund-transfers.index', ['status' => 'approved']))->assertOk()
            ->assertViewHas('refunds', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $approved->id);
        $excluded->update(['status' => RefundStatus::Rejected]);
        $this->get(route('admin.refund-transfers.index'))->assertOk()->assertDontSee($excluded->reference);
    }

    public function test_queue_combines_filters_and_includes_entire_end_day_without_start_date(): void
    {
        [$first, , $admin] = $this->scenario();
        [$middle, , $operator] = $this->scenario();
        [$last] = $this->scenario();
        $first->forceFill(['created_at' => '2026-09-01 23:59:59'])->save();
        $middle->forceFill(['created_at' => '2026-09-02 23:59:59'])->save();
        $last->forceFill(['created_at' => '2026-09-03 00:00:00'])->save();
        $attempt = $this->prepare($middle, $operator);
        app(RefundTransferService::class)->cancelPreparation($attempt, $operator, $this->cancellation($middle));
        $filters = ['q' => $middle->reference, 'status' => 'approved', 'from' => '2026-09-02',
            'to' => '2026-09-02', 'cancelled_only' => '1', 'sort' => 'oldest'];
        $this->actingAs($admin)->get(route('admin.refund-transfers.index', $filters))->assertOk()
            ->assertViewHas('refunds', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $middle->id)
            ->assertViewHas('summary', fn ($groups) => $groups->count() === 1 && (int) $groups->first()->total_minor === 12000);
        $this->get(route('admin.refund-transfers.index', ['to' => '2026-09-02', 'sort' => 'oldest']))->assertOk()
            ->assertViewHas('refunds', fn ($rows) => $rows->pluck('id')->all() === [$first->id, $middle->id]);
        $this->get(route('admin.refund-transfers.index', ['from' => '2026-09-03']))->assertOk()
            ->assertViewHas('refunds', fn ($rows) => $rows->pluck('id')->all() === [$last->id]);
    }

    public function test_queue_search_is_literal_bound_and_escaped_in_html(): void
    {
        [$first, , $admin] = $this->scenario();
        [$other] = $this->scenario();
        // Isolated fixtures intentionally exercise characters absent from generated references.
        DB::table('refund_requests')->where('id', $first->id)->update(['reference' => 'QUEUE_0%!']);
        DB::table('refund_requests')->where('id', $other->id)->update(['reference' => 'QUEUE-ZZ']);
        $this->actingAs($admin);
        foreach (['_', '%', '!', '0'] as $term) {
            $this->get(route('admin.refund-transfers.index', ['q' => $term]))->assertOk()
                ->assertViewHas('refunds', fn ($rows) => $rows->pluck('id')->all() === [$first->id]);
        }
        foreach (["' OR 1=1 --", '<script>alert(1)</script>', 'no-results'] as $term) {
            $response = $this->get(route('admin.refund-transfers.index', ['q' => $term]))->assertOk()
                ->assertViewHas('refunds', fn ($rows) => $rows->total() === 0)
                ->assertViewHas('summary', fn ($groups) => $groups->isEmpty());
            if (str_contains($term, '<script>')) {
                $response->assertDontSee($term, false)->assertSee(e($term), false);
            }
        }
    }

    public function test_queue_rejects_invalid_filters_and_unauthorized_readers(): void
    {
        [$refund, $customer, $admin] = $this->scenario();
        $this->get(route('admin.refund-transfers.index'))->assertRedirect(route('login'));
        $this->actingAs($customer)->get(route('admin.refund-transfers.index'))->assertForbidden();
        $this->actingAs($admin);
        foreach ([['status' => 'requested'], ['status' => ['paid']], ['q' => str_repeat('x', 101)],
            ['from' => '2026-02-30'], ['to' => 'invalid'], ['from' => '2026-09-03', 'to' => '2026-09-02'],
            ['sort' => 'amount desc'], ['cancelled_only' => 'yes'], ['page' => 0], ['page' => 1000001]] as $filters) {
            $this->getJson(route('admin.refund-transfers.index', $filters))->assertUnprocessable();
        }
        $this->get(route('admin.refund-transfers.index', ['status' => '', 'from' => '', 'to' => '', 'sort' => '']))->assertOk();
    }

    public function test_queue_pagination_preserves_filters_and_summary_covers_all_pages_with_bounded_queries(): void
    {
        $ids = [];
        for ($i = 0; $i < 31; $i++) {
            [$refund, , $admin] = $this->scenario();
            $ids[] = $refund->id;
        }
        $this->actingAs($admin);
        $filters = ['status' => 'approved', 'sort' => 'oldest', 'q' => 'RFD'];
        DB::enableQueryLog();
        $response = $this->get(route('admin.refund-transfers.index', $filters))->assertOk();
        $queries = collect(DB::getQueryLog())->filter(fn ($entry) => preg_match('/from ["`]refund_(requests|destinations|transfers)["`]/i', $entry['query']));
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(5, $queries->count(), 'Queue queries must not grow per displayed row');
        $response->assertViewHas('refunds', function ($rows) use ($ids) {
            $this->assertSame(31, $rows->total());
            $this->assertSame(array_slice($ids, 0, 30), $rows->pluck('id')->all());
            parse_str(parse_url($rows->nextPageUrl(), PHP_URL_QUERY), $next);
            $this->assertSame('approved', $next['status']);
            $this->assertSame('oldest', $next['sort']);
            $this->assertSame('RFD', $next['q']);
            $this->assertSame('2', $next['page']);
            return true;
        });
        $response->assertViewHas('summary', fn ($groups) => (int) $groups->first()->total_minor === 372000);
        $this->get(route('admin.refund-transfers.index', $filters + ['page' => 2]))->assertOk()
            ->assertViewHas('refunds', fn ($rows) => $rows->pluck('id')->all() === [$ids[30]])
            ->assertViewHas('summary', fn ($groups) => (int) $groups->first()->total_minor === 372000);
        $this->get(route('admin.refund-transfers.index', ['sort' => 'newest']))->assertOk()
            ->assertViewHas('refunds', fn ($rows) => $rows->first()->id === $ids[30]);
    }

    public function test_queue_distinguishes_unverified_destination_from_preparation(): void
    {
        [$refund, $customer, $admin] = $this->scenario();
        $destination = $refund->activeDestination;
        $this->actingAs($customer)->post(route('refund-destinations.revoke', $destination), [
            'lock_version' => $destination->lock_version, 'current_password' => 'password',
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->get(route('admin.refund-transfers.index'))->assertOk()
            ->assertSee('بانتظار وسيلة استلام موثّقة')->assertDontSee('وسيلة الاستلام موثّقة؛ بانتظار التجهيز');
    }

    public function test_integrity_report_accepts_healthy_lifecycle_and_does_not_write_or_expose_private_data(): void
    {
        [$refund, , $admin] = $this->scenario();
        $check = app(\App\Services\RefundIntegrityCheck::class);
        $this->assertSame([], $check->inspect($refund, $admin)['issues']);
        $first = $this->prepare($refund, $admin);
        $this->assertSame([], $check->inspect($refund, $admin)['issues']);
        app(RefundTransferService::class)->cancelPreparation($first, $admin, $this->cancellation($refund));
        $this->assertSame([], $check->inspect($refund, $admin)['issues']);
        $paid = $this->prepare($refund, $admin);
        $proof = $this->proof('integrity');
        app(RefundTransferService::class)->recordPaid($refund, $admin, $this->receipt($refund, $proof), $proof);
        $tables = ['refund_requests', 'refund_transfers', 'refund_destinations', 'ledger_entries', 'payments', 'delivery_disputes', 'audit_logs'];
        $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()]);
        $response = $this->actingAs($admin)->get(route('admin.refund-transfers.check', $refund))->assertOk()
            ->assertViewHas('report', fn ($report) => $report['issues'] === [] && $report['attempt_count'] === 2 && $report['ledger_count'] === 2)
            ->assertSee('لم تظهر تعارضات ضمن الفحوص المنفّذة')->assertDontSee('PS123456789101234')
            ->assertDontSee('Private Recipient')->assertDontSee('BANK-RFD-123')->assertDontSee($paid->fresh()->proof_path)
            ->assertDontSee($this->cancellation($refund)['cancellation_reason'])->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        foreach ($before as $table => $rows) {
            $this->assertSame($rows, DB::table($table)->orderBy('id')->get()->toJson(), 'Read-only report: '.$table);
        }
    }

    public function test_integrity_report_detects_missing_and_changed_proof_without_returning_file_contents(): void
    {
        [$refund, , $admin] = $this->scenario();
        $transfer = $this->prepare($refund, $admin);
        $proof = $this->proof('integrity-proof');
        app(RefundTransferService::class)->recordPaid($refund, $admin, $this->receipt($refund, $proof), $proof);
        $path = $transfer->fresh()->proof_path;
        $original = Storage::disk('local')->get($path);
        Storage::disk('local')->put($path, 'X'.substr($original, 1));
        $this->assertContains('proof_integrity', $this->integrityCodes($refund, $admin));
        Storage::disk('local')->put($path, $original);
        $this->assertSame([], $this->integrityCodes($refund, $admin));
        Storage::disk('local')->delete($path);
        $this->assertContains('proof_missing', $this->integrityCodes($refund, $admin));
        $this->assertSame(RefundStatus::Paid, $refund->fresh()->status);
        $this->assertDatabaseCount('ledger_entries', 5);
        DB::table('refund_transfers')->where('id', $transfer->id)->update(['proof_path' => '../private-secret.pdf']);
        $this->assertContains('proof_path', $this->integrityCodes($refund, $admin));
        $this->actingAs($admin)->get(route('admin.refund-transfers.check', $refund))->assertOk()
            ->assertDontSee('private-secret.pdf')->assertDontSee('integrity-proof');
        DB::table('refund_transfers')->where('id', $transfer->id)->update(['proof_sha256' => null]);
        $this->assertContains('proof_metadata', $this->integrityCodes($refund, $admin));
    }

    public function test_refund_proof_download_fails_closed_for_path_size_hash_and_serialization_tampering(): void
    {
        [$refund, , $admin] = $this->scenario();
        $transfer = $this->prepare($refund, $admin);
        $proof = $this->proof('download-integrity');
        app(RefundTransferService::class)->recordPaid($refund, $admin, $this->receipt($refund, $proof), $proof);
        $transfer->refresh();
        $original = [
            'proof_path' => $transfer->proof_path,
            'proof_size' => $transfer->proof_size,
            'proof_sha256' => $transfer->proof_sha256,
        ];

        $this->actingAs($admin)->get(route('refund-transfers.proof', $transfer))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $viewAuditCount = AuditLog::query()->where('action', 'refund.proof_viewed')->count();

        DB::table('refund_transfers')->where('id', $transfer->id)
            ->update(['proof_size' => $original['proof_size'] + 1]);
        $this->get(route('refund-transfers.proof', $transfer))->assertNotFound();

        DB::table('refund_transfers')->where('id', $transfer->id)
            ->update(['proof_size' => $original['proof_size'], 'proof_sha256' => str_repeat('0', 64)]);
        $this->get(route('refund-transfers.proof', $transfer))->assertNotFound();

        Storage::disk('local')->put('private-secret.pdf', 'PRIVATE REFUND SECRET');
        DB::table('refund_transfers')->where('id', $transfer->id)->update([
            'proof_path' => 'private-secret.pdf',
            'proof_size' => strlen('PRIVATE REFUND SECRET'),
            'proof_sha256' => hash('sha256', 'PRIVATE REFUND SECRET'),
        ]);
        $this->get(route('refund-transfers.proof', $transfer))
            ->assertNotFound()
            ->assertDontSee('PRIVATE REFUND SECRET');
        $this->assertSame($viewAuditCount, AuditLog::query()->where('action', 'refund.proof_viewed')->count());

        DB::table('refund_transfers')->where('id', $transfer->id)->update($original);
        foreach (['recipient_snapshot', 'transaction_reference', 'proof_path', 'proof_mime', 'proof_size', 'proof_sha256'] as $key) {
            $this->assertArrayNotHasKey($key, $transfer->fresh()->toArray());
        }
    }

    public function test_integrity_report_detects_wrong_missing_and_extra_refund_ledger_entries(): void
    {
        [$refund, , $admin] = $this->scenario();
        $this->prepare($refund, $admin);
        $proof = $this->proof('integrity-ledger');
        app(RefundTransferService::class)->recordPaid($refund, $admin, $this->receipt($refund, $proof), $proof);
        $credit = DB::table('ledger_entries')->where('idempotency_key', 'refund:'.$refund->id.':refunded:credit')->sole();
        foreach (['net_amount' => 12000, 'amount' => 12000, 'currency' => 'USD', 'direction' => 'debit', 'status' => 'available'] as $field => $value) {
            DB::table('ledger_entries')->where('id', $credit->id)->update([$field => $value]);
            $this->assertContains('ledger_refunded', $this->integrityCodes($refund, $admin));
            DB::table('ledger_entries')->where('id', $credit->id)->update([$field => $credit->{$field}]);
        }
        $duplicate = (array) $credit;
        unset($duplicate['id']);
        $duplicate['idempotency_key'] = 'unexpected-refund';
        $duplicate['reference'] = 'unexpected-refund';
        $extraId = DB::table('ledger_entries')->insertGetId($duplicate);
        $this->assertContains('ledger_count', $this->integrityCodes($refund, $admin));
        DB::table('ledger_entries')->where('id', $extraId)->delete();
        DB::table('ledger_entries')->where('id', $credit->id)->delete();
        $codes = $this->integrityCodes($refund, $admin);
        $this->assertContains('ledger_count', $codes);
        $this->assertContains('ledger_refunded', $codes);
        $this->assertSame(RefundStatus::Paid, $refund->fresh()->status);
    }

    public function test_integrity_report_handles_corrupted_snapshot_status_and_encrypted_recipient_without_crashing(): void
    {
        [$refund, , $admin] = $this->scenario();
        $transfer = $this->prepare($refund, $admin);
        DB::table('refund_requests')->where('id', $refund->id)->update(['amount_snapshot' => 'not-json', 'status' => 'unknown']);
        DB::table('refund_transfers')->where('id', $transfer->id)->update(['recipient_snapshot' => 'unreadable-ciphertext']);
        $codes = $this->integrityCodes($refund, $admin);
        $this->assertContains('refund_amount', $codes);
        $this->assertContains('refund_status', $codes);
        $this->actingAs($admin)->get(route('admin.refund-transfers.check', $refund))->assertOk()
            ->assertSee('توجد تعارضات تحتاج مراجعة')->assertDontSee('unreadable-ciphertext')->assertDontSee('not-json');
    }

    public function test_integrity_report_flags_attempt_state_linkage_amount_and_payment_capacity(): void
    {
        [$refund, , $admin] = $this->scenario();
        $transfer = $this->prepare($refund, $admin);
        DB::table('refund_transfers')->where('id', $transfer->id)->update(['active_key' => null, 'transaction_reference' => 'partial-receipt', 'amount' => 12001]);
        $codes = $this->integrityCodes($refund, $admin);
        foreach (['attempt_key', 'attempt_state', 'attempt_amount', 'payment_capacity'] as $code) {
            $this->assertContains($code, $codes);
        }
        DB::table('refund_transfers')->where('id', $transfer->id)->update(['cancelled_at' => now()]);
        $codes = $this->integrityCodes($refund, $admin);
        $this->assertContains('attempt_count', $codes);
        $this->assertContains('attempt_state', $codes);
        DB::table('refund_requests')->where('id', $refund->id)->update(['status' => 'paid']);
        $codes = $this->integrityCodes($refund, $admin);
        $this->assertContains('dispute_state', $codes);
        $this->assertContains('ledger_count', $codes);
        $this->assertContains('attempt_count', $codes);
    }

    public function test_integrity_report_handles_zero_net_and_rejects_unaccepted_source_payment(): void
    {
        [$refund, , $admin] = $this->scenario(100);
        $this->prepare($refund, $admin);
        $proof = $this->proof('integrity-zero');
        app(RefundTransferService::class)->recordPaid($refund, $admin, $this->receipt($refund, $proof), $proof);
        $this->assertSame([], $this->integrityCodes($refund, $admin));
        DB::table('payments')->where('id', $refund->saleEntry->payment_id)->update(['status' => 'rejected']);
        $this->assertContains('refund_source', $this->integrityCodes($refund, $admin));
        DB::table('payments')->where('id', $refund->saleEntry->payment_id)->update(['status' => 'accepted']);
        DB::table('ledger_entries')->where('id', $refund->sale_entry_id)->update(['gross_amount' => 10001]);
        $this->assertContains('refund_source', $this->integrityCodes($refund, $admin));
    }

    public function test_integrity_report_requires_payment_permission_for_http_and_direct_service_calls(): void
    {
        [$refund, $customer, $admin] = $this->scenario();
        $this->get(route('admin.refund-transfers.check', $refund))->assertRedirect(route('login'));
        $this->actingAs($customer)->get(route('admin.refund-transfers.check', $refund))->assertForbidden();
        $role = Role::create(['name' => 'Review only for integrity', 'slug' => 'integrity-reviewer']);
        $role->permissions()->attach(Permission::where('slug', 'refunds.review')->sole());
        $customer->assignRole($role);
        $this->actingAs($customer)->get(route('admin.refund-transfers.check', $refund))->assertForbidden();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Services\RefundIntegrityCheck::class)->inspect($refund, $customer);
    }

    public function test_dashboard_tracks_refund_work_without_counting_cancelled_attempts_as_new_requests(): void
    {
        [$refund, , $admin] = $this->scenario();
        $dashboard = app(\App\Services\AdminDashboardService::class);
        $metrics = fn ($key) => collect($dashboard->data($admin)['sections'])->firstWhere('key', $key)['metrics'];
        $this->assertSame([1, 0], array_values($metrics('transfers')));
        $this->assertSame([0], array_values($metrics('destinations')));
        $this->assertSame([1], array_values($metrics('settlements')));
        $this->assertSame([0], array_values($metrics('deliveries')));
        $attempt = $this->prepare($refund, $admin);
        $this->assertSame([0, 1], array_values($metrics('transfers')));
        app(RefundTransferService::class)->cancelPreparation($attempt, $admin, $this->cancellation($refund));
        $this->assertSame([1, 0], array_values($metrics('transfers')));
        $this->prepare($refund, $admin);
        $proof = $this->proof('dashboard');
        app(RefundTransferService::class)->recordPaid($refund, $admin, $this->receipt($refund, $proof), $proof);
        $this->assertSame([0, 0], array_values($metrics('transfers')));
        $this->assertSame([0], array_values($metrics('settlements')));
        $this->assertDatabaseCount('ledger_entries', 5);
    }

    private function integrityCodes(RefundRequest $refund, User $admin): array
    {
        return array_column(app(\App\Services\RefundIntegrityCheck::class)->inspect($refund, $admin)['issues'], 'code');
    }

    private function cancellation(RefundRequest $refund): array
    {
        return ['lock_version' => $refund->fresh()->lock_version, 'not_sent_confirmed' => 1,
            'cancellation_reason' => 'Confirmed with operator: no transfer was submitted.', 'current_password' => 'password'];
    }

    private function preparation(RefundRequest $refund): array
    {
        $refund = $refund->fresh();
        return ['lock_version' => $refund->lock_version, 'destination_id' => $refund->activeDestination->id,
            'destination_version' => $refund->activeDestination->lock_version, 'current_password' => 'password'];
    }

    private function proof(string $marker = 'refund'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('refund.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n% {$marker}\n%%EOF\n");
    }

    private function prepare(RefundRequest $refund, User $admin): RefundTransfer
    {
        $data = $this->preparation($refund);
        return app(RefundTransferService::class)->prepare($refund, $admin, $data['lock_version'], $data['destination_id'], $data['destination_version']);
    }

    private function receipt(RefundRequest $refund, UploadedFile $proof): array
    {
        return ['amount' => '120.00', 'transaction_reference' => 'bank-rfd-123', 'transferred_at' => now()->toDateTimeString(),
            'transfer_id' => $refund->fresh()->transfer->id,
            'lock_version' => $refund->fresh()->lock_version, 'transfer_confirmed' => 1, 'current_password' => 'password', 'proof' => $proof];
    }

    private function approveAndVerify(RefundRequest $refund, User $customer, User $admin): void
    {
        app(RefundReviewService::class)->review($refund, $admin, RefundStatus::Approved, 'Approved full refund', 0);
        $destination = app(RefundDestinationService::class)->submit($refund, $customer, [
            'type' => 'bank_account', 'provider_name' => 'Private Provider', 'account_name' => 'Private Recipient',
            'account_identifier' => 'PS123456789101234',
        ], 1);
        app(RefundDestinationService::class)->review($destination, $admin, RefundDestinationStatus::Verified, 'Independent ownership check', 0, true);
    }

    private function scenario(int $commission = 5, string $currency = 'ILS'): array
    {
        $customer = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $merchant = Merchant::query()->create([
            'user_id' => User::factory()->create()->id, 'legal_name' => 'Refund merchant',
            'identity_number' => 'RFT-'.$customer->id, 'phone' => '0599000000', 'date_of_birth' => '1990-01-01',
            'address' => 'Private merchant address', 'business_type' => 'Retail', 'verification_status' => 'verified',
        ]);
        $order = Order::query()->create([
            'user_id' => $customer->id, 'subtotal' => 100, 'shipping_fee' => 20, 'total' => 120,
            'currency' => $currency, 'status' => 'confirmed', 'payment_method' => 'manual_transfer', 'payment_status' => 'paid',
        ]);
        $suborder = MerchantOrder::query()->create([
            'order_id' => $order->id, 'merchant_id' => $merchant->id, 'group_key' => 'merchant:'.$merchant->id,
            'status' => 'confirmed', 'product_subtotal' => 100, 'delivery_fee' => 20, 'service_fee' => 0,
            'commission_amount' => $commission, 'total' => 120, 'currency' => $currency,
        ]);
        $payment = Payment::query()->create([
            'order_id' => $order->id, 'user_id' => $customer->id, 'order_no' => (string) $order->id,
            'amount' => 12000, 'currency' => $currency, 'status' => 'accepted', 'provider' => 'manual',
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
        $this->approveAndVerify($refund, $customer, $admin);

        return [$refund->fresh(), $customer, $admin];
    }
}
