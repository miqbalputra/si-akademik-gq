<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Login menerima email ATAU username: deteksi berdasarkan format email.
        $login = $data['login'];
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([$field => $login, 'password' => $data['password']], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'login' => 'Email/Username atau password tidak sesuai.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended($this->homePathFor($request->user()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function homePathFor(User $user): string
    {
        if ($user->hasRole('guru')) {
            return route('guru.dashboard', absolute: false);
        }

        if ($user->hasRole('wali_santri')) {
            return route('wali.dashboard', absolute: false);
        }

        if ($user->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah'])) {
            return '/admin';
        }

        return '/';
    }
}
