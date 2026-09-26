<?php

namespace App\Models;

use App\Enums\StockReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StockReservation extends Model
{
    protected $fillable = [
        'order_id', 'merchant_order_id', 'order_item_id', 'product_offer_id',
        'offer_variant_id', 'quantity', 'status', 'offer_stock_was_tracked',
        'variant_stock_was_tracked', 'expires_at', 'consumed_at', 'released_at',
        'release_reason',
    ];

    protected $hidden = ['release_reason'];

    protected function casts(): array
    {
        return [
            'status' => StockReservationStatus::class,
            'quantity' => 'integer',
            'offer_stock_was_tracked' => 'boolean',
            'variant_stock_was_tracked' => 'boolean',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Stock reservation history cannot be deleted.'));
        static::updating(function (self $reservation) {
            if ($reservation->isDirty([
                'order_id', 'merchant_order_id', 'order_item_id', 'product_offer_id', 'offer_variant_id',
                'quantity', 'offer_stock_was_tracked', 'variant_stock_was_tracked', 'expires_at',
            ])) {
                throw new LogicException('Stock reservation identity and quantity are immutable.');
            }

            $original = StockReservationStatus::from((string) $reservation->getRawOriginal('status'));
            if ($original !== StockReservationStatus::Reserved) {
                throw new LogicException('Completed stock reservation history is immutable.');
            }

            if ($reservation->isDirty('status')) {
                $next = $reservation->status;
                $valid = match ($next) {
                    StockReservationStatus::Consumed => $reservation->consumed_at !== null
                        && $reservation->released_at === null && $reservation->release_reason === null,
                    StockReservationStatus::Released, StockReservationStatus::Expired => $reservation->released_at !== null
                        && $reservation->consumed_at === null && filled($reservation->release_reason),
                    default => false,
                };
                if (! $valid) {
                    throw new LogicException('Invalid stock reservation lifecycle transition.');
                }
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function merchantOrder(): BelongsTo
    {
        return $this->belongsTo(MerchantOrder::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(ProductOffer::class, 'product_offer_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(OfferVariant::class, 'offer_variant_id');
    }
}
