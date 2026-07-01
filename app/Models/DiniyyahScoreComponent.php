<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['diniyyah_assessment_set_id', 'code', 'name', 'component_group', 'sort_order', 'is_required'])]
class DiniyyahScoreComponent extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function assessmentSet(): BelongsTo
    {
        return $this->belongsTo(DiniyyahAssessmentSet::class, 'diniyyah_assessment_set_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(DiniyyahScore::class);
    }
}
