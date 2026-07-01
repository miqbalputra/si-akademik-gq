<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['diniyyah_assessment_set_id', 'validated_by', 'status', 'validated_at', 'notes'])]
class DiniyyahScoreValidation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['validated_at' => 'datetime'];
    }

    public function assessmentSet(): BelongsTo
    {
        return $this->belongsTo(DiniyyahAssessmentSet::class, 'diniyyah_assessment_set_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
