<?php

namespace App\Services;

use App\Models\ClassroomTerm;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DiniyyahLedgerAccess
{
    /** Wali kelas is a dated teaching assignment, never an administrative-role bypass. */
    public function classrooms(User $user): Builder
    {
        $teacher = $user->teacher;
        $query = ClassroomTerm::query();
        if (! $user->hasRole('guru') || ! $teacher || $teacher->status !== 'active') {
            return $query->whereIn('id', []);
        }

        return $query->whereHas('homeroomAssignments', fn ($q) => $q->where('teacher_id', $teacher->id)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', today()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', today())));
    }

    public function allows(User $user, int $classroomTermId): bool
    {
        return $this->classrooms($user)->whereKey($classroomTermId)->exists();
    }
}
