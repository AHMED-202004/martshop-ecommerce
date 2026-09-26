<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id', 'name', 'slug', 'path', 'depth', 'status', 'sort_order',
        'image', 'icon', 'metadata',
    ];

    protected $hidden = ['metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function listedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot('is_primary', 'sort_order')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        $hiddenPaths = static::query()
            ->where(fn (Builder $status) => $status
                ->where('status', '!=', 'active')
                ->orWhereNull('status'))
            ->pluck('path');

        $query->active();
        foreach ($hiddenPaths as $hiddenPath) {
            $literalPath = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $hiddenPath);
            $query->where('categories.path', '!=', $hiddenPath)
                ->whereRaw("categories.path NOT LIKE ? ESCAPE '!'", [$literalPath.'/%']);
        }

        return $query;
    }

    public function ancestorsAndSelf(): array
    {
        $categories = [];
        $current = $this;
        while ($current) {
            array_unshift($categories, $current);
            $current = $current->parent;
        }

        return $categories;
    }
}
