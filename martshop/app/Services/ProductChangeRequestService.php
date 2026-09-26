<?php

namespace App\Services;

use App\Enums\MerchantVerificationStatus;
use App\Enums\ProductChangeRequestStatus;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductChangeRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductChangeRequestService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ProductChangeRequestImageStorage $images,
        private readonly CatalogReviewDecisionRecorder $decisions,
    ) {
    }

    public function submit(
        Product $product,
        Merchant $merchant,
        User $actor,
        array $data,
        ?UploadedFile $image,
    ): ProductChangeRequest {
        abort_unless(
            $merchant->user_id === $actor->id && $actor->can('proposeChanges', $product),
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
                    throw ValidationException::withMessages(['merchant' => 'حساب التاجر غير مؤهل للاقتراحات.']);
                }

                $product = Product::query()
                    ->whereKey($product->id)
                    ->where('status', ProductStatus::Active->value)
                    ->lockForUpdate()
                    ->firstOrFail();
                abort_unless($actor->can('proposeChanges', $product), 403);
                if (! $product->offers()->where('merchant_id', $merchant->id)->exists()) {
                    throw ValidationException::withMessages([
                        'product' => 'يمكنك اقتراح تعديل لمنتج لديك عرض مرتبط به فقط.',
                    ]);
                }

                if (! Category::query()->publiclyVisible()
                    ->whereKey($data['category_id'])
                    ->lockForUpdate()
                    ->first()) {
                    throw ValidationException::withMessages([
                        'category_id' => 'التصنيف المحدد غير متاح لإضافة المنتجات.',
                    ]);
                }

                $request = ProductChangeRequest::query()
                    ->where('product_id', $product->id)
                    ->where('merchant_id', $merchant->id)
                    ->whereIn('status', [
                        ProductChangeRequestStatus::PendingReview->value,
                        ProductChangeRequestStatus::ChangesRequested->value,
                    ])
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();

                if ($request?->status === ProductChangeRequestStatus::PendingReview) {
                    throw ValidationException::withMessages([
                        'change_request' => 'يوجد اقتراح قيد المراجعة لهذا المنتج بالفعل.',
                    ]);
                }

                $changes = $this->proposedChanges($data);
                if ($request) {
                    $expectedVersion = isset($data['change_request_lock_version'])
                        ? (int) $data['change_request_lock_version']
                        : -1;
                    if ($request->lock_version !== $expectedVersion) {
                        throw ValidationException::withMessages([
                            'change_request_lock_version' => 'تم تحديث الطلب. أعد تحميل الصفحة.',
                        ]);
                    }

                    $before = $this->requestSnapshot($request);
                    if ($storedImage) {
                        $obsoleteImage = $this->images->resolve($request);
                    }
                    $request->update([
                        'proposed_changes' => $changes,
                        'proposed_image_disk' => $storedImage['disk'] ?? $request->proposed_image_disk,
                        'proposed_image_path' => $storedImage['path'] ?? $request->proposed_image_path,
                        'proposed_image_size' => $storedImage['size'] ?? $request->proposed_image_size,
                        'proposed_image_sha256' => $storedImage['sha256'] ?? $request->proposed_image_sha256,
                        'status' => ProductChangeRequestStatus::PendingReview,
                        'open_key' => $product->id.':'.$merchant->id,
                        'submitted_at' => now(),
                        'reviewed_at' => null,
                        'reviewed_by' => null,
                        'review_notes' => null,
                        'lock_version' => $request->lock_version + 1,
                    ]);
                    $action = 'catalog.product_change_resubmitted';
                } else {
                    $before = null;
                    $request = ProductChangeRequest::query()->create([
                        'product_id' => $product->id,
                        'merchant_id' => $merchant->id,
                        'proposed_changes' => $changes,
                        'proposed_image_disk' => $storedImage['disk'] ?? null,
                        'proposed_image_path' => $storedImage['path'] ?? null,
                        'proposed_image_size' => $storedImage['size'] ?? null,
                        'proposed_image_sha256' => $storedImage['sha256'] ?? null,
                        'status' => ProductChangeRequestStatus::PendingReview,
                        'open_key' => $product->id.':'.$merchant->id,
                        'submitted_at' => now(),
                    ]);
                    $action = 'catalog.product_change_submitted';
                }

                $this->audit->record(
                    $action,
                    $request,
                    $before,
                    $this->requestSnapshot($request),
                );

                return $request;
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

    public function review(
        ProductChangeRequest $changeRequest,
        ProductChangeRequestStatus $decision,
        User $reviewer,
        ?string $reason,
    ): ProductChangeRequest {
        $publicImagePath = null;

        try {
            return DB::transaction(function () use (
                $changeRequest,
                $decision,
                $reviewer,
                $reason,
                &$publicImagePath,
            ) {
                $merchant = Merchant::query()
                    ->whereKey($changeRequest->merchant_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $product = Product::query()
                    ->whereKey($changeRequest->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $changeRequest = ProductChangeRequest::query()
                    ->whereKey($changeRequest->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                abort_unless($reviewer->can('moderate', $changeRequest), 403);

                if ($changeRequest->status !== ProductChangeRequestStatus::PendingReview) {
                    throw ValidationException::withMessages([
                        'decision' => 'طلب التغيير ليس قيد المراجعة.',
                    ]);
                }

                $beforeRequest = $this->requestSnapshot($changeRequest);
                $fromStatus = $changeRequest->status->value;
                if ($decision === ProductChangeRequestStatus::Approved) {
                    if ($product->status !== ProductStatus::Active) {
                        throw ValidationException::withMessages([
                            'decision' => 'لا يمكن تطبيق التغيير على منتج غير نشط.',
                        ]);
                    }

                    $changes = $changeRequest->proposed_changes;
                    $category = Category::query()
                        ->publiclyVisible()
                        ->whereKey($changes['category_id'])
                        ->firstOrFail();
                    if ($changeRequest->proposed_image_path) {
                        $image = $this->images->resolve($changeRequest);
                        if (! $image) {
                            throw ValidationException::withMessages([
                                'decision' => 'تعذّر التحقق من سلامة صورة التعديل.',
                            ]);
                        }
                        $publicImagePath = 'products/'.Str::uuid().'.'.$image['extension'];
                        $contents = file_get_contents($image['path']);
                        if (! is_string($contents) || ! Storage::disk('public')->put($publicImagePath, $contents)) {
                            throw ValidationException::withMessages([
                                'decision' => 'تعذّر حفظ صورة المنتج المعتمدة.',
                            ]);
                        }
                    }

                    $beforeProduct = $this->productSnapshot($product);
                    $oldCategoryId = $product->category_id;
                    $product->update([
                        'name' => $changes['name'],
                        'category_id' => $category->id,
                        'brand_id' => $changes['brand_id'],
                        'description' => $changes['description'],
                        'model' => $changes['model'],
                        'specifications' => $changes['specifications'],
                        'image' => $publicImagePath ? 'storage/'.$publicImagePath : $product->image,
                    ]);
                    $this->movePrimaryCategory($product, $oldCategoryId, $category->id);
                    $this->audit->record(
                        'catalog.product_core_updated',
                        $product,
                        $beforeProduct,
                        $this->productSnapshot($product),
                        null,
                        ['change_request_id' => $changeRequest->id],
                    );
                }

                $changeRequest->update([
                    'status' => $decision,
                    'open_key' => $decision === ProductChangeRequestStatus::ChangesRequested
                        ? $changeRequest->open_key
                        : null,
                    'reviewed_at' => now(),
                    'reviewed_by' => $reviewer->id,
                    'review_notes' => $reason,
                    'lock_version' => $changeRequest->lock_version + 1,
                ]);
                $this->decisions->record($changeRequest, $reviewer, $fromStatus, $decision->value, $changeRequest->submitted_at);
                $this->audit->record(
                    'catalog.product_change_'.$decision->value,
                    $changeRequest,
                    $beforeRequest,
                    $this->requestSnapshot($changeRequest),
                    $reason,
                );

                return $changeRequest;
            });
        } catch (Throwable $exception) {
            if ($publicImagePath) {
                Storage::disk('public')->delete($publicImagePath);
            }
            throw $exception;
        }
    }

    private function proposedChanges(array $data): array
    {
        return [
            'name' => $data['name'],
            'category_id' => (int) $data['category_id'],
            'brand_id' => isset($data['brand_id']) ? (int) $data['brand_id'] : null,
            'description' => $data['description'] ?? null,
            'model' => $data['model'] ?? null,
            'specifications' => ! empty($data['specifications'])
                ? json_decode($data['specifications'], true, 512, JSON_THROW_ON_ERROR)
                : null,
        ];
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

    private function requestSnapshot(ProductChangeRequest $request): array
    {
        return [
            'status' => $request->status->value,
            'proposed_changes' => $request->proposed_changes,
            'has_proposed_image' => $request->proposed_image_path !== null,
            'proposed_image_size' => $request->proposed_image_size,
            'lock_version' => $request->lock_version,
        ];
    }

    private function productSnapshot(Product $product): array
    {
        return [
            'name' => $product->name,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'description' => $product->description,
            'model' => $product->model,
            'specifications' => $product->specifications,
            'image' => $product->image,
        ];
    }
}
