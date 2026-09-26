<?php

namespace App\Models;

use App\Enums\RefundDestinationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RefundDestination extends Model
{
    protected $fillable = ['refund_request_id', 'active_key', 'recipient_snapshot', 'last_four',
        'status', 'submitted_by', 'reviewed_by', 'review_notes', 'reviewed_at', 'revoked_by', 'revoked_at', 'lock_version'];

    protected $hidden = ['recipient_snapshot', 'review_notes', 'active_key'];

    protected function casts(): array
    {
        return ['recipient_snapshot' => 'encrypted:array', 'review_notes' => 'encrypted',
            'status' => RefundDestinationStatus::class, 'reviewed_at' => 'datetime', 'revoked_at' => 'datetime', 'lock_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Refund destination history cannot be deleted.'));
        static::updating(function (self $destination) {
            if ($destination->isDirty(['refund_request_id', 'recipient_snapshot', 'last_four', 'submitted_by'])) {
                throw new LogicException('Recipient snapshots are immutable; revoke and register a new destination.');
            }
        });
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(RefundRequest::class, 'refund_request_id');
    }

    public function maskedIdentifier(): string
    {
        return '•••• '.$this->last_four;
    }
}
