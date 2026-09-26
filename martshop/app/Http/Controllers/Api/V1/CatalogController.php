<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Category;
use App\Services\CatalogQuery;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function categories(Request $request)
    {
        $filters = $request->validate([
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        return CategoryResource::collection(Category::query()->publiclyVisible()
            ->select(['id', 'parent_id', 'name', 'slug', 'path'])
            ->orderBy('sort_order')->orderBy('id')
            ->paginate($filters['per_page'] ?? 30)->withQueryString());
    }

    public function index(Request $request, CatalogQuery $catalogue)
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category' => ['sometimes', 'nullable', 'string', 'max:200'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $query = $catalogue->publicApiProducts();
        if (! empty($filters['q'])) {
            $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']);
            $query->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$term.'%']);
        }
        if (! empty($filters['category'])) {
            $category = Category::query()->publiclyVisible()
                ->where('path', $filters['category'])
                ->firstOrFail();
            $query->whereHas('categories', fn ($categories) => $categories->whereKey($category->id));
        }

        return ProductResource::collection($query->orderBy('id')
            ->paginate($filters['per_page'] ?? 20)->withQueryString());
    }

    public function show(string $slug, CatalogQuery $catalogue)
    {
        return new ProductResource($catalogue->publicApiProducts()->where('slug', $slug)->firstOrFail());
    }
}
