<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffDepartment extends Model
{
    protected $fillable = ['name', 'slug', 'is_active', 'manager_id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function staff(): HasMany
    {
        return $this->hasMany(User::class, 'staff_department_id');
    }
}
