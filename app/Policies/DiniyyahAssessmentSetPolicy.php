<?php

namespace App\Policies;

use App\Models\DiniyyahAssessmentSet;
use App\Models\User;

class DiniyyahAssessmentSetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'kabag_diniyyah', 'guru']);
    }

    public function view(User $user, DiniyyahAssessmentSet $assessmentSet): bool
    {
        if ($user->hasAnyRole(['admin', 'kabag_diniyyah'])) {
            return true;
        }

        return $this->isAssignedTeacher($user, $assessmentSet);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'kabag_diniyyah']);
    }

    public function update(User $user, DiniyyahAssessmentSet $assessmentSet): bool
    {
        return $user->hasAnyRole(['admin', 'kabag_diniyyah']);
    }

    public function delete(User $user, DiniyyahAssessmentSet $assessmentSet): bool
    {
        return $user->hasRole('admin');
    }

    public function inputScores(User $user, DiniyyahAssessmentSet $assessmentSet): bool
    {
        if ($user->hasAnyRole(['admin', 'kabag_diniyyah'])) {
            return true;
        }

        return $this->isAssignedTeacher($user, $assessmentSet);
    }

    private function isAssignedTeacher(User $user, DiniyyahAssessmentSet $assessmentSet): bool
    {
        $teacher = $user->teacher;

        if (! $teacher) {
            return false;
        }

        return $assessmentSet->classSubject()
            ->whereHas('teacherAssignments', function ($query) use ($teacher) {
                $query->where('teacher_id', $teacher->id)
                    ->where(function ($query) {
                        $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()->toDateString());
                    })
                    ->where(function ($query) {
                        $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString());
                    });
            })
            ->exists();
    }
}
