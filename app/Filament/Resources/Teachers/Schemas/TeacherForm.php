<?php

namespace App\Filament\Resources\Teachers\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TeacherForm
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
                    ->label('Nama Guru')
                    ->required(),
                Select::make('gender')
                    ->label('Jenis Kelamin')
                    ->options([
                        'male'   => 'Laki-laki',
                        'female' => 'Perempuan',
                    ])
                    ->required(),
                TextInput::make('niy')
                    ->label('NIY (Nomor Induk Yayasan)')
                    ->helperText('Kode identitas guru di yayasan ini.'),
                TextInput::make('phone')
                    ->label('Nomor Telepon')
                    ->tel(),
                TextInput::make('whatsapp')
                    ->label('WhatsApp'),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->helperText('Dipakai juga sebagai email login dan untuk login Google.'),
                Textarea::make('address')
                    ->label('Alamat')
                    ->columnSpanFull(),
                DatePicker::make('started_at')
                    ->label('Tanggal Mulai Tugas'),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'active'   => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                    ])
                    ->required()
                    ->default('active'),

                // Section "Akun Login": membuat/mengubah akun login (User) yang
                // ter-link ke teacher. Field-field ini BUKAN kolom tabel teachers —
                // ditarik keluar di hook halaman Create/Edit lalu diproses oleh
                // TeacherAccountService. Email login memakai field Email di atas.
                Section::make('Akun Login')
                    ->description('Buat akun login guru. Email diambil dari field Email di atas. Guru bisa login via Username, Email, atau Google.')
                    ->schema([
                        TextInput::make('login_username')
                            ->label('Username')
                            ->helperText('Untuk login. Bisa dikosongkan jika login via email/Google.')
                            ->autocomplete('username'),
                        TextInput::make('login_password')
                            ->label('Password')
                            ->password()
                            ->helperText('Wajib saat membuat akun baru. Kosongkan saat edit jika tidak ingin mengubah.')
                            ->autocomplete('new-password'),
                    ])
                    ->columns(2),
            ]);
    }
}
