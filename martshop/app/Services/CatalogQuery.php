<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOffer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CatalogQuery
{
    public function __construct(private readonly LegacyCatalogNormalizer $normalizer)
    {
    }

    public function forCategoryPath(string $path): Collection
    {
        if (! Schema::hasTable('product_offers') || ! Schema::hasTable('category_product')) {
            return collect();
        }

        $categories = Category::query()
            ->publiclyVisible()
            ->where(fn (Builder $query) => $query
                ->where('path', $path)
                ->orWhere('path', 'like', $path.'/%'))
            ->orderBy('depth')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'path']);

        if ($categories->isEmpty()) {
            return collect();
        }

        $categoryIds = $categories->pluck('id');
        $listingRows = DB::table('category_product')
            ->whereIn('category_id', $categoryIds)
            ->orderBy('sort_order')
            ->orderBy('product_id')
            ->get()
            ->groupBy('category_id');

        $productIds = collect();
        foreach ($categoryIds as $categoryId) {
            $productIds->push(...$listingRows->get($categoryId, collect())->pluck('product_id'));
        }
        $productIds = $productIds->unique()->values();

        if ($productIds->isEmpty()) {
            return collect();
        }

        $products = $this->publicProducts()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        return $productIds
            ->map(fn (int $productId) => $products->get($productId))
            ->filter()
            ->map(fn (Product $product) => $this->present($product))
            ->values();
    }

    public function publicProducts(): Builder
    {
        return Product::query()
            ->publishedForStore()
            ->whereHas('offers', fn (Builder $query) => $query->sellable())
            ->with([
                'brand',
                'variants' => fn ($query) => $query->available(),
                'offers' => fn ($query) => $query
                    ->sellable()
                    ->with(['variants' => fn ($query) => $query->available()])
                    ->orderBy('price')
                    ->orderBy('id'),
            ]);
    }

    public function publicApiProducts(): Builder
    {
        return Product::query()
            ->select(['id', 'brand_id', 'name', 'slug', 'price', 'sale_price', 'image'])
            ->publishedForStore()
            ->whereHas('offers', fn (Builder $query) => $query->sellable())
            ->with([
                'brand:id,name',
                'variants' => fn ($query) => $query->available()
                    ->select(['id', 'product_id', 'size_value', 'color']),
                'offers' => fn ($query) => $query
                    ->select(['id', 'product_id', 'price', 'compare_at_price', 'currency'])
                    ->sellable()
                    ->with(['variants' => fn ($query) => $query
                        ->select(['id', 'product_offer_id', 'attributes'])->available()])
                    ->orderBy('price')
                    ->orderBy('id'),
            ]);
    }

    public function bestOffer(Product $product): ?ProductOffer
    {
        if (! Schema::hasTable('product_offers')) {
            return null;
        }

        return $product->offers()
            ->select(['id', 'product_id', 'price', 'compare_at_price', 'currency'])
            ->sellable()
            ->with(['variants' => fn ($query) => $query
                ->select(['id', 'product_offer_id', 'attributes', 'price'])->available()])
            ->orderBy('price')
            ->orderBy('id')
            ->first();
    }

    public function present(Product $product, ?ProductOffer $offer = null): array
    {
        $offer ??= $product->relationLoaded('offers') ? $product->offers->first() : $this->bestOffer($product);
        $currentPrice = $offer ? (float) $offer->price : (float) $product->final_price;
        $compareAtPrice = $offer?->compare_at_price !== null
            ? (float) $offer->compare_at_price
            : ($product->sale_price !== null && (float) $product->price > $currentPrice
                ? (float) $product->price
                : null);

        $offerVariants = $offer?->variants ?? collect();
        $sizes = $offerVariants
            ->map(fn ($variant) => $variant->attributes['size'] ?? null)
            ->filter()
            ->merge($offerVariants->isEmpty() ? $product->variants->pluck('size_value') : [])
            ->map(fn ($size) => (string) $size)
            ->unique()
            ->values();
        $color = $offerVariants
            ->map(fn ($variant) => $variant->attributes['color'] ?? null)
            ->filter()
            ->first()
            ?? $product->variants->pluck('color')->filter()->first()
            ?? $this->normalizer->inferColor($product->name);
        $presentedOfferVariants = $offerVariants
            ->map(fn ($variant) => [
                'id' => $variant->id,
                'attributes' => collect($variant->attributes ?? [])
                    ->only(['size', 'color'])
                    ->filter(fn ($value) => is_scalar($value) && (string) $value !== '')
                    ->map(fn ($value) => (string) $value)
                    ->all(),
                'price' => $variant->price !== null ? (float) $variant->price : $currentPrice,
            ])
            ->values();

        return [
            'id' => $product->id,
            'offer_id' => $offer?->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $currentPrice,
            'old_price' => $compareAtPrice,
            'image' => $product->image,
            'brand' => $product->brand?->name ?? $this->normalizer->inferBrand($product->name),
            'color' => $color,
            'sizes' => $sizes->all(),
            'offer_variants' => $presentedOfferVariants->all(),
        ];
    }
}
