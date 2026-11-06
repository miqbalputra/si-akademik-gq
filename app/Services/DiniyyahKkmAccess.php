<?php

namespace App\Services;

use App\Models\DiniyyahKkmRevision;
use App\Models\DiniyyahKkmScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DiniyyahKkmAccess
{
    public const OFFICIAL_STATUSES = ['approved', 'superseded', 'school_fixed'];

    public function __construct(private DiniyyahKkmAssignmentRoster $roster) {}

    public function manages(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'kabag_diniyyah']);
    }

    public function eligibleTeachers(DiniyyahKkmScope $scope): Builder
    {
        return $this->roster->eligibleTeachers($scope);
    }

    public function reviews(User $user): bool
    {
        return $this->manages($user);
    }

    public function writes(User $user, DiniyyahKkmScope $scope): bool
    {
        return $this->teacherManaged($scope) && $user->hasRole('guru') && $user->teacher && $user->teacher->id === $scope->assigned_teacher_id
            && $this->eligibleTeachers($scope)->whereKey($scope->assigned_teacher_id)->exists();
    }

    public function teacherManaged(DiniyyahKkmScope $scope): bool
    {
        return ! $scope->activeRevision?->is_school_fixed && $scope->subject?->schoolKkm() === null;
    }

    public function views(User $user, DiniyyahKkmScope $scope, bool $manager = false): bool
    {
        return ($manager && $this->manages($user)) || $this->writes($user, $scope)
            || ($scope->active_revision_id && $user->hasRole('guru') && $user->teacher
                && $this->eligibleTeachers($scope)->whereKey($user->teacher->id)->exists());
    }

    public function viewsRevision(User $user, DiniyyahKkmRevision $revision, bool $manager = false): bool
    {
        $scope = $revision->scope;
        if (! $this->views($user, $scope, $manager)) {
            return false;
        }

        return in_array($revision->status, self::OFFICIAL_STATUSES, true) || ($manager && $this->manages($user)) || $this->writes($user, $scope);
    }
}
