<?php

namespace App\Services;

use App\Enums\MerchantVerificationStatus;
use App\Enums\ProductOfferStatus;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MerchantCatalogService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ProductSubmissionImageStorage $images,
    ) {
    }

    public function submit(Merchant $merchant, User $actor, array $data, ?UploadedFile $image): ProductOffer
    {
        abort_unless(
            $merchant->user_id === $actor->id && $actor->can('createOffer', Product::class),
            403,
        );
        $storedImage = null;
        if ($data['product_mode'] === 'new' && $image) {
            $storedImage = $this->images->store($merchant, $image);
        }

        try {
            return DB::transaction(function () use ($merchant, $actor, $data, $storedImage) {
                $merchant = Merchant::query()->whereKey($merchant->id)->lockForUpdate()->firstOrFail();
                if ($merchant->user_id !== $actor->id
                    || $merchant->verification_status !== MerchantVerificationStatus::Verified) {
                    throw ValidationException::withMessages([
                        'merchant' => 'يجب اعتماد حساب التاجر قبل إرسال المنتجات والعروض.',
                    ]);
                }

                $productCreated = $data['product_mode'] === 'new';
                if ($productCreated && ! Category::query()
                    ->publiclyVisible()
                    ->whereKey($data['category_id'])
                    ->lockForUpdate()
                    ->first()) {
                    throw ValidationException::withMessages([
                        'category_id' => 'التصنيف المحدد غير متاح لإضافة المنتجات.',
                    ]);
                }
                if ($productCreated) {
                    $product = $this->createProduct($merchant, $data, $storedImage);
                } else {
                    $product = Product::query()
                        ->whereKey($data['product_id'])
                        ->where('status', ProductStatus::Active->value)
                        ->inPublicCategoryOrUncategorized()
                        ->lockForUpdate()
                        ->first();
                    if (! $product) {
                        throw ValidationException::withMessages([
                            'product_id' => 'المنتج المحدد غير متاح لإضافة عرض جديد.',
                        ]);
                    }
                }

                if (ProductOffer::query()
                    ->where('product_id', $product->id)
                    ->where('merchant_id', $merchant->id)
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'product_id' => 'لديك عرض مسجل لهذا المنتج بالفعل.',
                    ]);
                }

                $offer = ProductOffer::query()->create([
                    'product_id' => $product->id,
                    'merchant_id' => $merchant->id,
                    'location_id' => $data['location_id'],
                    'price' => round((float) $data['price'], 2),
                    'compare_at_price' => isset($data['compare_at_price'])
                        ? round((float) $data['compare_at_price'], 2)
                        : null,
                    'currency' => 'ILS',
                    'stock' => (int) $data['stock'],
                    'status' => ProductOfferStatus::PendingReview,
                    'preparation_time_days' => (int) $data['preparation_time_days'],
                    'warranty' => $data['warranty'] ?? null,
                    'last_confirmed_at' => null,
                    'expires_at' => null,
                    'source' => 'merchant',
                    'source_key' => $merchant->id.':'.Str::uuid(),
                    'submitted_at' => now(),
                ]);

                foreach ($this->sizes($data['variant_sizes'] ?? null) as $size) {
                    $offer->variants()->create([
                        'attributes' => array_filter([
                            'size' => $size,
                            'color' => $data['variant_color'] ?? null,
                        ]),
                        'stock' => null,
                        'status' => 'active',
                        'source_key' => 'size:'.sha1($size),
                    ]);
                }

                if ($productCreated) {
                    $this->audit->record('catalog.product_submitted', $product, null, [
                        'status' => $product->status->value,
                        'merchant_id' => $merchant->id,
                        'category_id' => $product->category_id,
                    ]);
                }
                $this->audit->record('catalog.offer_submitted', $offer, null, [
                    'status' => $offer->status->value,
                    'merchant_id' => $merchant->id,
                    'product_id' => $product->id,
                    'price' => (float) $offer->price,
                    'stock' => $offer->stock,
                ]);

                return $offer->load(['product', 'variants']);
            });
        } catch (Throwable $exception) {
            if ($storedImage) {
                Storage::disk('local')->delete($storedImage['path']);
            }
            throw $exception;
        }
    }

    private function createProduct(Merchant $merchant, array $data, ?array $storedImage): Product
    {
        $currentPrice = round((float) $data['price'], 2);
        $compareAtPrice = isset($data['compare_at_price'])
            ? round((float) $data['compare_at_price'], 2)
            : null;
        $slugBase = Str::substr(Str::slug($data['name']) ?: 'product', 0, 191);
        $slug = $slugBase.'-'.Str::lower(Str::random(8));

        $product = Product::query()->create([
            'category_id' => $data['category_id'],
            'created_by_merchant_id' => $merchant->id,
            'brand_id' => $data['brand_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'slug' => $slug,
            'model' => $data['model'] ?? null,
            'source' => 'merchant_submission',
            'source_key' => (string) Str::uuid(),
            'price' => $compareAtPrice ?? $currentPrice,
            'sale_price' => $compareAtPrice ? $currentPrice : null,
            'image' => null,
            'submission_image_disk' => $storedImage['disk'] ?? null,
            'submission_image_path' => $storedImage['path'] ?? null,
            'submission_image_size' => $storedImage['size'] ?? null,
            'submission_image_sha256' => $storedImage['sha256'] ?? null,
            'in_stock' => (int) $data['stock'] > 0,
            'status' => ProductStatus::PendingReview,
            'is_super_deal' => false,
            'submitted_at' => now(),
        ]);

        $product->categories()->attach($data['category_id'], [
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        return $product;
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
}
