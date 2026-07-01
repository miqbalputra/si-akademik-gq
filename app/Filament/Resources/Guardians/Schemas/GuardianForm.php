<?php

namespace App\Filament\Resources\Guardians\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GuardianForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Akun Pengguna')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Opsional: hubungkan ke akun login.'),
                TextInput::make('name')
                    ->label('Nama Wali Santri')
                    ->required(),
                Select::make('gender')
                    ->label('Jenis Kelamin')
                    ->options([
                        'male'   => 'Laki-laki',
                        'female' => 'Perempuan',
                    ])
                    ->required(),
                TextInput::make('nik')
                    ->label('NIK'),
                TextInput::make('phone')
                    ->label('Nomor Telepon')
                    ->tel(),
                TextInput::make('whatsapp')
                    ->label('WhatsApp'),
                TextInput::make('email')
                    ->label('Email')
                    ->email(),
                Textarea::make('address')
                    ->label('Alamat')
                    ->columnSpanFull(),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'active'   => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                    ])
                    ->required()
                    ->default('active'),
            ]);
    }
}
