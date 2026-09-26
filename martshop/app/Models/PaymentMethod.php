<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    protected $fillable = [
        'name', 'slug', 'type', 'is_active', 'account_name', 'account_identifier',
        'instructions', 'logo_path', 'sort_order', 'metadata',
    ];

    protected $hidden = ['account_name', 'account_identifier', 'instructions', 'metadata'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('type', 'manual_transfer');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
