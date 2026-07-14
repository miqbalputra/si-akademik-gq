<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
