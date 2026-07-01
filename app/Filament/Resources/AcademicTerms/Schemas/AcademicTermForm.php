<?php

namespace App\Filament\Resources\AcademicTerms\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AcademicTermForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('academic_year_id')
                    ->relationship('academicYear', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('semester')
                    ->required(),
                DatePicker::make('starts_at'),
                DatePicker::make('ends_at'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
