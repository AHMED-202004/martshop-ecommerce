<?php

namespace App\Models;

use App\Enums\MerchantVerificationStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class Merchant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'location_id', 'legal_name', 'identity_number', 'phone',
        'date_of_birth', 'address', 'business_type', 'verification_status',
        'submitted_at', 'reviewed_at', 'reviewed_by', 'review_notes',
    ];

    protected $hidden = [
        'identity_number', 'identity_number_hash', 'phone', 'date_of_birth',
        'address', 'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'verification_status' => MerchantVerificationStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected function identityNumber(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => Crypt::decryptString($value),
            set: fn (string $value) => Crypt::encryptString(trim($value)),
        );
    }

    protected static function booted(): void
    {
        static::saving(function (Merchant $merchant) {
            $merchant->identity_number_hash = self::identityHash($merchant->identity_number);
        });
    }

    public static function identityHash(string $identityNumber): string
    {
        $normalized = mb_strtolower(preg_replace('/[\s-]+/u', '', trim($identityNumber)));

        return hash('sha256', $normalized);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MerchantDocument::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(ProductOffer::class);
    }

    public function productChangeRequests(): HasMany
    {
        return $this->hasMany(ProductChangeRequest::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MerchantOrder::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function payoutMethods(): HasMany
    {
        return $this->hasMany(MerchantPayoutMethod::class);
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }
}
