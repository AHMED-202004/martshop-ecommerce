<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class OfferVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_offer_id', 'sku', 'attributes', 'price', 'stock', 'status', 'source_key',
    ];

    protected $hidden = ['source_key'];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'price' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(ProductOffer::class, 'product_offer_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->active()
            ->where(fn (Builder $stock) => $stock->whereNull('stock')->orWhere('stock', '>', 0));
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }
}
