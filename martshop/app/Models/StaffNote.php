<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StaffNote extends Model
{
    protected $fillable = ['staff_id', 'author_id', 'body'];

    protected $hidden = ['body'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Staff notes are append-only.'));
        static::deleting(fn () => throw new LogicException('Staff notes are append-only.'));
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
