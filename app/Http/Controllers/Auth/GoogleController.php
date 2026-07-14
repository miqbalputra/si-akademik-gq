<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirectToGoogle(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Exception $e) {
            return redirect()->route('login')->withErrors(['email' => 'Gagal masuk menggunakan Google. Silakan coba lagi.']);
        }

        // Tolak akun Google yang emailnya belum diverifikasi Google.
        $emailVerified = filter_var($googleUser->user['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $emailVerified) {
            return redirect()->route('login')->withErrors([
                'email' => 'Email Google Anda belum terverifikasi. Verifikasi email Anda di akun Google lalu coba lagi.',
            ]);
        }

        // Batasi domain (hosted domain) bila dikonfigurasi (opsional, fail-open
        // saat kosong agar tidak memutus login yang sudah berjalan).
        $hostedDomain = config('services.google.hosted_domain');

        if ($hostedDomain !== null && $hostedDomain !== '') {
            $accountDomain = (string) ($googleUser->user['hd'] ?? '');
            $emailDomain = substr(strrchr($googleUser->getEmail(), '@'), 1);

            if ($accountDomain !== $hostedDomain && $emailDomain !== $hostedDomain) {
                return redirect()->route('login')->withErrors([
                    'email' => 'Akun Google Anda tidak berada dalam domain sekolah yang diizinkan.',
                ]);
            }
        }

        // Cari user berdasarkan email
        $user = User::where('email', $googleUser->getEmail())->first();

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'email' => 'Email Google Anda (' . $googleUser->getEmail() . ') tidak terdaftar di sistem. Silakan hubungi admin sekolah.'
            ]);
        }

        // Hubungkan google_id hanya bila belum terhubung. Jika sudah terhubung
        // ke Google ID lain, tolak — jangan menimpa binding yang ada (cegah
        // pengambilalihan akun via email yang diperebutkan).
        if ($user->google_id === null) {
            $user->update(['google_id' => $googleUser->getId()]);
        } elseif ($user->google_id !== $googleUser->getId()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Akun ini sudah terhubung dengan akun Google lain. Hubungi admin sekolah.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

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
