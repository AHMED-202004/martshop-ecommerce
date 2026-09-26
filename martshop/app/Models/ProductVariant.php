<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id','size_type','size_value','color','in_stock',
    ];

    protected function casts(): array
    {
        return ['in_stock' => 'boolean'];
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('in_stock', true);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
