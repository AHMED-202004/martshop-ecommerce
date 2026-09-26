<?php

namespace App\Services;

use App\Enums\MerchantVerificationStatus;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class MerchantProductSubmissionService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ProductSubmissionImageStorage $images,
    ) {
    }

    public function resubmit(
        Product $product,
        Merchant $merchant,
        User $actor,
        array $data,
        ?UploadedFile $image,
    ): Product {
        abort_unless(
            $merchant->user_id === $actor->id && $actor->can('updateSubmission', $product),
            403,
        );
        $storedImage = $image ? $this->images->store($merchant, $image) : null;
        $obsoleteImage = null;

        try {
            $result = DB::transaction(function () use (
                $product,
                $merchant,
                $actor,
                $data,
                $storedImage,
                &$obsoleteImage,
            ) {
                $merchant = Merchant::query()->whereKey($merchant->id)->lockForUpdate()->firstOrFail();
                if ($merchant->user_id !== $actor->id
                    || $merchant->verification_status !== MerchantVerificationStatus::Verified) {
                    throw ValidationException::withMessages(['merchant' => 'حساب التاجر غير مؤهل للتعديل.']);
                }

                $product = Product::query()
                    ->whereKey($product->id)
                    ->where('created_by_merchant_id', $merchant->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                abort_unless($actor->can('updateSubmission', $product), 403);
                if ($product->status !== ProductStatus::ChangesRequested) {
                    throw ValidationException::withMessages([
                        'product' => 'يمكن تعديل المنتج عندما تكون حالته مطلوب تعديل فقط.',
                    ]);
                }

                $before = $this->snapshot($product);
                $oldCategoryId = $product->category_id;
                $newCategoryId = (int) $data['category_id'];
                if (! Category::query()->publiclyVisible()->whereKey($newCategoryId)->lockForUpdate()->first()) {
                    throw ValidationException::withMessages([
                        'category_id' => 'التصنيف المحدد غير متاح لإضافة المنتجات.',
                    ]);
                }
                if ($storedImage) {
                    $obsoleteImage = $this->images->resolve($product);
                }
                $product->update([
                    'name' => $data['name'],
                    'category_id' => $newCategoryId,
                    'brand_id' => $data['brand_id'] ?? null,
                    'description' => $data['description'] ?? null,
                    'model' => $data['model'] ?? null,
                    'submission_image_disk' => $storedImage['disk'] ?? $product->submission_image_disk,
                    'submission_image_path' => $storedImage['path'] ?? $product->submission_image_path,
                    'submission_image_size' => $storedImage['size'] ?? $product->submission_image_size,
                    'submission_image_sha256' => $storedImage['sha256'] ?? $product->submission_image_sha256,
                    'status' => ProductStatus::PendingReview,
                    'submitted_at' => now(),
                    'reviewed_at' => null,
                    'reviewed_by' => null,
                    'review_notes' => null,
                ]);
                $this->movePrimaryCategory($product, $oldCategoryId, $newCategoryId);

                $this->audit->record(
                    'catalog.product_resubmitted',
                    $product,
                    $before,
                    $this->snapshot($product),
                );

                return $product;
            });

            if ($obsoleteImage) {
                Storage::disk('local')->delete($obsoleteImage['relative_path']);
            }

            return $result;
        } catch (Throwable $exception) {
            if ($storedImage) {
                Storage::disk('local')->delete($storedImage['path']);
            }
            throw $exception;
        }
    }

    private function movePrimaryCategory(Product $product, ?int $oldCategoryId, int $newCategoryId): void
    {
        DB::table('category_product')->where('product_id', $product->id)->update(['is_primary' => false]);
        if ($oldCategoryId && $oldCategoryId !== $newCategoryId) {
            $product->categories()->detach($oldCategoryId);
        }
        $product->categories()->syncWithoutDetaching([
            $newCategoryId => ['is_primary' => true, 'sort_order' => 0],
        ]);
    }

    private function snapshot(Product $product): array
    {
        return [
            'name' => $product->name,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'description' => $product->description,
            'model' => $product->model,
            'has_image' => $product->image !== null || $product->submission_image_path !== null,
            'submission_image_size' => $product->submission_image_size,
            'status' => $product->status->value,
        ];
    }
}
