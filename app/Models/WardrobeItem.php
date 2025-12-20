<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WardrobeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'persona_id',
        'slot_name',
        'description',
        'upper_body',
        'lower_body',
        'footwear',
        'accessories',
        'tags',
        'is_primary',
        'last_worn_at',
        'wear_count',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_primary' => 'boolean',
        'last_worn_at' => 'datetime',
        'wear_count' => 'integer',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }
}
