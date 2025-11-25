<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Persona extends Model implements HasMedia
{
    use InteractsWithMedia;
    protected $fillable = [
        'user_id',
        'name',
        'system_prompt',
        'physical_traits',
        'wake_time',
        'sleep_time',
        'is_active',
    ];

    protected $casts = [
        'wake_time' => 'string',
        'sleep_time' => 'string',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function memoryTags(): HasMany
    {
        return $this->hasMany(MemoryTag::class);
    }

    public function eventSchedules(): HasMany
    {
        return $this->hasMany(EventSchedule::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
