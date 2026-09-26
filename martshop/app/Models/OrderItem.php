<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'merchant_order_id', 'product_id', 'product_offer_id', 'merchant_id',
        'product_name', 'price', 'qty', 'image', 'variant_snapshot', 'offer_snapshot',
    ];

    protected $hidden = ['offer_snapshot'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'variant_snapshot' => 'array',
            'offer_snapshot' => 'array',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function merchantOrder()
    {
        return $this->belongsTo(MerchantOrder::class);
    }

    public function reservation()
    {
        return $this->hasOne(StockReservation::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function offer()
    {
        return $this->belongsTo(ProductOffer::class, 'product_offer_id');
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}
