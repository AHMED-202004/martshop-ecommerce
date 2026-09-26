<?php

namespace App\Models;

use App\Enums\StaffTaskPriority;
use App\Enums\StaffTaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StaffTask extends Model
{
    protected $fillable = [
        'title', 'description', 'task_type', 'priority', 'assigned_to', 'queue_department_id', 'assigned_by',
        'related_type', 'related_id', 'due_at', 'started_at', 'completed_at', 'status', 'notes',
        'version',
    ];

    protected $hidden = ['description', 'notes'];

    protected function casts(): array
    {
        return [
            'priority' => StaffTaskPriority::class,
            'status' => StaffTaskStatus::class,
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
