<?php

namespace App\Filament\Resources\AcademicYears\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AcademicYearForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('school_id')->label('Sekolah')
                    ->relationship('school', 'name')
                    ->required(),
                TextInput::make('name')->label('Nama')
                    ->required(),
                TextInput::make('hijri_label')->label('Label Hijriah'),
                TextInput::make('gregorian_label')->label('Label Masehi'),
                DatePicker::make('starts_at')->label('Dimulai Pada'),
                DatePicker::make('ends_at')->label('Selesai Pada'),
                Toggle::make('is_active')->label('Status Aktif')
                    ->required(),
            ]);
    }
}
