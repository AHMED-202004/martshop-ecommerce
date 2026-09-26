<?php

namespace App\Services;

use App\Enums\MerchantDocumentStatus;
use App\Enums\MerchantDocumentType;
use App\Enums\MerchantVerificationStatus;
use App\Models\Merchant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MerchantRegistrationService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function saveProfile(User $user, array $data): Merchant
    {
        return DB::transaction(function () use ($user, $data) {
            $merchant = Merchant::where('user_id', $user->id)->lockForUpdate()->first();
            if ($merchant) {
                abort_unless($user->can('update', $merchant), 403);
            }
            $before = $merchant ? $this->auditSnapshot($merchant) : null;

            if ($merchant) {
                $merchant->update($data);
            } else {
                $merchant = Merchant::create(array_merge($data, [
                    'user_id' => $user->id,
                    'verification_status' => MerchantVerificationStatus::Incomplete,
                ]));
            }

            if ($role = Role::where('slug', 'merchant')->first()) {
                $user->assignRole($role);
            }

            $this->audit->record(
                $before ? 'merchant.profile_updated' : 'merchant.profile_created',
                $merchant,
                $before,
                $this->auditSnapshot($merchant),
            );

            return $merchant;
        });
    }

    public function submit(Merchant $merchant, User $actor): Merchant
    {
        return DB::transaction(function () use ($merchant, $actor) {
            $merchant = Merchant::whereKey($merchant->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->can('submit', $merchant), 403);
            $missing = [];

            foreach ($this->requiredDocumentTypes() as $type) {
                $latest = $merchant->documents()->where('type', $type->value)->latest('id')->first();
                if (!$latest || $latest->status === MerchantDocumentStatus::Rejected) {
                    $missing[] = $type->value;
                }
            }

            if ($missing) {
                throw ValidationException::withMessages([
                    'documents' => 'يجب رفع المستندات المطلوبة قبل إرسال الطلب: '.implode(', ', $missing),
                ]);
            }

            $before = $this->auditSnapshot($merchant);
            $merchant->update([
                'verification_status' => MerchantVerificationStatus::PendingReview,
                'submitted_at' => now(),
                'reviewed_at' => null,
                'reviewed_by' => null,
                'review_notes' => null,
            ]);

            $this->audit->record(
                'merchant.submitted_for_review',
                $merchant,
                $before,
                $this->auditSnapshot($merchant),
            );

            return $merchant;
        });
    }

    public function requiredDocumentTypes(): array
    {
        return [
            MerchantDocumentType::IdentityFront,
            MerchantDocumentType::IdentityBack,
            MerchantDocumentType::PersonalPhoto,
        ];
    }

    private function auditSnapshot(Merchant $merchant): array
    {
        return [
            'legal_name' => $merchant->legal_name,
            'location_id' => $merchant->location_id,
            'business_type' => $merchant->business_type,
            'verification_status' => $merchant->verification_status->value,
        ];
    }
}
