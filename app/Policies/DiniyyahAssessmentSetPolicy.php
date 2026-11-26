<?php

namespace App\Policies;

use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\ReportCard;
use App\Models\User;
use App\Services\DiniyyahAssessmentAccess;

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
        return $user->hasAnyRole(['admin', 'kabag_diniyyah']) && $this->classIsOpen($assessmentSet);
    }

    public function delete(User $user, DiniyyahAssessmentSet $assessmentSet): bool
    {
        return $user->hasRole('admin') && $this->classIsOpen($assessmentSet);
    }

    public function restore(User $user, DiniyyahAssessmentSet $assessmentSet): bool
    {
        return $this->update($user, $assessmentSet);
    }

    public function inputScores(User $user, DiniyyahAssessmentSet $assessmentSet): bool
    {
        return app(DiniyyahAssessmentAccess::class)->classSubjects($user)
            ->whereKey($assessmentSet->diniyyah_class_subject_id)->exists();
    }

    private function classIsOpen(DiniyyahAssessmentSet $assessmentSet): bool
    {
        $classroomId = $assessmentSet->classSubject?->classroom_term_id;

        return $classroomId !== null
            && ! DiniyyahLedgerSnapshot::where('classroom_term_id', $classroomId)->whereIn('status', ['locked', 'published'])->exists()
            && ! ReportCard::where('classroom_term_id', $classroomId)->whereIn('status', ['locked', 'published'])->exists();
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
