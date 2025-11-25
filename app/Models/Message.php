<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'user_id',
        'persona_id',
        'sender_type',
        'content',
        'image_path',
        'is_event_trigger',
    ];

    protected $casts = [
        'is_event_trigger' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }
}
