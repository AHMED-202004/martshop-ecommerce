<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RefundTransfer extends Model
{
    protected $fillable = ['refund_request_id', 'refund_destination_id', 'payment_id', 'destination_version',
        'recipient_snapshot', 'amount', 'merchant_amount', 'commission_amount', 'delivery_amount', 'service_amount',
        'currency', 'prepared_by', 'prepared_at', 'paid_by', 'paid_at', 'transferred_at', 'transaction_reference',
        'proof_path', 'proof_mime', 'proof_size', 'proof_sha256', 'active_key',
        'cancelled_by', 'cancelled_at', 'cancellation_reason'];

    protected $hidden = [
        'recipient_snapshot', 'transaction_reference', 'proof_path', 'proof_mime',
        'proof_size', 'proof_sha256', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return ['recipient_snapshot' => 'encrypted:array', 'amount' => 'integer', 'merchant_amount' => 'integer',
            'commission_amount' => 'integer', 'delivery_amount' => 'integer', 'service_amount' => 'integer',
            'destination_version' => 'integer', 'prepared_at' => 'datetime', 'paid_at' => 'datetime',
            'transferred_at' => 'datetime', 'proof_size' => 'integer',
            'cancelled_at' => 'datetime', 'cancellation_reason' => 'encrypted'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Refund transfer records cannot be deleted.'));
        static::updating(function (self $transfer) {
            if ($transfer->getOriginal('paid_at') || $transfer->getOriginal('cancelled_at') || $transfer->isDirty([
                'refund_request_id', 'refund_destination_id', 'payment_id', 'destination_version', 'recipient_snapshot',
                'amount', 'merchant_amount', 'commission_amount', 'delivery_amount', 'service_amount',
                'currency', 'prepared_by', 'prepared_at',
            ])) {
                throw new LogicException('Refund transfer snapshots and terminal records are immutable.');
            }
            if (($transfer->isDirty('active_key') && (! $transfer->cancelled_at || $transfer->active_key !== null))
                || ($transfer->cancelled_at && ($transfer->paid_at || $transfer->active_key !== null))) {
                throw new LogicException('Only an unpaid cancellation can release a transfer attempt.');
            }
        });
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(RefundRequest::class, 'refund_request_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(RefundDestination::class, 'refund_destination_id');
    }
}
