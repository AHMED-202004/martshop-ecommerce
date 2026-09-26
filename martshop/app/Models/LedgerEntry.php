<?php

namespace App\Models;

use App\Enums\LedgerEntryType;
use App\Enums\LedgerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LedgerEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'merchant_id', 'order_id', 'merchant_order_id', 'payment_id', 'withdrawal_request_id',
        'reverses_entry_id', 'entry_type', 'direction', 'amount', 'gross_amount',
        'commission_amount', 'fee_amount', 'net_amount', 'status', 'currency',
        'reference', 'idempotency_key', 'metadata', 'created_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_type' => LedgerEntryType::class,
            'status' => LedgerStatus::class,
            'amount' => 'integer',
            'gross_amount' => 'integer',
            'commission_amount' => 'integer',
            'fee_amount' => 'integer',
            'net_amount' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are immutable; create a reversal entry.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are append-only.'));
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function merchantOrder(): BelongsTo
    {
        return $this->belongsTo(MerchantOrder::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(WithdrawalRequest::class, 'withdrawal_request_id');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
