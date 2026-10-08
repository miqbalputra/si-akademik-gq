<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    public function mount(): void
    {
        if (Filament::auth()->check()) {
            session()->forget('url.intended');
        }

        parent::mount();
    }

    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response !== null) {
            session()->forget('url.intended');
        }

        return $response;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Masuk ke Portal Admin';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Selamat Datang';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Silakan masuk menggunakan akun yang diberikan administrator.';
    }
}
