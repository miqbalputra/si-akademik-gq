<?php

namespace App\Policies;

use App\Models\DiniyyahScore;
use App\Models\User;

class DiniyyahScorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'kabag_diniyyah', 'guru']);
    }

    public function view(User $user, DiniyyahScore $score): bool
    {
        return $this->canAccessAssessmentSet($user, $score);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'kabag_diniyyah', 'guru']);
    }

    public function update(User $user, DiniyyahScore $score): bool
    {
        if ($score->status === 'locked') {
            return false;
        }

        return $this->canAccessAssessmentSet($user, $score);
    }

    public function delete(User $user, DiniyyahScore $score): bool
    {
        return $user->hasAnyRole(['admin', 'kabag_diniyyah']) && $score->status !== 'locked';
    }

    private function canAccessAssessmentSet(User $user, DiniyyahScore $score): bool
    {
        return $user->can('inputScores', $score->assessmentSet);
    }
}
