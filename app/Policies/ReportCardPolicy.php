<?php

namespace App\Policies;

use App\Models\ReportCard;
use App\Models\User;

class ReportCardPolicy
{
    public function view(User $user, ReportCard $reportCard): bool
    {
        if ($user->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah'])) {
            return true;
        }

        if (! $user->hasRole('wali_santri') || $reportCard->status !== 'published') {
            return false;
        }

        return $user->guardian?->students()
            ->where('students.id', $reportCard->student_id)
            ->wherePivot('can_login', true)
            ->exists() ?? false;
    }
}
