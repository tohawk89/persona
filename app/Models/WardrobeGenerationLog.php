<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WardrobeGenerationLog extends Model
{
    public $timestamps = false;

    protected $table = 'wardrobe_generation_log';

    protected $fillable = [
        'persona_id',
        'slot_name',
        'tags_used',
        'outfits_generated',
        'generated_at',
    ];

    protected $casts = [
        'tags_used' => 'array',
        'outfits_generated' => 'integer',
        'generated_at' => 'datetime',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }
}
