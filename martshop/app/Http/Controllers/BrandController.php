<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function show(Brand $brand, Request $request)
    {
        // جميع منتجات الماركة (مفعّلة) مع العلاقات المطلوبة للواجهة
        $products = $brand->products()
            ->publishedForStore()
            ->select(['id', 'brand_id', 'name', 'slug', 'price', 'sale_price', 'image'])
            ->withPublicCardData()
            ->latest('id')
            ->paginate(24)
            ->withQueryString();

        return view('brands.show', compact('brand', 'products'));
    }
}
