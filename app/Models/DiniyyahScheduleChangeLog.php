<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'teacher_id',
    'old_teacher_id',
    'diniyyah_teacher_assignment_id',
    'diniyyah_teaching_schedule_id',
    'entity_type',
    'event',
    'change_summary',
    'old_values',
    'new_values',
    'changed_by',
])]
class DiniyyahScheduleChangeLog extends Model
{
    use HasFactory;

    /**
     * Immutable audit log: hanya created_at, tidak ada updated_at. Mencegah
     * Eloquent mencoba set updated_at saat ::create() (kolom tidak ada).
     */
    public const UPDATED_AT = null;

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function oldTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'old_teacher_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(DiniyyahTeacherAssignment::class, 'diniyyah_teacher_assignment_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(DiniyyahTeachingSchedule::class, 'diniyyah_teaching_schedule_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}