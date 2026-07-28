<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['diniyyah_teacher_assignment_id', 'substitute_teacher_id', 'date', 'session_hour', 'session_starts_at', 'session_ends_at', 'material', 'jp_count'])]
class DiniyyahClassJournal extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'jp_count' => 'integer',
        ];
    }

    public function teacherAssignment(): BelongsTo
    {
        return $this->belongsTo(DiniyyahTeacherAssignment::class, 'diniyyah_teacher_assignment_id');
    }

    /**
     * Guru pengganti yang benar-benar mengajar (nullable). Jika null, berarti
     * jurnal diisi oleh guru asli pemilik assignment.
     */
    public function substituteTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'substitute_teacher_id');
    }

    public function absences(): HasMany
    {
        return $this->hasMany(DiniyyahClassJournalAbsence::class);
    }

    /**
     * Guru yang JP-nya dihitung untuk penggajian: pengganti jika ada, jika tidak
     * maka guru pemilik assignment (guru asli).
     */
    public function effectiveTeacher(): ?Teacher
    {
        return $this->substituteTeacher ?? $this->teacherAssignment?->teacher;
    }
}
