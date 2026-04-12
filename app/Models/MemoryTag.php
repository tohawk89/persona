<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoryTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'persona_id',
        'target',
        'category',
        'value',
        'context',
        'importance',
        'last_consolidated_at',
    ];

    protected $casts = [
        'last_consolidated_at' => 'datetime',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }
}
