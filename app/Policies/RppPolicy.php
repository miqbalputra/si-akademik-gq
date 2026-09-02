<?php

namespace App\Policies;

use App\Models\Rpp;
use App\Models\User;

class RppPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah', 'guru']);
    }

    public function view(User $user, Rpp $rpp): bool
    {
        return $user->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah'])
            || ($user->hasRole('guru') && $user->teacher?->id === $rpp->teacher_id);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('guru') && $user->teacher !== null;
    }

    public function update(User $user, Rpp $rpp): bool
    {
        return $user->hasAnyRole(['admin', 'kabag_diniyyah'])
            || ($user->hasRole('guru') && $user->teacher?->id === $rpp->teacher_id);
    }

    public function delete(User $user, Rpp $rpp): bool
    {
        return $this->update($user, $rpp);
    }

    public function restore(User $user, Rpp $rpp): bool
    {
        return $this->update($user, $rpp);
    }
}
