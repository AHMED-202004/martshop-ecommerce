<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class DeliveryRating extends Model
{
    public $timestamps = false;
    protected $fillable = ['delivery_id', 'customer_id', 'rating', 'comment', 'created_at'];
    protected $hidden = ['customer_id', 'comment'];
    protected function casts(): array { return ['rating' => 'integer', 'created_at' => 'datetime']; }
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Delivery ratings are immutable.'));
        static::deleting(fn () => throw new LogicException('Delivery ratings cannot be deleted.'));
    }
    public function delivery(): BelongsTo { return $this->belongsTo(Delivery::class); }
    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }
}
