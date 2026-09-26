<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'user_id', 'payment_method_id', 'open_key', 'idempotency_key',
        'order_no', 'amount', 'currency', 'provider', 'provider_ref', 'sender_name',
        'sender_account', 'transferred_at', 'status', 'meta', 'reviewed_by',
        'reviewed_at', 'review_notes', 'accepted_at', 'rejected_at', 'lock_version',
    ];

    protected $hidden = [
        'open_key', 'idempotency_key', 'provider_ref', 'sender_name',
        'sender_account', 'meta', 'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PaymentStatus::class,
            'meta' => 'array',
            'transferred_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function proof(): HasOne
    {
        return $this->hasOne(PaymentProof::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function amountDecimal(): string
    {
        return number_format($this->amount / 100, 2, '.', '');
    }
}
