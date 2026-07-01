<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TahfidzUasDay extends Model
{
    protected $fillable = [
        'academic_term_id', 'day_number', 'test_date',
        'label', 'description', 'is_active',
    ];

    protected $casts = [
        'test_date' => 'date',
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