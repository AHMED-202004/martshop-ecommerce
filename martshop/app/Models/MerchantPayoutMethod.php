<?php

namespace App\Models;

use App\Enums\MerchantPayoutMethodStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use LogicException;

class MerchantPayoutMethod extends Model
{
    protected $fillable = [
        'merchant_id', 'type', 'provider_name', 'account_name', 'account_identifier',
        'account_identifier_hash', 'last_four', 'status', 'submitted_at',
        'reviewed_by', 'reviewed_at', 'review_notes', 'disabled_at', 'lock_version',
    ];

    protected $hidden = ['account_name', 'account_identifier', 'account_identifier_hash', 'review_notes'];

    protected function casts(): array
    {
        return [
            'status' => MerchantPayoutMethodStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'disabled_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    protected function accountIdentifier(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => Crypt::decryptString($value),
            set: fn (string $value) => Crypt::encryptString(self::normalizeIdentifier($value)),
        );
    }

    protected static function booted(): void
    {
        static::saving(function (MerchantPayoutMethod $method) {
            $identifier = self::normalizeIdentifier($method->account_identifier);
            $method->account_identifier_hash = hash('sha256', mb_strtolower($identifier));
            $method->last_four = mb_substr(preg_replace('/\s+/u', '', $identifier), -4) ?: null;
        });
        static::deleting(fn () => throw new LogicException('Payout methods cannot be deleted; disable them instead.'));
    }

    public static function normalizeIdentifier(string $identifier): string
    {
        return preg_replace('/\s+/u', ' ', trim($identifier));
    }

    public function maskedIdentifier(): string
    {
        return '•••• '.($this->last_four ?: '----');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }
}
