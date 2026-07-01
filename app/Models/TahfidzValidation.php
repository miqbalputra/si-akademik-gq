<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TahfidzValidation extends Model
{
    protected $fillable = [
        'tahfidz_halaqah_id', 'validated_by', 'status',
        'validated_at', 'notes',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
    ];

    public function halaqah(): BelongsTo
    {
        return $this->belongsTo(TahfidzHalaqah::class);
    }
}