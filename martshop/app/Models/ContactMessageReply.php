<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessageReply extends Model
{
    protected $fillable = ['contact_message_id', 'author_id', 'body', 'is_internal'];

    protected $hidden = ['body'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function contactMessage(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
