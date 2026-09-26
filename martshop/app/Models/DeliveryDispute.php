<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class DeliveryDispute extends Model
{
    protected $fillable = ['delivery_id', 'opened_by', 'reason', 'status', 'closed_by', 'close_reason', 'closed_at'];

    protected $hidden = ['reason', 'close_reason'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Disputes cannot be deleted.'));
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function refundRequest(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RefundRequest::class);
    }
}
