<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academic_term_id', 'classroom_id', 'name', 'capacity', 'status'])]
class ClassroomTerm extends Model
{
    use HasFactory;

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class);
    }

    public function homeroomAssignments(): HasMany
    {
        return $this->hasMany(HomeroomAssignment::class);
    }

    public function diniyyahClassSubjects(): HasMany
    {
        return $this->hasMany(DiniyyahClassSubject::class);
    }

    public function diniyyahLedgerSnapshots(): HasMany
    {
        return $this->hasMany(DiniyyahLedgerSnapshot::class);
    }

    public function studentAttendances(): HasMany
    {
        return $this->hasMany(StudentAttendance::class);
    }

    public function schoolEvents(): BelongsToMany
    {
        return $this->belongsToMany(SchoolEvent::class, 'school_event_targets')
            ->withTimestamps();
    }
}
