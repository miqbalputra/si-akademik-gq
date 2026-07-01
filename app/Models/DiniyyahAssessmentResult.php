<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'diniyyah_assessment_set_id',
    'class_enrollment_id',
    'daily_raw_score',
    'exam_raw_score',
    'daily_weighted_score',
    'exam_weighted_score',
    'final_score',
    'kkm',
    'is_complete',
    'is_passed',
    'calculated_at',
    'locked_at',
])]
class DiniyyahAssessmentResult extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'daily_raw_score' => 'decimal:2',
            'exam_raw_score' => 'decimal:2',
            'daily_weighted_score' => 'decimal:2',
            'exam_weighted_score' => 'decimal:2',
            'final_score' => 'decimal:2',
            'kkm' => 'decimal:2',
            'is_complete' => 'boolean',
            'is_passed' => 'boolean',
            'calculated_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function assessmentSet(): BelongsTo
    {
        return $this->belongsTo(DiniyyahAssessmentSet::class, 'diniyyah_assessment_set_id');
    }

    public function classEnrollment(): BelongsTo
    {
        return $this->belongsTo(ClassEnrollment::class);
    }
}
