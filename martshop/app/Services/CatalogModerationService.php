<?php

namespace App\Services;

use App\Enums\MerchantVerificationStatus;
use App\Enums\ProductOfferStatus;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CatalogModerationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ProductSubmissionImageStorage $images,
        private readonly CatalogReviewDecisionRecorder $decisions,
    ) {
    }

    public function reviewProduct(Product $product, ProductStatus $decision, User $reviewer, ?string $reason): Product
    {
        $publicImagePath = null;
        $privateImage = null;

        try {
            $result = DB::transaction(function () use (
                $product,
                $decision,
                $reviewer,
                $reason,
                &$publicImagePath,
                &$privateImage,
            ) {
                $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                abort_unless($reviewer->can('moderate', $product), 403);
                $this->ensureProductTransition($product->status, $decision);

                $updates = [
                    'status' => $decision,
                    'reviewed_at' => now(),
                    'reviewed_by' => $reviewer->id,
                    'review_notes' => $reason,
                ];
                if ($decision === ProductStatus::Active && $product->submission_image_path) {
                    $privateImage = $this->images->resolve($product);
                    if (! $privateImage) {
                        throw ValidationException::withMessages([
                            'decision' => 'تعذّر التحقق من سلامة صورة المنتج المرسلة.',
                        ]);
                    }
                    $contents = file_get_contents($privateImage['path']);
                    $publicImagePath = 'products/'.Str::uuid().'.'.$privateImage['extension'];
                    if (! is_string($contents) || ! Storage::disk('public')->put($publicImagePath, $contents)) {
                        throw ValidationException::withMessages([
                            'decision' => 'تعذّر نشر صورة المنتج المعتمدة.',
                        ]);
                    }
                    $updates += [
                        'image' => 'storage/'.$publicImagePath,
                        'submission_image_disk' => null,
                        'submission_image_path' => null,
                        'submission_image_size' => null,
                        'submission_image_sha256' => null,
                    ];
                }

                $before = $this->productSnapshot($product);
                $fromStatus = $product->status->value;
                $product->update($updates);
                $this->decisions->record($product, $reviewer, $fromStatus, $decision->value, $product->submitted_at);
                $this->audit->record(
                    'catalog.product_'.$decision->value,
                    $product,
                    $before,
                    $this->productSnapshot($product),
                    $reason,
                );

                if (in_array($decision, [ProductStatus::ChangesRequested, ProductStatus::Rejected], true)) {
                    $offerDecision = $decision === ProductStatus::Rejected
                        ? ProductOfferStatus::Rejected
                        : ProductOfferStatus::ChangesRequested;
                    $this->transitionRelatedOffers($product, $offerDecision, $reviewer, $reason);
                } elseif ($decision === ProductStatus::Hidden) {
                    $this->transitionRelatedOffers($product, ProductOfferStatus::Paused, $reviewer, $reason);
                }

                return $product;
            });
        } catch (Throwable $exception) {
            if ($publicImagePath) {
                Storage::disk('public')->delete($publicImagePath);
            }
            throw $exception;
        }

        if ($privateImage) {
            Storage::disk('local')->delete($privateImage['relative_path']);
        }

        return $result;
    }

    public function reviewOffer(ProductOffer $offer, ProductOfferStatus $decision, User $reviewer, ?string $reason): ProductOffer
    {
        return DB::transaction(function () use ($offer, $decision, $reviewer, $reason) {
            $offer = ProductOffer::query()
                ->with(['product', 'merchant'])
                ->whereKey($offer->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($reviewer->can('moderate', $offer), 403);
            $this->ensureOfferTransition($offer->status, $decision);

            if ($decision === ProductOfferStatus::Active) {
                if ($offer->product->status !== ProductStatus::Active) {
                    throw ValidationException::withMessages([
                        'decision' => 'يجب اعتماد المنتج قبل تفعيل عرضه.',
                    ]);
                }
                if (! $offer->merchant || $offer->merchant->verification_status !== MerchantVerificationStatus::Verified) {
                    throw ValidationException::withMessages([
                        'decision' => 'لا يمكن تفعيل عرض لتاجر غير موثق.',
                    ]);
                }
            }

            $before = $this->offerSnapshot($offer);
            $fromStatus = $offer->status->value;
            $isPaused = $decision === ProductOfferStatus::Paused;
            $isActive = $decision === ProductOfferStatus::Active;
            $offer->update([
                'status' => $decision,
                'last_confirmed_at' => $isActive ? now() : $offer->last_confirmed_at,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
                'review_notes' => $reason,
                'paused_at' => $isPaused ? now() : null,
                'paused_by' => $isPaused ? $reviewer->id : null,
                'pause_reason' => $isPaused ? 'admin_moderation' : null,
                'lock_version' => $offer->lock_version + 1,
            ]);
            $this->decisions->record($offer, $reviewer, $fromStatus, $decision->value, $offer->submitted_at);
            $this->audit->record(
                'catalog.offer_'.$decision->value,
                $offer,
                $before,
                $this->offerSnapshot($offer),
                $reason,
            );

            return $offer;
        });
    }

    private function transitionRelatedOffers(
        Product $product,
        ProductOfferStatus $decision,
        User $reviewer,
        ?string $reason,
    ): void {
        $offers = $product->offers()
            ->whereNotNull('merchant_id')
            ->whereIn('status', [
                ProductOfferStatus::PendingReview->value,
                ProductOfferStatus::ChangesRequested->value,
                ProductOfferStatus::Active->value,
            ])
            ->lockForUpdate()
            ->get();

        foreach ($offers as $offer) {
            $before = $this->offerSnapshot($offer);
            $fromStatus = $offer->status->value;
            $isPaused = $decision === ProductOfferStatus::Paused;
            $offer->update([
                'status' => $decision,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
                'review_notes' => $reason,
                'paused_at' => $isPaused ? now() : null,
                'paused_by' => $isPaused ? $reviewer->id : null,
                'pause_reason' => $isPaused ? 'product_hidden' : null,
                'lock_version' => $offer->lock_version + 1,
            ]);
            $this->decisions->record($offer, $reviewer, $fromStatus, $decision->value, $offer->submitted_at, 'product_moderation');
            $this->audit->record(
                'catalog.offer_'.$decision->value,
                $offer,
                $before,
                $this->offerSnapshot($offer),
                $reason,
                ['trigger' => 'product_moderation'],
            );
        }
    }

    private function ensureProductTransition(ProductStatus $from, ProductStatus $to): void
    {
        $allowed = [
            ProductStatus::PendingReview->value => [ProductStatus::Active, ProductStatus::ChangesRequested, ProductStatus::Rejected],
            ProductStatus::ChangesRequested->value => [ProductStatus::Active, ProductStatus::Rejected],
            ProductStatus::Active->value => [ProductStatus::Hidden],
            ProductStatus::Hidden->value => [ProductStatus::Active],
            ProductStatus::Rejected->value => [ProductStatus::ChangesRequested],
        ];

        if (! in_array($to, $allowed[$from->value] ?? [], true)) {
            throw ValidationException::withMessages(['decision' => 'انتقال حالة المنتج غير مسموح.']);
        }
    }

    private function ensureOfferTransition(ProductOfferStatus $from, ProductOfferStatus $to): void
    {
        $allowed = [
            ProductOfferStatus::PendingReview->value => [ProductOfferStatus::Active, ProductOfferStatus::ChangesRequested, ProductOfferStatus::Rejected],
            ProductOfferStatus::ChangesRequested->value => [ProductOfferStatus::Active, ProductOfferStatus::Rejected],
            ProductOfferStatus::Active->value => [ProductOfferStatus::Paused],
            ProductOfferStatus::Paused->value => [ProductOfferStatus::Active],
            ProductOfferStatus::Rejected->value => [ProductOfferStatus::ChangesRequested],
        ];

        if (! in_array($to, $allowed[$from->value] ?? [], true)) {
            throw ValidationException::withMessages(['decision' => 'انتقال حالة العرض غير مسموح.']);
        }
    }

    private function productSnapshot(Product $product): array
    {
        return [
            'status' => $product->status->value,
            'reviewed_by' => $product->reviewed_by,
        ];
    }

    private function offerSnapshot(ProductOffer $offer): array
    {
        return [
            'status' => $offer->status->value,
            'reviewed_by' => $offer->reviewed_by,
            'lock_version' => $offer->lock_version,
            'paused_by' => $offer->paused_by,
            'pause_reason' => $offer->pause_reason,
        ];
    }
}
