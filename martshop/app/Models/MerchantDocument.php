<?php

namespace App\Models;

use App\Enums\MerchantDocumentStatus;
use App\Enums\MerchantDocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id', 'type', 'disk', 'path', 'original_name', 'mime_type',
        'size', 'sha256', 'status', 'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    protected $hidden = [
        'disk', 'path', 'sha256', 'original_name', 'mime_type', 'size',
        'reviewed_by', 'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => MerchantDocumentType::class,
            'status' => MerchantDocumentStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
