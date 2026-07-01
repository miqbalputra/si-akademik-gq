<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academic_year_id', 'name', 'semester', 'starts_at', 'ends_at', 'is_active'])]
class AcademicTerm extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classroomTerms(): HasMany
    {
        return $this->hasMany(ClassroomTerm::class);
    }

    public function classEnrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class);
    }

    public function diniyyahLedgerSnapshots(): HasMany
    {
        return $this->hasMany(DiniyyahLedgerSnapshot::class);
    }

    public function studentAttendances(): HasMany
    {
        return $this->hasMany(StudentAttendance::class);
    }

    public function schoolHolidays(): HasMany
    {
        return $this->hasMany(SchoolHoliday::class);
    }

    public function schoolEvents(): HasMany
    {
        return $this->hasMany(SchoolEvent::class);
    }
}
