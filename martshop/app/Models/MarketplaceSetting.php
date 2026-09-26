<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'description', 'updated_by'];

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
