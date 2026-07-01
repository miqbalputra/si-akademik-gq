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
                Select::make('academic_term_id')
                    ->relationship('academicTerm', 'name')
                    ->required(),
                Select::make('classroom_id')
                    ->relationship('classroom', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('capacity')
                    ->numeric(),
                TextInput::make('status')
                    ->required()
                    ->default('active'),
            ]);
    }
}
