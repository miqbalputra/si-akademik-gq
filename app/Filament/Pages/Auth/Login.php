<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
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
