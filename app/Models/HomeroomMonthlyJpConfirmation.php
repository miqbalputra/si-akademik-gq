<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'classroom_term_id', 'homeroom_teacher_id', 'teacher_id', 'period_start',
    'confirmed_jp', 'review_signature', 'is_override', 'override_reason', 'confirmed_at',
])]
class HomeroomMonthlyJpConfirmation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'confirmed_jp' => 'integer',
            'is_override' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }

    public function classroomTerm(): BelongsTo
    {
        return $this->belongsTo(ClassroomTerm::class);
    }

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'homeroom_teacher_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
