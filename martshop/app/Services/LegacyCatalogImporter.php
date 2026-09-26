<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOffer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class LegacyCatalogImporter
{
    public function __construct(private readonly LegacyCatalogNormalizer $normalizer)
    {
    }

    public function import(array $catalogue): array
    {
        return DB::transaction(function () use ($catalogue) {
            $rows = $this->catalogueRows($catalogue);
            $categoryPaths = $rows->pluck('path')->unique()->values();
            $categories = Category::query()->whereIn('path', $categoryPaths)->get()->keyBy('path');
            $missingPaths = $categoryPaths->diff($categories->keys());

            if ($missingPaths->isNotEmpty()) {
                throw new RuntimeException('Missing catalogue categories: '.$missingPaths->implode(', '));
            }

            $createdProducts = 0;
            $existingProducts = 0;
            $createdOffers = 0;
            $variantCount = 0;

            foreach ($rows->groupBy('slug') as $slug => $productRows) {
                $first = $productRows->first();
                $categoryIds = $productRows
                    ->pluck('path')
                    ->unique()
                    ->map(fn (string $path) => $categories[$path]->id)
                    ->values();
                $categorySortOrders = $productRows
                    ->groupBy('path')
                    ->map(fn (Collection $rows) => (int) $rows->min('position'));

                $product = Product::query()->where('slug', $slug)->first();
                if ($product) {
                    $existingProducts++;
                    if (! $product->category_id) {
                        $product->category_id = $categoryIds->first();
                    }
                    if (! $product->brand_id && $brandId = $this->brandId($first['name'])) {
                        $product->brand_id = $brandId;
                    }
                    if ($product->isDirty()) {
                        $product->save();
                    }
                } else {
                    $currentPrice = $this->money($first['price']);
                    $compareAtPrice = $this->compareAtPrice($first);
                    $product = Product::query()->create([
                        'category_id' => $categoryIds->first(),
                        'brand_id' => $this->brandId($first['name']),
                        'name' => $first['name'],
                        'slug' => $slug,
                        'price' => $compareAtPrice ?? $currentPrice,
                        'sale_price' => $compareAtPrice ? $currentPrice : null,
                        'image' => $first['image'] ?? null,
                        'in_stock' => true,
                        'status' => 'active',
                        'is_super_deal' => false,
                        'source' => 'legacy_demo',
                        'source_key' => $slug,
                        'metadata' => [
                            'legacy_category_paths' => $productRows->pluck('path')->unique()->values()->all(),
                        ],
                    ]);
                    $createdProducts++;
                }

                foreach ($categoryIds as $categoryId) {
                    $categoryPath = $categories->firstWhere('id', $categoryId)->path;
                    $product->categories()->syncWithoutDetaching([
                        $categoryId => [
                            'is_primary' => $product->category_id === $categoryId,
                            'sort_order' => $categorySortOrders[$categoryPath] ?? 0,
                        ],
                    ]);
                }

                $offer = ProductOffer::query()->firstOrNew([
                    'source' => 'legacy_demo',
                    'source_key' => $slug,
                ]);
                if (! $offer->exists) {
                    $createdOffers++;
                }

                $offer->fill([
                    'product_id' => $product->id,
                    'merchant_id' => null,
                    'location_id' => null,
                    'price' => $this->money($first['price']),
                    'compare_at_price' => $this->compareAtPrice($first),
                    'currency' => 'ILS',
                    'stock' => null,
                    'status' => 'active',
                    'last_confirmed_at' => now(),
                    'expires_at' => null,
                    'metadata' => [
                        'category_paths' => $productRows->pluck('path')->unique()->values()->all(),
                        'legacy_names' => $productRows->pluck('name')->unique()->values()->all(),
                    ],
                ])->save();

                $color = $this->normalizer->inferColor($first['name']);
                $sizes = $productRows
                    ->flatMap(fn (array $row) => $row['sizes'] ?? [])
                    ->map(fn ($size) => (string) $size)
                    ->filter()
                    ->unique()
                    ->values();

                foreach ($sizes as $size) {
                    $sourceKey = 'size:'.sha1($size);
                    $offer->variants()->updateOrCreate(
                        ['source_key' => $sourceKey],
                        [
                            'attributes' => array_filter([
                                'size' => $size,
                                'color' => $color,
                            ]),
                            'price' => null,
                            'stock' => null,
                            'status' => 'active',
                        ]
                    );
                    $variantCount++;
                }
            }

            return [
                'catalogue_rows' => $rows->count(),
                'unique_products' => $rows->pluck('slug')->unique()->count(),
                'created_products' => $createdProducts,
                'existing_products' => $existingProducts,
                'created_offers' => $createdOffers,
                'offer_variants' => $variantCount,
            ];
        });
    }

    private function catalogueRows(array $catalogue): Collection
    {
        return collect($catalogue)->flatMap(function (array $products, string $path) {
            return collect($products)->map(function (array $product, int $position) use ($path) {
                if (empty($product['slug']) || empty($product['name']) || ! isset($product['price'])) {
                    throw new RuntimeException("Invalid legacy product in category {$path}.");
                }

                return array_merge($product, ['path' => $path, 'position' => $position]);
            });
        })->values();
    }

    private function brandId(string $name): ?int
    {
        $brandName = $this->normalizer->inferBrand($name);
        if (! $brandName) {
            return null;
        }

        return Brand::query()->firstOrCreate(
            ['slug' => Str::slug($brandName)],
            ['name' => $brandName]
        )->id;
    }

    private function compareAtPrice(array $product): ?float
    {
        $currentPrice = $this->money($product['price']);
        $oldPrice = isset($product['old_price']) ? $this->money($product['old_price']) : null;

        return $oldPrice !== null && $oldPrice > $currentPrice ? $oldPrice : null;
    }

    private function money(mixed $value): float
    {
        return round((float) $value, 2);
    }
}
