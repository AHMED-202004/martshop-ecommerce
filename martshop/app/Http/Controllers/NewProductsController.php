<?php


namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\PublicCategoryNavigation;

class NewProductsController extends Controller
{
    public function index(PublicCategoryNavigation $categoryNavigation)
    {
        $products = Product::query()->publishedForStore()->select([
            'id', 'brand_id', 'name', 'slug', 'price', 'sale_price', 'image', 'created_at',
        ])->withPublicCardData()
        ->where('is_new', true)
        ->orderByDesc('created_at')
        ->paginate(24);

        return view('new-products.index', [
            'products' => $products,
            'navigationCategories' => $categoryNavigation->roots(),
        ]);
    }
}
