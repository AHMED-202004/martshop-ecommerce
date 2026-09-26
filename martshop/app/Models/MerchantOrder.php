<?php

namespace App\Models;

use App\Enums\MerchantOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MerchantOrder extends Model
{
    protected $fillable = [
        'order_id', 'merchant_id', 'group_key', 'status', 'product_subtotal',
        'delivery_fee', 'service_fee', 'commission_amount', 'total', 'currency',
        'origin_location_id', 'commission_snapshot', 'delivery_snapshot',
        'confirmed_at', 'rejected_at', 'expired_at', 'rejection_reason',
    ];

    protected $hidden = ['commission_snapshot', 'delivery_snapshot', 'rejection_reason'];

    protected function casts(): array
    {
        return [
            'status' => MerchantOrderStatus::class,
            'product_subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'commission_snapshot' => 'array',
            'delivery_snapshot' => 'array',
            'confirmed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function originLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }
}
