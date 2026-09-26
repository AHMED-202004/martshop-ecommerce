<?php

namespace App\Models;

use App\Enums\MerchantVerificationStatus;
use App\Enums\ProductOfferStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'merchant_id', 'location_id', 'price', 'compare_at_price',
        'currency', 'stock', 'status', 'preparation_time_days', 'warranty',
        'last_confirmed_at', 'expires_at', 'source', 'source_key', 'metadata',
        'submitted_at', 'reviewed_at', 'reviewed_by', 'review_notes',
        'lock_version', 'paused_at', 'paused_by', 'pause_reason',
    ];

    protected $hidden = ['metadata', 'review_notes', 'source_key', 'pause_reason'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'last_confirmed_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
            'status' => ProductOfferStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'paused_at' => 'datetime',
            'lock_version' => 'integer',
            'stock' => 'integer',
        ];
    }

    public function scopeSellable(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->where(fn (Builder $query) => $query->whereNull('stock')->orWhere('stock', '>', 0))
            ->where(fn (Builder $query) => $query
                ->whereDoesntHave('variants')
                ->orWhereHas('variants', fn (Builder $variants) => $variants->available()))
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn (Builder $query) => $query->whereNull('merchant_id')
                ->orWhereHas('merchant', fn (Builder $merchant) => $merchant
                    ->where('verification_status', MerchantVerificationStatus::Verified->value)));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(OfferVariant::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function pauser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paused_by');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }
}
