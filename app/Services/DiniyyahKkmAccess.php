<?php

namespace App\Services;

use App\Models\DiniyyahKkmRevision;
use App\Models\DiniyyahKkmScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DiniyyahKkmAccess
{
    public function __construct(private DiniyyahKkmAssignmentRoster $roster) {}

    public function manages(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'kabag_diniyyah']);
    }

    public function eligibleTeachers(DiniyyahKkmScope $scope): Builder
    {
        return $this->roster->eligibleTeachers($scope);
    }

    public function writes(User $user, DiniyyahKkmScope $scope): bool
    {
        return ! $scope->activeRevision?->is_school_fixed && $scope->subject?->schoolKkm() === null && $user->hasRole('guru') && $user->teacher?->id === $scope->assigned_teacher_id
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

        return in_array($revision->status, ['approved', 'superseded', 'school_fixed'], true) || $this->manages($user) || $this->writes($user, $scope);
    }
}
