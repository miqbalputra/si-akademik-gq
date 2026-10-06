<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserObserver
{
    public function deleted(User $user): void
    {
        activity('security')
            ->performedOn($user)
            ->causedBy(Auth::user())
            ->withProperties(['user_id' => $user->id, 'name' => $user->name])
            ->log('user_account_deactivated');
    }

    public function restored(User $user): void
    {
        activity('security')
            ->performedOn($user)
            ->causedBy(Auth::user())
            ->withProperties(['user_id' => $user->id, 'name' => $user->name])
            ->log('user_account_reactivated');
    }
}
