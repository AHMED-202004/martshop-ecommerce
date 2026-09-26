<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class WithdrawalProof extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'withdrawal_request_id', 'disk', 'path', 'original_name', 'mime_type',
        'size', 'sha256', 'uploaded_by', 'created_at',
    ];

    protected $hidden = ['disk', 'path', 'original_name', 'mime_type', 'size', 'sha256'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'size' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Withdrawal proofs are immutable.'));
        static::deleting(fn () => throw new LogicException('Withdrawal proofs cannot be deleted.'));
    }

    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(WithdrawalRequest::class, 'withdrawal_request_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
