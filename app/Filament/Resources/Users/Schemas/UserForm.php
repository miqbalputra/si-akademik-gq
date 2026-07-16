<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required(),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique(User::class, 'email', ignoreRecord: true)
                    ->helperText('Dipakai untuk login (email) & login Google. Jika akun ini terhubung ke data Guru/Wali, email di sana akan ikut diperbarui agar sama.'),

                TextInput::make('username')
                    ->label('Username')
                    ->nullable()
                    ->unique(User::class, 'username', ignoreRecord: true)
                    ->helperText('Untuk login via /login. Bisa dikosongkan jika login via email/Google.'),

                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->autocomplete('new-password')
                    // Hanya simpan jika diisi. Saat edit, kosong = tidak mengubah.
                    // Password User di-cast "hashed", jangan Hash::make manual.
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->helperText('Wajib saat membuat akun baru. Kosongkan saat edit jika tidak ingin mengubah.'),

                // Field virtual: bukan kolom/relasi model. Di-handle di hook
                // halaman via Spatie syncRoles (agar cache permission di-clear).
                Select::make('user_roles')
                    ->label('Peran')
                    ->multiple()
                    ->options(Role::where('guard_name', 'web')->pluck('name', 'name'))
                    ->helperText('Peran guru & wali_santri tidak bisa akses /admin (mereka login via /login).')
                    ->columnSpanFull(),
            ]);
    }
}