<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'diniyyah_class_subject_id',
    'title',
    'tested_material',
    'assessment_method',
    'kkm',
    'daily_weight',
    'exam_weight',
    'appears_on_ledger',
    'appears_on_report',
    'sort_order',
    'status',
    'created_by',
    'updated_by',
])]
class DiniyyahAssessmentSet extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'kkm' => 'decimal:2',
            'appears_on_ledger' => 'boolean',
            'appears_on_report' => 'boolean',
        ];
    }

    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(DiniyyahClassSubject::class, 'diniyyah_class_subject_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(DiniyyahScoreComponent::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(DiniyyahScore::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(DiniyyahAssessmentResult::class);
    }

    public function validations(): HasMany
    {
        return $this->hasMany(DiniyyahScoreValidation::class);
    }
}
