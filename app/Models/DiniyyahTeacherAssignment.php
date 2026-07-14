<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['diniyyah_class_subject_id', 'teacher_id', 'assignment_role', 'starts_at', 'ends_at', 'assigned_by'])]
class DiniyyahTeacherAssignment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(DiniyyahClassSubject::class, 'diniyyah_class_subject_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function journals(): HasMany
    {
        return $this->hasMany(DiniyyahClassJournal::class, 'diniyyah_teacher_assignment_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(DiniyyahTeachingSchedule::class, 'diniyyah_teacher_assignment_id');
    }
}
