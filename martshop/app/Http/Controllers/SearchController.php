<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $q = trim((string) ($data['q'] ?? ''));

        if ($q === '') {
            // ممكن ترجّعه للصفحة السابقة لو الحقل فاضي
            return redirect()->back();
        }

        $literalTerm = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q);
        $products = Product::query()
            ->select(['id', 'brand_id', 'name', 'slug', 'image', 'price', 'sale_price'])
            ->with('brand:id,name')
            ->publishedForStore()
            ->where(function ($query) use ($literalTerm) {
                $query->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$literalTerm.'%'])
                    ->orWhereRaw("slug LIKE ? ESCAPE '!'", ['%'.$literalTerm.'%']);
            })
            ->orderByDesc('created_at')
            ->paginate(24)
            ->withQueryString();

        return view('search.results', compact('products', 'q'));
    }
}
