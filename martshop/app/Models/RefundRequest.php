<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RefundRequest extends Model
{
    protected $fillable = [
        'delivery_dispute_id', 'sale_entry_id', 'requested_by', 'reference', 'status',
        'amount', 'currency', 'amount_snapshot', 'reason', 'reviewed_by', 'review_reason',
        'reviewed_at', 'lock_version',
    ];

    protected $hidden = ['amount_snapshot', 'reason', 'review_reason'];

    protected function casts(): array
    {
        return ['status' => RefundStatus::class, 'amount' => 'integer', 'amount_snapshot' => 'array',
            'reviewed_at' => 'datetime', 'lock_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Refund requests cannot be deleted.'));
        static::updating(function (self $refund) {
            if ($refund->isDirty(['delivery_dispute_id', 'sale_entry_id', 'requested_by',
                'reference', 'amount', 'currency', 'amount_snapshot', 'reason'])) {
                throw new LogicException('Refund request snapshots cannot be changed.');
            }
        });
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(DeliveryDispute::class, 'delivery_dispute_id');
    }

    public function saleEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'sale_entry_id');
    }

    public function destinations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RefundDestination::class);
    }

    public function activeDestination(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RefundDestination::class)->whereNotNull('active_key');
    }

    public function transfer(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RefundTransfer::class)->whereNotNull('active_key');
    }

    public function transfers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RefundTransfer::class);
    }
}
