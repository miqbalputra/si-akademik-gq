<?php

namespace App\Filament\Resources\ClassroomTerms\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClassroomTermForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('academic_term_id')->label('Periode Akademik')
                    ->relationship('academicTerm', 'name')
                    ->required(),
                Select::make('classroom_id')->label('Kelas Master')
                    ->relationship('classroom', 'name')
                    ->required(),
                TextInput::make('name')->label('Nama')
                    ->required(),
                TextInput::make('capacity')->label('Kapasitas')
                    ->numeric(),
                TextInput::make('status')->label('Status')
                    ->required()
                    ->default('active'),
            ]);
    }
}
