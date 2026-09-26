<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    private const CATEGORY_PRESENTATION = [
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

    private const BRAND_IMAGES = [
        'rue-broca' => 'assets/img/brands/307-small_default.jpg',
        'rock' => 'assets/img/brands/100-small_default.jpg',
        'diadora' => 'assets/img/brands/3-small_default.jpg',
        'somi' => 'assets/img/brands/287-small_default.jpg',
        'hi-tec' => 'assets/img/brands/13-small_default.jpg',
        'reebok' => 'assets/img/brands/85-small_default.jpg',
        'under-armour' => 'assets/img/brands/338-small_default.jpg',
        'skechers' => 'assets/img/brands/9-small_default.jpg',
        'adidas' => 'assets/img/brands/146-small_default (1).jpg',
    ];

    public function index()
    {
        $homeCategories = $this->categories();
        $megaMenu = $this->megaMenu();
        $featuredBrands = $this->brands();

        return view('home', compact('homeCategories', 'megaMenu', 'featuredBrands'));
    }

    private function megaMenu(): ?array
    {
        if (! Schema::hasTable('categories') || ! Category::query()->exists()) {
            return null;
        }

        return Category::query()
            ->active()
            ->whereNull('parent_id')
            ->with(['children' => fn ($children) => $children
                ->active()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->with(['children' => fn ($grandchildren) => $grandchildren
                    ->active()
                    ->orderBy('sort_order')
                    ->orderBy('id')])])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function (Category $root) {
                $columns = $root->children->map(function (Category $child) {
                    $items = $child->children->isNotEmpty()
                        ? $child->children->map(fn (Category $leaf) => [
                            $leaf->name,
                            url('/c/'.$leaf->path),
                        ])->values()->all()
                        : [[$child->name, url('/c/'.$child->path)]];

                    return ['title' => $child->name, 'items' => $items];
                })->values()->all();

                return [$root->slug => ['cols' => $columns]];
            })
            ->all();
    }

    private function categories(): array
    {
        if (! Schema::hasTable('categories') || ! Category::query()->exists()) {
            if (! config('catalog.legacy_fallback_enabled', false)) {
                return [];
            }

            return collect(self::CATEGORY_PRESENTATION)
                ->map(fn (array $item, string $slug) => ['slug' => $slug, ...$item])
                ->values()
                ->all();
        }

        return Category::query()
            ->active()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['slug', 'name'])
            ->map(fn (Category $category) => [
                'slug' => $category->slug,
                'name' => $category->name,
                'icon' => self::CATEGORY_PRESENTATION[$category->slug]['icon'] ?? 'fa-solid fa-table-list',
            ])
            ->all();
    }

    private function brands(): array
    {
        if (! Schema::hasTable('brands') || ! Schema::hasTable('products')) {
            return [];
        }

        $brands = Brand::query()
            ->whereIn('slug', array_keys(self::BRAND_IMAGES))
            ->whereHas('products', fn (Builder $products) => $products->publishedForStore())
            ->get(['id', 'name', 'slug'])
            ->keyBy('slug');

        return collect(self::BRAND_IMAGES)
            ->map(function (string $image, string $slug) use ($brands) {
                $brand = $brands->get($slug);

                return $brand ? [
                    'slug' => $brand->slug,
                    'name' => $brand->name,
                    'image' => $image,
                ] : null;
            })
            ->filter()
            ->values()
            ->all();
    }
}
