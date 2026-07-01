<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['academic_term_id', 'classroom_term_id', 'class_enrollment_id', 'student_id', 'report_type', 'status', 'issue_date', 'total_score', 'average_score', 'rank_in_class', 'homeroom_note', 'published_at', 'published_by', 'locked_at', 'locked_by'])]
class ReportCard extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'total_score' => 'decimal:2',
            'average_score' => 'decimal:2',
            'published_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function classroomTerm(): BelongsTo
    {
        return $this->belongsTo(ClassroomTerm::class);
    }

    public function classEnrollment(): BelongsTo
    {
        return $this->belongsTo(ClassEnrollment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ReportCardLine::class);
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(ReportCardAttendance::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ReportCardSnapshot::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(ReportCardSignature::class);
    }
}
