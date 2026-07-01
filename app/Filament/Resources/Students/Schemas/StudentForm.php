<?php

namespace App\Filament\Resources\Students\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required(),
                Select::make('gender')
                    ->label('Jenis Kelamin')
                    ->options([
                        'male'   => 'Laki-laki',
                        'female' => 'Perempuan',
                    ])
                    ->required(),
                TextInput::make('nis')
                    ->label('NIS')
                    ->required()
                    ->numeric()
                    ->maxLength(10)
                    ->unique(ignoreRecord: true),
                TextInput::make('nik')
                    ->label('NIK')
                    ->numeric()
                    ->maxLength(16)
                    ->unique(ignoreRecord: true),
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
