<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'subtotal', 'shipping_fee', 'total', 'currency', 'delivery_address_snapshot', 'status',
        'payment_method', 'payment_status', 'reservation_expires_at', 'checkout_token',
    ];

    protected $hidden = ['delivery_address_snapshot', 'checkout_token'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'delivery_address_snapshot' => 'array',
            'status' => OrderStatus::class,
            'payment_status' => OrderPaymentStatus::class,
            'reservation_expires_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function merchantOrders(): HasMany
    {
        return $this->hasMany(MerchantOrder::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
