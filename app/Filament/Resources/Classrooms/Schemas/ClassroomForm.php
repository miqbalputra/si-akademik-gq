<?php

namespace App\Filament\Resources\Classrooms\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ClassroomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Kelas')
                    ->required(),
                TextInput::make('level_name')
                    ->label('Tingkat'),
                Select::make('gender_group')
                    ->label('Gender Kelas')
                    ->options([
                        'male'   => 'Putra',
                        'female' => 'Putri',
                        'mixed'  => 'Campuran',
                    ])
                    ->required()
                    ->default('mixed'),
                TextInput::make('sort_order')
                    ->label('Urutan')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->required(),
            ]);
    }
}
