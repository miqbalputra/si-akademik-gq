<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TahfidzUasResult extends Model
{
    protected $fillable = [
        'academic_term_id', 'student_id', 'tahfidz_halaqah_id',
        'juz_tested', 'daily_totals', 'final_score', 'predicate',
        'is_complete', 'calculated_at', 'status', 'validated_by', 'validated_at',
    ];

    protected $casts = [
        'daily_totals' => 'array',
        'final_score' => 'decimal:2',
        'is_complete' => 'boolean',
        'calculated_at' => 'datetime',
        'validated_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function halaqah(): BelongsTo
    {
        return $this->belongsTo(TahfidzHalaqah::class);
    }
}