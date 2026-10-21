<?php

namespace App\Services;

use App\Models\DiniyyahKkmRevision;
use App\Models\DiniyyahKkmScope;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DiniyyahKkmAccess
{
    public function manages(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'kabag_diniyyah']);
    }

    public function eligibleTeachers(DiniyyahKkmScope $scope): Builder
    {
        $term = $scope->academicTerm;
        $subjectIds = app(DiniyyahKkmResolver::class)->classSubjects($scope)->pluck('id');

        return Teacher::query()->where('status', 'active')->whereHas('user', fn ($q) => $q->role('guru'))
            ->whereHas('diniyyahTeacherAssignments', function ($q) use ($subjectIds, $term): void {
                $q->whereIn('diniyyah_class_subject_id', $subjectIds);
                if ($term->ends_at) {
                    $q->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $term->ends_at));
                }
                if ($term->starts_at) {
                    $q->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $term->starts_at));
                }
            });
    }

    public function writes(User $user, DiniyyahKkmScope $scope): bool
    {
        return $user->hasRole('guru') && $user->teacher?->id === $scope->assigned_teacher_id
            && $this->eligibleTeachers($scope)->whereKey($scope->assigned_teacher_id)->exists();
    }

    public function views(User $user, DiniyyahKkmScope $scope): bool
    {
        return $this->manages($user) || $this->writes($user, $scope)
            || ($scope->active_revision_id && $user->hasRole('guru') && $user->teacher
                && $this->eligibleTeachers($scope)->whereKey($user->teacher->id)->exists());
    }

    public function viewsRevision(User $user, DiniyyahKkmRevision $revision): bool
    {
        $scope = $revision->scope;
        if (! $this->views($user, $scope)) {
            return false;
        }

        return in_array($revision->status, ['approved', 'superseded'], true) || $this->manages($user) || $this->writes($user, $scope);
    }
}
