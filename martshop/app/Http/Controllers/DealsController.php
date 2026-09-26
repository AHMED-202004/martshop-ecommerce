<?php

namespace App\Http\Controllers;

use App\Services\PublicCategoryNavigation;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Brand;
use Illuminate\Validation\Rule;

class DealsController extends Controller
{
    public function index(Request $r, PublicCategoryNavigation $categoryNavigation)
    {
        $availableColors = ['أحمر','أزرق','أسود','أبيض','بني','كحلي','عسلي','offwhite','Grey'];
        $alphaList       = ['2XS','XS','S','M','L','XL','One Size','11-12 سنوات'];
        $numList         = [28,29,30,31,32,33,34,35,37.5,38.5,39,40,41,42,43,44,45,46];
        $filters = $r->validate([
            'brand' => ['sometimes', 'array', 'max:50'],
            'brand.*' => ['integer', 'distinct', 'exists:brands,id'],
            'color' => ['sometimes', 'array', 'max:20'],
            'color.*' => ['string', 'distinct', Rule::in($availableColors)],
            'alpha' => ['sometimes', 'array', 'max:20'],
            'alpha.*' => ['string', 'distinct', Rule::in($alphaList)],
            'num' => ['sometimes', 'array', 'max:30'],
            'num.*' => ['numeric', 'distinct', Rule::in($numList)],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $brandIds = array_map('intval', $filters['brand'] ?? []);
        $colors = $filters['color'] ?? [];
        $alphaSizes = $filters['alpha'] ?? [];
        $numSizes = array_map('strval', $filters['num'] ?? []);
        $qText = trim((string) ($filters['q'] ?? ''));

        $brands = Brand::query()
            ->whereHas('products', fn ($products) => $products
                ->publishedForStore()
                ->where('is_super_deal', true))
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        // الاستعلام الأساسي
        $q = Product::query()
            ->publishedForStore()
            ->select(['id', 'brand_id', 'name', 'slug', 'image', 'price', 'sale_price'])
            ->withPublicCardData()
            ->where('is_super_deal', true)

            // بحث نصي اختياري
            ->when($qText !== '', function ($qq) use ($qText) {
                $literal = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $qText);
                $qq->where(function ($w) use ($literal) {
                    $w->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$literal.'%'])
                      ->orWhereRaw("slug LIKE ? ESCAPE '!'", ['%'.$literal.'%']);
                });
            })

            // فلاتر حسب الاختيار
            ->when(!empty($brandIds), function ($qq) use ($brandIds) {
                $qq->whereIn('brand_id', $brandIds);
            })
            ->when(!empty($colors), function ($qq) use ($colors) {
                $qq->whereHas('variants', fn($v) => $v->available()->whereIn('color', $colors));
            })
            ->when(!empty($alphaSizes), function ($qq) use ($alphaSizes) {
                $qq->whereHas('variants', fn($v) => $v->available()->where('size_type','alpha')
                                                     ->whereIn('size_value', $alphaSizes));
            })
            ->when(!empty($numSizes), function ($qq) use ($numSizes) {
                $qq->whereHas('variants', fn($v) => $v->available()->where('size_type','num')
                                                     ->whereIn('size_value', $numSizes));
            });

        // بدون فلاتر -> يعرض الكل
        $products = $q->orderByDesc('id')
                      ->paginate(24)
                      ->withQueryString(); // يحافظ على معاملات الرابط

        return view('deals.index', [
            'products'        => $products,
            'brands'          => $brands,
            'availableColors' => $availableColors,
            'alphaList'       => $alphaList,
            'numList'         => $numList,

            'brandIds'        => $brandIds,
            'colors'          => $colors,
            'alphaSizes'      => $alphaSizes,
            'numSizes'        => $numSizes,
            'qText'           => $qText,
            'navigationCategories' => $categoryNavigation->roots(),
        ]);
    }
}
