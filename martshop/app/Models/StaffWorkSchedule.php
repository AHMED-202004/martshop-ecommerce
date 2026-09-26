<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffWorkSchedule extends Model
{
    protected $fillable = ['user_id', 'day_of_week', 'is_working', 'starts_at', 'ends_at', 'timezone'];

    protected $hidden = ['user_id'];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_working' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
