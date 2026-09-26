<?php

namespace App\Http\Resources\Api\V1;

use App\Services\CatalogQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $public = app(CatalogQuery::class)->present($this->resource);
        $offer = $this->resource->offers->first();

        return [
            'id' => $public['id'],
            'slug' => $public['slug'],
            'name' => $public['name'],
            'image_url' => $public['image'] ? asset($public['image']) : null,
            'brand' => $public['brand'],
            'color' => $public['color'],
            'sizes' => $public['sizes'],
            'offer_id' => $offer->id,
            'price' => (string) $offer->price,
            'currency' => $offer->currency,
        ];
    }
}
