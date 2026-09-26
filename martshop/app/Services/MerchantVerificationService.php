<?php

namespace App\Services;

use App\Enums\MerchantDocumentStatus;
use App\Enums\MerchantVerificationStatus;
use App\Enums\ProductOfferStatus;
use App\Models\Merchant;
use App\Models\MerchantDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MerchantVerificationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly MerchantRegistrationService $registration,
    ) {
    }

    public function reviewDocument(
        MerchantDocument $document,
        MerchantDocumentStatus $status,
        User $reviewer,
        ?string $notes,
    ): MerchantDocument {
        abort_unless($reviewer->can('review', $document), 403);

        return DB::transaction(function () use ($document, $status, $reviewer, $notes) {
            $document = MerchantDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $before = ['status' => $document->status->value, 'review_notes' => $document->review_notes];
            $document->update([
                'status' => $status,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);
            $this->audit->record('merchant.document_reviewed', $document, $before, [
                'status' => $document->status->value,
                'review_notes' => $document->review_notes,
            ]);

            return $document;
        });
    }

    public function approve(Merchant $merchant, User $reviewer): Merchant
    {
        $this->authorizeVerification($merchant, $reviewer);

        return DB::transaction(function () use ($merchant, $reviewer) {
            $merchant = Merchant::whereKey($merchant->id)->lockForUpdate()->firstOrFail();
            $this->ensurePending($merchant);

            $notAccepted = [];
            foreach ($this->registration->requiredDocumentTypes() as $type) {
                $latest = $merchant->documents()->where('type', $type->value)->latest('id')->first();
                if (!$latest || $latest->status !== MerchantDocumentStatus::Accepted) {
                    $notAccepted[] = $type->value;
                }
            }
            if ($notAccepted) {
                throw ValidationException::withMessages([
                    'documents' => 'لا يمكن الاعتماد قبل قبول المستندات المطلوبة: '.implode(', ', $notAccepted),
                ]);
            }

            return $this->transition($merchant, MerchantVerificationStatus::Verified, $reviewer, null, 'merchant.verified');
        });
    }

    public function requestChanges(Merchant $merchant, User $reviewer, string $reason): Merchant
    {
        $this->authorizeVerification($merchant, $reviewer);

        return DB::transaction(function () use ($merchant, $reviewer, $reason) {
            $merchant = Merchant::whereKey($merchant->id)->lockForUpdate()->firstOrFail();
            $this->ensurePending($merchant);

            return $this->transition($merchant, MerchantVerificationStatus::ChangesRequested, $reviewer, $reason, 'merchant.changes_requested');
        });
    }

    public function reject(Merchant $merchant, User $reviewer, string $reason): Merchant
    {
        $this->authorizeVerification($merchant, $reviewer);

        return DB::transaction(function () use ($merchant, $reviewer, $reason) {
            $merchant = Merchant::whereKey($merchant->id)->lockForUpdate()->firstOrFail();
            $this->ensurePending($merchant);

            return $this->transition($merchant, MerchantVerificationStatus::Rejected, $reviewer, $reason, 'merchant.rejected');
        });
    }

    public function suspend(Merchant $merchant, User $reviewer, string $reason): Merchant
    {
        $this->authorizeVerification($merchant, $reviewer);

        return DB::transaction(function () use ($merchant, $reviewer, $reason) {
            $merchant = Merchant::whereKey($merchant->id)->lockForUpdate()->firstOrFail();

            $merchant = $this->transition(
                $merchant,
                MerchantVerificationStatus::Suspended,
                $reviewer,
                $reason,
                'merchant.suspended',
            );

            $offers = $merchant->offers()
                ->where('status', ProductOfferStatus::Active->value)
                ->lockForUpdate()
                ->get();
            foreach ($offers as $offer) {
                $before = ['status' => $offer->status->value];
                $offer->update([
                    'status' => ProductOfferStatus::Paused,
                    'paused_at' => now(),
                    'paused_by' => $reviewer->id,
                    'pause_reason' => 'merchant_suspended',
                    'lock_version' => $offer->lock_version + 1,
                ]);
                $this->audit->record(
                    'catalog.offer_paused',
                    $offer,
                    $before,
                    ['status' => $offer->status->value],
                    $reason,
                    ['trigger' => 'merchant_suspended'],
                );
            }

            return $merchant;
        });
    }

    private function ensurePending(Merchant $merchant): void
    {
        if ($merchant->verification_status !== MerchantVerificationStatus::PendingReview) {
            throw ValidationException::withMessages(['merchant' => 'طلب التاجر ليس قيد المراجعة.']);
        }
    }

    private function authorizeVerification(Merchant $merchant, User $reviewer): void
    {
        abort_unless($reviewer->can('verify', $merchant), 403);
    }

    private function transition(
        Merchant $merchant,
        MerchantVerificationStatus $status,
        User $reviewer,
        ?string $reason,
        string $action,
    ): Merchant {
        $before = ['verification_status' => $merchant->verification_status->value];
        $merchant->update([
            'verification_status' => $status,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $reason,
        ]);
        $this->audit->record($action, $merchant, $before, [
            'verification_status' => $status->value,
        ], $reason);

        return $merchant;
    }
}
