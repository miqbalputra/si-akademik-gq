<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirectToGoogle(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Exception $e) {
            return redirect()->route('login')->withErrors(['email' => 'Gagal masuk menggunakan Google. Silakan coba lagi.']);
        }

        // Cari user berdasarkan email
        $user = User::where('email', $googleUser->getEmail())->first();

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'email' => 'Email Google Anda (' . $googleUser->getEmail() . ') tidak terdaftar di sistem. Silakan hubungi admin sekolah.'
            ]);
        }

        // Link google_id jika belum terhubung
        if ($user->google_id !== $googleUser->getId()) {
            $user->update([
                'google_id' => $googleUser->getId(),
            ]);
        }

        Auth::login($user);

        // Redirect ke dashboard yang sesuai berdasarkan role
        if ($user->hasRole('wali_santri')) {
            return redirect()->route('wali.dashboard');
        } elseif ($user->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah', 'kabag_tahfidz'])) {
            return redirect('/admin');
        } elseif ($user->hasRole('guru')) {
            return redirect()->route('guru.diniyyah-scores.index');
        }

        return redirect('/');
    }
}
