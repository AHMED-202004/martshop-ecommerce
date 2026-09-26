<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class DeliveryEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'delivery_id', 'event_type', 'from_status', 'to_status', 'actor_id',
        'note', 'metadata', 'created_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Delivery events are append-only.'));
        static::deleting(fn () => throw new LogicException('Delivery events are append-only.'));
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
