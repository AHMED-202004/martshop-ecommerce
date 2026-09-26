<?php

namespace App\Models;

use App\Enums\SupportTicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactMessage extends Model
{
    protected $hidden = ['contact', 'ref', 'message'];

    protected $fillable = [
        'user_id', 'topic', 'contact', 'ref', 'message', 'status', 'assigned_to', 'queue_department_id',
        'assigned_at', 'first_response_at', 'resolved_at', 'resolved_by', 'closed_at', 'closed_by', 'sla_due_at',
        'reopened_count', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupportTicketStatus::class,
            'assigned_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'reopened_count' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ContactMessageReply::class)->oldest('id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
