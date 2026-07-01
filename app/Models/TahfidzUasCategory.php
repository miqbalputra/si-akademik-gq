<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TahfidzUasCategory extends Model
{
    protected $fillable = [
        'academic_term_id', 'code', 'name', 'description',
        'max_score', 'sort_order', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(TahfidzUasScore::class);
    }
}