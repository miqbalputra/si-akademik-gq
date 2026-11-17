<?php

namespace App\Filament\Auth;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        // Filament has already logged out admin and invalidated the session.
        // Also expire the portal's remember cookie before switching accounts.
        Auth::guard('web')->logout();

        return redirect()->route('login');
    }
}
