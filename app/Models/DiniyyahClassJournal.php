<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['diniyyah_teacher_assignment_id', 'date', 'session_hour', 'material', 'jp_count'])]
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

    public function absences(): HasMany
    {
        return $this->hasMany(DiniyyahClassJournalAbsence::class);
    }
}
