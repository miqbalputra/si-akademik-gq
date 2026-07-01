<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academic_term_id', 'classroom_term_id', 'student_id', 'roll_number', 'status'])]
class ClassEnrollment extends Model
{
    use HasFactory;

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function classroomTerm(): BelongsTo
    {
        return $this->belongsTo(ClassroomTerm::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function diniyyahScores(): HasMany
    {
        return $this->hasMany(DiniyyahScore::class);
    }

    public function diniyyahAssessmentResults(): HasMany
    {
        return $this->hasMany(DiniyyahAssessmentResult::class);
    }

    public function reportCards(): HasMany
    {
        return $this->hasMany(ReportCard::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(StudentAttendance::class);
    }
}
