<?php

namespace App\Services;

use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\Rpp;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class RppAccessService
{
    /** @return Collection<int, DiniyyahTeacherAssignment> */
    public function activeAssignments(User $user): Collection
    {
        $teacherId = $user->teacher?->id;
        if (! $teacherId) {
            return new Collection;
        }

        $today = now()->toDateString();

        return DiniyyahTeacherAssignment::query()
            ->with(['classSubject.subject', 'classSubject.classroomTerm.classroom'])
            ->where('teacher_id', $teacherId)
            ->where(function ($query) use ($today): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $today);
            })
            ->where(function ($query) use ($today): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $today);
            })
            ->whereHas('classSubject', fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))
            ->get();
    }

    public function assignmentFor(User $user, int $classSubjectId): DiniyyahTeacherAssignment
    {
        abort_unless($user->hasRole('guru') && $user->teacher, 403, 'Akun Anda belum terhubung dengan data guru.');

        $assignment = $this->activeAssignments($user)
            ->firstWhere('diniyyah_class_subject_id', $classSubjectId);

        abort_unless($assignment, 403, 'Mapel dan kelas yang dipilih bukan penugasan aktif Anda.');

        return $assignment;
    }

    public function canManage(User $user, Rpp $rpp): bool
    {
        return $user->hasAnyRole(['admin', 'kabag_diniyyah'])
            || ($user->hasRole('guru') && $user->teacher?->id === $rpp->teacher_id);
    }

    public function canView(User $user, Rpp $rpp): bool
    {
        return $this->canManage($user, $rpp) || $user->hasRole('kepala_sekolah');
    }

    public function isManagement(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']);
    }

    public function classSubjectOptions(User $user): Collection
    {
        return $this->activeAssignments($user)
            ->map(fn (DiniyyahTeacherAssignment $assignment) => $assignment->classSubject)
            ->filter()
            ->unique('id')
            ->values();
    }
}
