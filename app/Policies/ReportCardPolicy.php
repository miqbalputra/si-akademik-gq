<?php

namespace App\Policies;

use App\Models\ReportCard;
use App\Models\User;

class ReportCardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']);
    }

    public function view(User $user, ReportCard $reportCard): bool
    {
        if (! $user->hasRole('admin') && ! $reportCard->student()->where('status', 'active')->exists()) {
            return false;
        }

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

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'kabag_diniyyah']);
    }

    public function update(User $user, ReportCard $reportCard): bool
    {
        return $this->create($user) && $reportCard->status === 'draft';
    }

    public function delete(User $user, ReportCard $reportCard): bool
    {
        return $this->update($user, $reportCard);
    }

    public function deleteAny(User $user): bool
    {
        return $this->create($user);
    }
}
