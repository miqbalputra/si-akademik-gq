<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TahfidzWeek extends Model
{
    protected $fillable = [
        'academic_term_id', 'week_number', 'month_label',
        'month_number', 'date_label', 'starts_on', 'ends_on', 'is_active',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_active' => 'boolean',
    ];

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function weeklyScores(): HasMany
    {
        return $this->hasMany(TahfidzWeeklyScore::class);
    }
}