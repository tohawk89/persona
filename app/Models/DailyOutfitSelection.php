<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyOutfitSelection extends Model
{
    public $timestamps = false; // Only has created_at

    protected $fillable = [
        'persona_id',
        'date',
        'slot_name',
        'wardrobe_item_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function wardrobeItem(): BelongsTo
    {
        return $this->belongsTo(WardrobeItem::class);
    }
}
