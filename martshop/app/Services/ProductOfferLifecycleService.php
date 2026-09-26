<?php

namespace App\Services;

use App\Enums\MerchantVerificationStatus;
use App\Enums\ProductOfferStatus;
use App\Enums\ProductStatus;
use App\Models\Merchant;
use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductOfferLifecycleService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function update(ProductOffer $offer, Merchant $merchant, User $actor, array $data): ProductOffer
    {
        return DB::transaction(function () use ($offer, $merchant, $actor, $data) {
            $this->ensureMerchantVerified($merchant, $actor);
            $offer = ProductOffer::query()
                ->with('product')
                ->whereKey($offer->id)
                ->where('merchant_id', $merchant->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($actor->can('update', $offer), 403);
            $this->ensureVersion($offer, (int) $data['lock_version']);

            if ($offer->status === ProductOfferStatus::Rejected) {
                throw ValidationException::withMessages([
                    'offer' => 'العرض المرفوض يجب أن تعيد الإدارة فتحه قبل تعديله.',
                ]);
            }

            $before = $this->snapshot($offer);
            $resubmitting = $offer->status === ProductOfferStatus::ChangesRequested;
            $offer->update([
                'price' => round((float) $data['price'], 2),
                'compare_at_price' => isset($data['compare_at_price'])
                    ? round((float) $data['compare_at_price'], 2)
                    : null,
                'stock' => (int) $data['stock'],
                'location_id' => $data['location_id'],
                'preparation_time_days' => (int) $data['preparation_time_days'],
                'warranty' => $data['warranty'] ?? null,
                'status' => $resubmitting ? ProductOfferStatus::PendingReview : $offer->status,
                'submitted_at' => $resubmitting ? now() : $offer->submitted_at,
                'reviewed_at' => $resubmitting ? null : $offer->reviewed_at,
                'reviewed_by' => $resubmitting ? null : $offer->reviewed_by,
                'review_notes' => $resubmitting ? null : $offer->review_notes,
                'last_confirmed_at' => $offer->status === ProductOfferStatus::Active ? now() : $offer->last_confirmed_at,
                'lock_version' => $offer->lock_version + 1,
            ]);

            $this->syncVariants(
                $offer,
                $this->sizes($data['variant_sizes'] ?? null),
                $data['variant_color'] ?? null,
            );

            $this->audit->record(
                $resubmitting ? 'catalog.offer_resubmitted' : 'catalog.offer_operational_updated',
                $offer,
                $before,
                $this->snapshot($offer->fresh()),
            );

            return $offer->fresh(['product', 'location', 'variants']);
        });
    }

    public function pause(
        ProductOffer $offer,
        Merchant $merchant,
        User $actor,
        int $lockVersion,
        ?string $reason,
    ): ProductOffer {
        return DB::transaction(function () use ($offer, $merchant, $actor, $lockVersion, $reason) {
            $this->ensureMerchantVerified($merchant, $actor);
            $offer = ProductOffer::query()
                ->whereKey($offer->id)
                ->where('merchant_id', $merchant->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($actor->can('pause', $offer), 403);
            $this->ensureVersion($offer, $lockVersion);
            if ($offer->status !== ProductOfferStatus::Active) {
                throw ValidationException::withMessages(['offer' => 'يمكن إيقاف العرض النشط فقط.']);
            }

            $before = $this->snapshot($offer);
            $offer->update([
                'status' => ProductOfferStatus::Paused,
                'paused_at' => now(),
                'paused_by' => $actor->id,
                'pause_reason' => 'merchant_requested',
                'lock_version' => $offer->lock_version + 1,
            ]);
            $this->audit->record(
                'catalog.offer_paused_by_merchant',
                $offer,
                $before,
                $this->snapshot($offer),
                $reason,
            );

            return $offer;
        });
    }

    public function resume(
        ProductOffer $offer,
        Merchant $merchant,
        User $actor,
        int $lockVersion,
    ): ProductOffer {
        return DB::transaction(function () use ($offer, $merchant, $actor, $lockVersion) {
            $this->ensureMerchantVerified($merchant, $actor);
            $offer = ProductOffer::query()
                ->with('product')
                ->whereKey($offer->id)
                ->where('merchant_id', $merchant->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($actor->can('resume', $offer), 403);
            $this->ensureVersion($offer, $lockVersion);
            if ($offer->status !== ProductOfferStatus::Paused
                || $offer->paused_by !== $actor->id
                || $offer->pause_reason !== 'merchant_requested') {
                throw ValidationException::withMessages([
                    'offer' => 'لا يمكنك استئناف عرض أوقفته الإدارة أو أوقفه النظام.',
                ]);
            }
            if ($offer->product->status !== ProductStatus::Active) {
                throw ValidationException::withMessages(['offer' => 'لا يمكن الاستئناف قبل تفعيل المنتج.']);
            }

            $before = $this->snapshot($offer);
            $offer->update([
                'status' => ProductOfferStatus::Active,
                'paused_at' => null,
                'paused_by' => null,
                'pause_reason' => null,
                'last_confirmed_at' => now(),
                'lock_version' => $offer->lock_version + 1,
            ]);
            $this->audit->record(
                'catalog.offer_resumed_by_merchant',
                $offer,
                $before,
                $this->snapshot($offer),
            );

            return $offer;
        });
    }

    private function ensureMerchantVerified(Merchant $merchant, User $actor): void
    {
        $merchant = Merchant::query()->whereKey($merchant->id)->lockForUpdate()->firstOrFail();
        abort_unless($merchant->user_id === $actor->id, 403);
        if ($merchant->verification_status !== MerchantVerificationStatus::Verified) {
            throw ValidationException::withMessages([
                'merchant' => 'لا تملك صلاحية تعديل هذا العرض.',
            ]);
        }
    }

    private function ensureVersion(ProductOffer $offer, int $expectedVersion): void
    {
        if ($offer->lock_version !== $expectedVersion) {
            throw ValidationException::withMessages([
                'lock_version' => 'تم تحديث العرض من جلسة أخرى. أعد تحميل الصفحة ثم حاول مجددًا.',
            ]);
        }
    }

    private function syncVariants(ProductOffer $offer, array $sizes, ?string $color): void
    {
        $activeKeys = [];
        foreach ($sizes as $size) {
            $sourceKey = 'size:'.sha1($size);
            $activeKeys[] = $sourceKey;
            $offer->variants()->updateOrCreate(
                ['source_key' => $sourceKey],
                [
                    'attributes' => array_filter(['size' => $size, 'color' => $color]),
                    'price' => null,
                    'stock' => null,
                    'status' => 'active',
                ]
            );
        }

        $offer->variants()
            ->when($activeKeys, fn ($query) => $query->whereNotIn('source_key', $activeKeys))
            ->where('status', 'active')
            ->update(['status' => 'inactive']);
    }

    private function sizes(?string $rawSizes): array
    {
        if (! $rawSizes) {
            return [];
        }

        return collect(preg_split('/[,،\r\n]+/u', $rawSizes))
            ->map(fn (string $size) => trim($size))
            ->filter()
            ->unique()
            ->take(100)
            ->values()
            ->all();
    }

    private function snapshot(ProductOffer $offer): array
    {
        return [
            'status' => $offer->status->value,
            'price' => (float) $offer->price,
            'compare_at_price' => $offer->compare_at_price !== null ? (float) $offer->compare_at_price : null,
            'stock' => $offer->stock,
            'location_id' => $offer->location_id,
            'preparation_time_days' => $offer->preparation_time_days,
            'lock_version' => $offer->lock_version,
            'paused_by' => $offer->paused_by,
            'pause_reason' => $offer->pause_reason,
        ];
    }
}
