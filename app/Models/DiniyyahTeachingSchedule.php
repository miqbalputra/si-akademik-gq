<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['diniyyah_teacher_assignment_id', 'class_session_id', 'day_of_week'])]
class DiniyyahTeachingSchedule extends Model
{
    use HasFactory;

    public function teacherAssignment(): BelongsTo
    {
        return $this->belongsTo(DiniyyahTeacherAssignment::class, 'diniyyah_teacher_assignment_id');
    }

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function scheduleChangeLogs(): HasMany
    {
        return $this->hasMany(DiniyyahScheduleChangeLog::class, 'diniyyah_teaching_schedule_id');
    }
}
