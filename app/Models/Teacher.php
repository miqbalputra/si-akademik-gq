<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'name', 'gender', 'niy', 'phone', 'whatsapp', 'email', 'address', 'started_at', 'status'])]
class Teacher extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['started_at' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teacherRoles(): HasMany
    {
        return $this->hasMany(TeacherRole::class);
    }

    public function homeroomAssignments(): HasMany
    {
        return $this->hasMany(HomeroomAssignment::class);
    }

    public function diniyyahTeacherAssignments(): HasMany
    {
        return $this->hasMany(DiniyyahTeacherAssignment::class);
    }

    public function tahfidzHalaqahs(): HasMany
    {
        return $this->hasMany(TahfidzHalaqah::class);
    }

    public function tahfidzAssistantHalaqahs(): HasMany
    {
        return $this->hasMany(TahfidzHalaqah::class, "assistant_teacher_id");
    }

    public function tasmiExaminerAssignments(): HasMany
    {
        return $this->hasMany(TasmiExaminerAssignment::class);
    }

    public function tasmiRecordsAsExaminer(): HasMany
    {
        return $this->hasMany(TasmiRecord::class, 'examiner_teacher_id');
    }

    public function rpps(): HasMany
    {
        return $this->hasMany(Rpp::class);
    }

    /**
     * Cek apakah guru ditugaskan sebagai PJ Tasmi' pada periode tertentu (atau periode aktif).
     */
    public function isTasmiExaminer(?int $academicTermId = null): bool
    {
        $query = $this->tasmiExaminerAssignments()->where('status', 'active');

        if ($academicTermId !== null) {
            $query->where('academic_term_id', $academicTermId);
        } else {
            $query->whereHas('academicTerm', fn ($q) => $q->where('is_active', true));
        }

        return $query->exists();
    }
}
