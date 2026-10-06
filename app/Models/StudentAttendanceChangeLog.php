<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_attendance_id', 'student_id', 'attendance_date', 'event', 'old_values', 'new_values', 'changed_by', 'changed_at'])]
class StudentAttendanceChangeLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'old_values' => 'array',
            'new_values' => 'array',
            'changed_at' => 'datetime',
        ];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(StudentAttendance::class, 'student_attendance_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
