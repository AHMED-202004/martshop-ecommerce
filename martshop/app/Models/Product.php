<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id', 'category_id', 'created_by_merchant_id', 'name', 'description', 'slug', 'model',
        'specifications', 'source', 'source_key', 'metadata', 'price',
        'sale_price', 'image', 'submission_image_disk', 'submission_image_path',
        'submission_image_size', 'submission_image_sha256', 'in_stock', 'status',
        'is_new', 'is_super_deal', 'submitted_at',
        'reviewed_at', 'reviewed_by', 'review_notes',
    ];

    protected $hidden = [
        'metadata', 'review_notes', 'source_key', 'submission_image_disk',
        'submission_image_path', 'submission_image_size', 'submission_image_sha256',
    ];

    protected function casts(): array
    {
        return [
            'specifications' => 'array',
            'metadata' => 'array',
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'submission_image_size' => 'integer',
            'is_new' => 'boolean',
            'in_stock' => 'boolean',
            'is_super_deal' => 'boolean',
            'status' => ProductStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function creatorMerchant()
    {
        return $this->belongsTo(Merchant::class, 'created_by_merchant_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class)
            ->withPivot('is_primary', 'sort_order')
            ->withTimestamps();
    }

    public function offers()
    {
        return $this->hasMany(ProductOffer::class);
    }

    public function changeRequests()
    {
        return $this->hasMany(ProductChangeRequest::class);
    }


    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function scopePublishedForStore(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Active->value)
            ->inPublicCategoryOrUncategorized()
            ->where(fn (Builder $products) => $products
                ->whereHas('offers', fn (Builder $offers) => $offers->sellable())
                ->orWhere(fn (Builder $platformProducts) => $platformProducts
                    ->where(fn (Builder $source) => $source
                        ->where('source', '!=', 'merchant_submission')
                        ->orWhereNull('source'))
                    ->where('in_stock', true)));
    }

    public function scopeInPublicCategoryOrUncategorized(Builder $query): Builder
    {
        return $query->where(fn (Builder $products) => $products
            ->whereHas('categories', fn (Builder $categories) => $categories->publiclyVisible())
            ->orWhere(fn (Builder $withoutListings) => $withoutListings
                ->whereDoesntHave('categories')
                ->where(fn (Builder $directCategory) => $directCategory
                    ->whereNull('category_id')
                    ->orWhereHas('category', fn (Builder $category) => $category->publiclyVisible()))));
    }

    public function scopeWithPublicCardData(Builder $query): Builder
    {
        return $query->with([
            'brand:id,name',
            'variants' => fn ($variants) => $variants->available()
                ->select(['id', 'product_id', 'size_type', 'size_value', 'color']),
            'offers' => fn ($offers) => $offers
                ->select(['id', 'product_id', 'price', 'compare_at_price', 'currency'])
                ->sellable()
                ->with(['variants' => fn ($variants) => $variants
                    ->select(['id', 'product_offer_id', 'attributes'])
                    ->available()])
                ->orderBy('price')
                ->orderBy('id'),
        ]);
    }

    // سعر نهائي: لو في خصم يستخدمه
    public function getFinalPriceAttribute()
    {
        return $this->sale_price ?? $this->price;
    }
}
