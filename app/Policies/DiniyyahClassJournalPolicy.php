<?php

namespace App\Policies;

use App\Models\DiniyyahClassJournal;
use App\Models\User;

class DiniyyahClassJournalPolicy
{
    private const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];
    private const MANAGE_ROLES = ['admin', 'kabag_diniyyah'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(self::VIEW_ROLES);
    }

    public function view(User $user, DiniyyahClassJournal $journal): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::MANAGE_ROLES);
    }

    public function update(User $user, DiniyyahClassJournal $journal): bool
    {
        return $this->create($user) && $journal->status !== 'validated';
    }

    public function delete(User $user, DiniyyahClassJournal $journal): bool
    {
        return $this->create($user) && $journal->status !== 'validated';
    }

    public function deleteAny(User $user): bool
    {
        return $this->create($user);
    }
}
