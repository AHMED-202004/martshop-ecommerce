<?php

namespace App\Services;

use App\Http\Controllers\CategoryController;
use App\Models\Product;

class CatalogItemResolver
{
    public function __construct(
        private readonly CategoryController $categories,
        private readonly CatalogQuery $catalogue,
    ) {}

    public function resolve(
        mixed $id,
        ?string $slug,
        ?int $offerId = null,
        ?int $offerVariantId = null,
        array $options = [],
    ): ?array
    {
        $product = Product::query()
            ->select(['id', 'source', 'slug', 'name', 'price', 'sale_price', 'image', 'status'])
            ->publishedForStore()
            ->when(
                is_numeric($id) && (int) $id > 0,
                fn ($query) => $query->whereKey((int) $id),
                fn ($query) => $query->where('slug', $slug)
            )
            ->first();

        if ($product) {
            $offer = $offerId
                ? $product->offers()->sellable()->whereKey($offerId)->first()
                : $this->catalogue->bestOffer($product);

            if (! $offer && $product->source === 'merchant_submission') {
                return null;
            }

            $availableVariants = $offer
                ? ($offer->relationLoaded('variants')
                    ? $offer->variants
                    : $offer->variants()->available()->get())
                : collect();
            $variant = $offerVariantId
                ? $availableVariants->firstWhere('id', $offerVariantId)
                : null;
            if ($offerVariantId && ! $variant) {
                return null;
            }
            if (! $variant && $availableVariants->isNotEmpty()) {
                $submittedOptions = collect($options)
                    ->filter(fn ($value, $key) => in_array($key, ['size', 'color'], true)
                        && is_scalar($value) && (string) $value !== '')
                    ->map(fn ($value) => (string) $value)
                    ->all();
                $matches = $submittedOptions === []
                    ? $availableVariants
                    : $availableVariants->filter(function ($candidate) use ($submittedOptions) {
                        $attributes = collect($candidate->attributes ?? [])
                            ->map(fn ($value) => is_scalar($value) ? (string) $value : $value)
                            ->all();

                        return collect($submittedOptions)->every(
                            fn (string $value, string $key) => ($attributes[$key] ?? null) === $value
                        );
                    });
                $variant = $matches->count() === 1 ? $matches->first() : null;
            }
            $offerVariantRequired = $availableVariants->isNotEmpty() && ! $variant;

            return [
                'id' => $product->id,
                'product_id' => $product->id,
                'offer_id' => $offer?->id,
                'offer_variant_id' => $variant?->id,
                'offer_variant_required' => $offerVariantRequired,
                'merchant_id' => $offer?->merchant_id,
                'location_id' => $offer?->location_id,
                'currency' => $offer?->currency ?? 'ILS',
                'compare_at_price' => $offer?->compare_at_price !== null
                    ? (float) $offer->compare_at_price
                    : null,
                'slug' => $product->slug,
                'name' => $product->name,
                'price' => $variant?->price !== null
                    ? (float) $variant->price
                    : ($offer ? (float) $offer->price : (float) $product->final_price),
                'image' => $product->image ? asset($product->image) : null,
                'options' => $variant?->attributes ?? [],
            ];
        }

        if (!$slug) {
            return null;
        }

        if (! config('catalog.legacy_fallback_enabled', false)) {
            return null;
        }

        // A database record is authoritative for its slug. Never let a hidden,
        // pending or otherwise unsellable record fall through to legacy demo data.
        if (Product::query()->where('slug', $slug)->exists()) {
            return null;
        }

        $demo = $this->categories->findDemoProductBySlug($slug);
        if (!$demo) {
            return null;
        }

        return [
            'id' => $slug,
            'product_id' => null,
            'offer_id' => null,
            'offer_variant_id' => null,
            'offer_variant_required' => false,
            'merchant_id' => null,
            'location_id' => null,
            'currency' => 'ILS',
            'compare_at_price' => isset($demo['old_price']) ? (float) $demo['old_price'] : null,
            'slug' => $slug,
            'name' => (string) $demo['name'],
            'price' => (float) $demo['price'],
            'image' => $demo['image'] ?? null,
            'options' => [],
        ];
    }
}
