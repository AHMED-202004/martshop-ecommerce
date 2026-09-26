<?php

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class WithdrawalRequest extends Model
{
    protected $fillable = [
        'merchant_id', 'merchant_payout_method_id', 'amount', 'currency', 'status',
        'reference', 'idempotency_key', 'destination_snapshot', 'requested_at',
        'reviewed_by', 'reviewed_at', 'review_notes', 'approved_at', 'rejected_at',
        'paid_at', 'transferred_at', 'transaction_reference', 'lock_version',
    ];

    protected $hidden = [
        'idempotency_key', 'destination_snapshot', 'transaction_reference', 'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => WithdrawalStatus::class,
            'destination_snapshot' => 'array',
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'paid_at' => 'datetime',
            'transferred_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Withdrawal requests are financial records and cannot be deleted.'));
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function payoutMethod(): BelongsTo
    {
        return $this->belongsTo(MerchantPayoutMethod::class, 'merchant_payout_method_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function proof(): HasOne
    {
        return $this->hasOne(WithdrawalProof::class);
    }
}
