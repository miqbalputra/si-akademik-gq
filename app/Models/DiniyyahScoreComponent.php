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

    public function inputLabel(): string
    {
        return $this->code === 'nilai_tugas' && $this->name === 'Nilai Tugas' ? 'Nilai Tugas 1' : $this->name;
    }

    public function isDefaultInputColumn(): bool
    {
        return in_array($this->code, ['ulangan_harian_1', 'ulangan_harian_2', 'nilai_tugas', 'nilai_tugas_1'], true);
    }

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
