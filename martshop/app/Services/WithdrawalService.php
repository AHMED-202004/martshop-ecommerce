<?php

namespace App\Services;

use App\Enums\LedgerStatus;
use App\Enums\MerchantPayoutMethodStatus;
use App\Enums\MerchantVerificationStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Merchant;
use App\Models\MerchantPayoutMethod;
use App\Models\User;
use App\Models\WithdrawalProof;
use App\Models\WithdrawalRequest;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class WithdrawalService
{
    public function __construct(
        private readonly MarketplaceSettings $money,
        private readonly WithdrawalPolicyService $policies,
        private readonly MerchantLedgerService $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function request(
        Merchant $merchant,
        MerchantPayoutMethod $payoutMethod,
        array $data,
        User $actor,
    ): WithdrawalRequest {
        $idempotencyKey = (string) $data['idempotency_key'];
        if ($existing = $this->existing($merchant->id, $idempotencyKey)) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($merchant, $payoutMethod, $data, $actor, $idempotencyKey) {
                $lockedMerchant = Merchant::query()->whereKey($merchant->id)->lockForUpdate()->firstOrFail();
                if ($lockedMerchant->user_id !== $actor->id) {
                    abort(403);
                }
                if ($lockedMerchant->verification_status !== MerchantVerificationStatus::Verified) {
                    throw ValidationException::withMessages(['withdrawal' => 'حساب التاجر غير موثق للسحب.']);
                }

                $policy = $this->policies->policy();
                if (! $policy['enabled']) {
                    throw ValidationException::withMessages(['withdrawal' => 'طلبات السحب متوقفة مؤقتًا.']);
                }
                $method = MerchantPayoutMethod::query()->whereKey($payoutMethod->id)->lockForUpdate()->firstOrFail();
                if ($method->merchant_id !== $lockedMerchant->id
                    || $method->status !== MerchantPayoutMethodStatus::Verified) {
                    throw ValidationException::withMessages(['merchant_payout_method_id' => 'وسيلة الاستلام غير موثقة أو لا تخصك.']);
                }

                if ($existing = WithdrawalRequest::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first()) {
                    if ($existing->merchant_id !== $lockedMerchant->id) {
                        throw ValidationException::withMessages(['idempotency_key' => 'مفتاح الطلب غير صالح.']);
                    }
                    return $existing;
                }

                $amount = $this->money->decimalToMinorUnits($data['amount']);
                $this->validatePolicy($lockedMerchant, $amount, $policy);
                $available = $this->ledger->bucketBalance(
                    $lockedMerchant->id,
                    LedgerStatus::Available,
                    'ILS',
                    true,
                );
                if ($available < $amount) {
                    throw ValidationException::withMessages(['amount' => 'المبلغ أكبر من الرصيد المتاح للسحب.']);
                }

                $withdrawal = WithdrawalRequest::query()->create([
                    'merchant_id' => $lockedMerchant->id,
                    'merchant_payout_method_id' => $method->id,
                    'amount' => $amount,
                    'currency' => 'ILS',
                    'status' => WithdrawalStatus::Requested,
                    'reference' => 'WDR-'.Str::upper((string) Str::ulid()),
                    'idempotency_key' => $idempotencyKey,
                    'destination_snapshot' => [
                        'payout_method_id' => $method->id,
                        'type' => $method->type,
                        'provider_name' => $method->provider_name,
                        'account_name' => $method->account_name,
                        'masked_identifier' => $method->maskedIdentifier(),
                        'verified_at' => $method->reviewed_at?->toIso8601String(),
                    ],
                    'requested_at' => now(),
                ]);
                $this->ledger->holdWithdrawal($withdrawal, $actor);
                $this->audit->record('withdrawal.requested', $withdrawal, null, $this->snapshot($withdrawal));

                return $withdrawal->load('payoutMethod');
            }, 3);
        } catch (QueryException $exception) {
            if ($existing = $this->existing($merchant->id, $idempotencyKey)) {
                return $existing;
            }
            throw $exception;
        }
    }

    public function review(
        WithdrawalRequest $withdrawal,
        WithdrawalStatus $decision,
        User $actor,
        ?string $notes,
        int $lockVersion,
    ): WithdrawalRequest {
        abort_unless($actor->hasPermission('withdrawals.approve'), 403);

        return DB::transaction(function () use ($withdrawal, $decision, $actor, $notes, $lockVersion) {
            $lockedMerchant = Merchant::query()->whereKey($withdrawal->merchant_id)->lockForUpdate()->firstOrFail();
            $locked = WithdrawalRequest::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            if ($lockedMerchant->user_id === $actor->id) {
                throw ValidationException::withMessages([
                    'withdrawal' => 'لا يمكن لمراجع السحوبات اتخاذ قرار على طلب يخص حسابه التجاري.',
                ]);
            }
            if ($locked->status !== WithdrawalStatus::Requested) {
                if ($locked->status === $decision) {
                    return $locked;
                }
                throw ValidationException::withMessages(['withdrawal' => 'تمت مراجعة طلب السحب مسبقًا.']);
            }
            if ($locked->lock_version !== $lockVersion) {
                throw ValidationException::withMessages(['lock_version' => 'تم تحديث طلب السحب؛ حدّث الصفحة.']);
            }

            $before = $this->snapshot($locked);
            if ($decision === WithdrawalStatus::Rejected) {
                $this->ledger->releaseWithdrawal($locked, $actor);
            }
            $locked->update([
                'status' => $decision,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
                'approved_at' => $decision === WithdrawalStatus::Approved ? now() : null,
                'rejected_at' => $decision === WithdrawalStatus::Rejected ? now() : null,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->audit->record('withdrawal.reviewed', $locked, $before, $this->snapshot($locked), $notes);

            return $locked->refresh();
        }, 3);
    }

    public function markPaid(
        WithdrawalRequest $withdrawal,
        array $data,
        UploadedFile $proof,
        User $actor,
    ): WithdrawalRequest {
        abort_unless($actor->hasPermission('withdrawals.approve'), 403);

        $storedPath = null;
        try {
            return DB::transaction(function () use ($withdrawal, $data, $proof, $actor, &$storedPath) {
                $lockedMerchant = Merchant::query()->whereKey($withdrawal->merchant_id)->lockForUpdate()->firstOrFail();
                $locked = WithdrawalRequest::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
                if ($lockedMerchant->user_id === $actor->id) {
                    throw ValidationException::withMessages([
                        'withdrawal' => 'لا يمكن لمراجع السحوبات تسجيل تحويل لطلب يخص حسابه التجاري.',
                    ]);
                }
                if ($locked->status === WithdrawalStatus::Paid) {
                    return $locked;
                }
                if ($locked->status !== WithdrawalStatus::Approved) {
                    throw ValidationException::withMessages(['withdrawal' => 'يجب اعتماد طلب السحب قبل تسجيل التحويل.']);
                }
                if ($locked->lock_version !== (int) $data['lock_version']) {
                    throw ValidationException::withMessages(['lock_version' => 'تم تحديث طلب السحب؛ حدّث الصفحة.']);
                }

                $transactionReference = mb_strtoupper(trim($data['transaction_reference']));
                if (WithdrawalRequest::query()
                    ->where('transaction_reference', $transactionReference)
                    ->whereKeyNot($locked->id)
                    ->exists()) {
                    throw ValidationException::withMessages(['transaction_reference' => 'مرجع التحويل مستخدم مسبقًا.']);
                }

                $fileName = Str::uuid().'.'.($proof->guessExtension() ?: 'bin');
                $storedPath = $proof->storeAs('withdrawal-proofs/'.$locked->id, $fileName, 'local');
                if (! $storedPath) {
                    throw new RuntimeException('Withdrawal proof could not be stored.');
                }
                $storedFile = Storage::disk('local')->path($storedPath);
                $storedSize = is_file($storedFile) ? filesize($storedFile) : false;
                $storedHash = is_file($storedFile) ? hash_file('sha256', $storedFile) : false;
                $sourceHash = hash_file('sha256', $proof->getRealPath());
                if (! is_int($storedSize) || $storedSize < 1 || ! is_string($storedHash)
                    || ! is_string($sourceHash) || ! hash_equals($sourceHash, $storedHash)) {
                    throw new RuntimeException('Withdrawal proof integrity verification failed.');
                }
                WithdrawalProof::query()->create([
                    'withdrawal_request_id' => $locked->id,
                    'disk' => 'local',
                    'path' => $storedPath,
                    'original_name' => $proof->getClientOriginalName(),
                    'mime_type' => $proof->getMimeType(),
                    'size' => $storedSize,
                    'sha256' => $storedHash,
                    'uploaded_by' => $actor->id,
                    'created_at' => now(),
                ]);

                $before = $this->snapshot($locked);
                $this->ledger->payWithdrawal($locked, $actor);
                $locked->update([
                    'status' => WithdrawalStatus::Paid,
                    'paid_at' => now(),
                    'transferred_at' => $data['transferred_at'],
                    'transaction_reference' => $transactionReference,
                    'lock_version' => $locked->lock_version + 1,
                ]);
                $this->audit->record('withdrawal.paid', $locked, $before, $this->snapshot($locked), metadata: [
                    'withdrawal_proof_id' => $locked->proof()->value('id'),
                ]);

                return $locked->refresh()->load(['proof', 'payoutMethod']);
            }, 3);
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            if ($exception instanceof QueryException
                && WithdrawalRequest::query()->where('transaction_reference', mb_strtoupper(trim($data['transaction_reference'])))->exists()) {
                throw ValidationException::withMessages(['transaction_reference' => 'مرجع التحويل مستخدم مسبقًا.']);
            }
            throw $exception;
        }
    }

    private function validatePolicy(Merchant $merchant, int $amount, array $policy): void
    {
        if ($amount < $policy['minimum_minor']) {
            throw ValidationException::withMessages(['amount' => 'المبلغ أقل من الحد الأدنى للسحب.']);
        }
        if ($amount > $policy['maximum_minor']) {
            throw ValidationException::withMessages(['amount' => 'المبلغ أكبر من الحد الأعلى للطلب الواحد.']);
        }

        $countedStatuses = [
            WithdrawalStatus::Requested->value,
            WithdrawalStatus::Approved->value,
            WithdrawalStatus::Paid->value,
        ];
        $daily = (int) $merchant->withdrawalRequests()
            ->whereIn('status', $countedStatuses)
            ->where('requested_at', '>=', now()->startOfDay())
            ->sum('amount');
        if ($daily + $amount > $policy['daily_limit_minor']) {
            throw ValidationException::withMessages(['amount' => 'سيؤدي الطلب إلى تجاوز حد السحب اليومي.']);
        }
        $weekly = (int) $merchant->withdrawalRequests()
            ->whereIn('status', $countedStatuses)
            ->where('requested_at', '>=', now()->startOfWeek())
            ->sum('amount');
        if ($weekly + $amount > $policy['weekly_limit_minor']) {
            throw ValidationException::withMessages(['amount' => 'سيؤدي الطلب إلى تجاوز حد السحب الأسبوعي.']);
        }
    }

    private function existing(int $merchantId, string $idempotencyKey): ?WithdrawalRequest
    {
        return WithdrawalRequest::query()
            ->where('merchant_id', $merchantId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function snapshot(WithdrawalRequest $withdrawal): array
    {
        return [
            'merchant_id' => $withdrawal->merchant_id,
            'amount' => $withdrawal->amount,
            'currency' => $withdrawal->currency,
            'status' => $withdrawal->status->value,
            'reference' => $withdrawal->reference,
            'destination' => $withdrawal->destination_snapshot,
            'transaction_reference' => $withdrawal->transaction_reference,
            'lock_version' => $withdrawal->lock_version,
        ];
    }
}
