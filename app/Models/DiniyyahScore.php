<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['diniyyah_assessment_set_id', 'diniyyah_score_component_id', 'class_enrollment_id', 'score', 'input_by', 'input_at', 'status', 'notes'])]
class DiniyyahScore extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'input_at' => 'datetime',
        ];
    }

    public function assessmentSet(): BelongsTo
    {
        return $this->belongsTo(DiniyyahAssessmentSet::class, 'diniyyah_assessment_set_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(DiniyyahScoreComponent::class, 'diniyyah_score_component_id');
    }

    public function classEnrollment(): BelongsTo
    {
        return $this->belongsTo(ClassEnrollment::class);
    }
}
