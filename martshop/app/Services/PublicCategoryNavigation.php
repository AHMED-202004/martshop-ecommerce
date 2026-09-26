<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\Schema;

class PublicCategoryNavigation
{
    private const FALLBACK_CATEGORIES = [
        'shoes' => ['name' => 'أحذية', 'icon' => 'fa-solid fa-shoe-prints'],
        'clothing' => ['name' => 'الملابس', 'icon' => 'fa-solid fa-shirt'],
        'perfumes' => ['name' => 'عطور', 'icon' => 'fa-solid fa-wine-bottle'],
        'beauty' => ['name' => 'قسم الجمال', 'icon' => 'fa-solid fa-wand-magic-sparkles'],
        'watches' => ['name' => 'ساعات', 'icon' => 'fa-regular fa-clock'],
        'electronics' => ['name' => 'إلكترونيات', 'icon' => 'fa-solid fa-computer'],
        'bags' => ['name' => 'شنط واكسسوارات', 'icon' => 'fa-solid fa-bag-shopping'],
        'kids' => ['name' => 'الأطفال والألعاب', 'icon' => 'fa-solid fa-children'],
        'sporthealth' => ['name' => 'الرياضة و الصحة', 'icon' => 'fa-solid fa-heart-pulse'],
        'home' => ['name' => 'المنزل والحديقة', 'icon' => 'fa-solid fa-house'],
        'books' => ['name' => 'كتب و روايات', 'icon' => 'fa-solid fa-book'],
    ];

    public function roots(): array
    {
        if (! Schema::hasTable('categories') || ! Category::query()->exists()) {
            if (! config('catalog.legacy_fallback_enabled', false)) {
                return [];
            }

            return collect(self::FALLBACK_CATEGORIES)
                ->map(fn (array $item, string $slug) => ['slug' => $slug, ...$item])
                ->values()
                ->all();
        }

        return Category::query()
            ->publiclyVisible()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['slug', 'name', 'icon'])
            ->map(fn (Category $category) => [
                'slug' => $category->slug,
                'name' => $category->name,
                'icon' => $category->icon
                    ?: (self::FALLBACK_CATEGORIES[$category->slug]['icon'] ?? 'fa-solid fa-table-list'),
            ])
            ->all();
    }
}
