<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PaymentProof extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payment_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256', 'created_at',
    ];

    protected $hidden = ['disk', 'path', 'original_name', 'mime_type', 'size', 'sha256'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'size' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment proofs are immutable.'));
        static::deleting(fn () => throw new LogicException('Payment proofs cannot be deleted.'));
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
