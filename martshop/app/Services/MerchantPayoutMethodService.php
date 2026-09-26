<?php

namespace App\Services;

use App\Enums\MerchantPayoutMethodStatus;
use App\Enums\MerchantVerificationStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Merchant;
use App\Models\MerchantPayoutMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MerchantPayoutMethodService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function register(Merchant $merchant, array $data, User $actor): MerchantPayoutMethod
    {
        return DB::transaction(function () use ($merchant, $data, $actor) {
            $lockedMerchant = Merchant::query()->whereKey($merchant->id)->lockForUpdate()->firstOrFail();
            if ($lockedMerchant->user_id !== $actor->id) {
                abort(403);
            }
            if ($lockedMerchant->verification_status !== MerchantVerificationStatus::Verified) {
                throw ValidationException::withMessages([
                    'payout_method' => 'يجب توثيق حساب التاجر قبل تسجيل وسيلة استلام.',
                ]);
            }

            $identifier = MerchantPayoutMethod::normalizeIdentifier($data['account_identifier']);
            $hash = hash('sha256', mb_strtolower($identifier));
            $duplicate = MerchantPayoutMethod::query()
                ->where('merchant_id', $lockedMerchant->id)
                ->where('account_identifier_hash', $hash)
                ->whereIn('status', [
                    MerchantPayoutMethodStatus::Pending->value,
                    MerchantPayoutMethodStatus::Verified->value,
                ])
                ->lockForUpdate()
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'account_identifier' => 'وسيلة الاستلام هذه مسجلة أو قيد التحقق بالفعل.',
                ]);
            }

            $method = MerchantPayoutMethod::query()->create([
                'merchant_id' => $lockedMerchant->id,
                'type' => $data['type'],
                'provider_name' => trim($data['provider_name']),
                'account_name' => trim($data['account_name']),
                'account_identifier' => $identifier,
                'status' => MerchantPayoutMethodStatus::Pending,
                'submitted_at' => now(),
            ]);
            $this->audit->record('payout_method.registered', $method, null, $this->snapshot($method));

            return $method;
        }, 3);
    }

    public function review(
        MerchantPayoutMethod $method,
        MerchantPayoutMethodStatus $decision,
        User $actor,
        ?string $notes,
        int $lockVersion,
    ): MerchantPayoutMethod {
        return DB::transaction(function () use ($method, $decision, $actor, $notes, $lockVersion) {
            $lockedMerchant = Merchant::query()->whereKey($method->merchant_id)->lockForUpdate()->firstOrFail();
            $locked = MerchantPayoutMethod::query()->whereKey($method->id)->lockForUpdate()->firstOrFail();
            if ($lockedMerchant->user_id === $actor->id) {
                throw ValidationException::withMessages([
                    'payout_method' => 'لا يمكن لمراجع السحوبات مراجعة وسيلة استلام تخص حسابه التجاري.',
                ]);
            }
            if ($locked->status !== MerchantPayoutMethodStatus::Pending) {
                if ($locked->status === $decision) {
                    return $locked;
                }
                throw ValidationException::withMessages(['payout_method' => 'تمت مراجعة وسيلة الاستلام مسبقًا.']);
            }
            if ($locked->lock_version !== $lockVersion) {
                throw ValidationException::withMessages(['lock_version' => 'تم تحديث وسيلة الاستلام؛ حدّث الصفحة.']);
            }

            $before = $this->snapshot($locked);
            $locked->update([
                'status' => $decision,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->audit->record('payout_method.reviewed', $locked, $before, $this->snapshot($locked), $notes);

            return $locked->refresh();
        }, 3);
    }

    public function disable(MerchantPayoutMethod $method, User $actor): MerchantPayoutMethod
    {
        return DB::transaction(function () use ($method, $actor) {
            $locked = MerchantPayoutMethod::query()->whereKey($method->id)->lockForUpdate()->firstOrFail();
            if ($locked->merchant->user_id !== $actor->id) {
                abort(403);
            }
            if ($locked->status === MerchantPayoutMethodStatus::Disabled) {
                return $locked;
            }
            if ($locked->withdrawals()->whereIn('status', [
                WithdrawalStatus::Requested->value,
                WithdrawalStatus::Approved->value,
            ])->exists()) {
                throw ValidationException::withMessages([
                    'payout_method' => 'لا يمكن تعطيل وسيلة مرتبطة بطلب سحب مفتوح.',
                ]);
            }

            $before = $this->snapshot($locked);
            $locked->update([
                'status' => MerchantPayoutMethodStatus::Disabled,
                'disabled_at' => now(),
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->audit->record('payout_method.disabled', $locked, $before, $this->snapshot($locked));

            return $locked->refresh();
        }, 3);
    }

    private function snapshot(MerchantPayoutMethod $method): array
    {
        return [
            'merchant_id' => $method->merchant_id,
            'type' => $method->type,
            'provider_name' => $method->provider_name,
            'account_name' => $method->account_name,
            'masked_identifier' => $method->maskedIdentifier(),
            'status' => $method->status->value,
            'lock_version' => $method->lock_version,
        ];
    }
}
