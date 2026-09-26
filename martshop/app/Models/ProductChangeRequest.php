<?php

namespace App\Models;

use App\Enums\ProductChangeRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductChangeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'merchant_id', 'proposed_changes', 'proposed_image_disk',
        'proposed_image_path', 'proposed_image_size', 'proposed_image_sha256',
        'status', 'open_key', 'submitted_at', 'reviewed_at',
        'reviewed_by', 'review_notes', 'lock_version',
    ];

    protected $hidden = ['proposed_image_disk', 'proposed_image_path', 'proposed_image_sha256'];

    protected function casts(): array
    {
        return [
            'proposed_changes' => 'array',
            'status' => ProductChangeRequestStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'proposed_image_size' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
