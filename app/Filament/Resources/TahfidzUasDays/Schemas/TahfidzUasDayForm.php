<?php

namespace App\Filament\Resources\TahfidzUasDays\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TahfidzUasDayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('academic_term_id')
                    ->relationship('academicTerm', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('day_number')
                    ->label('Hari Ke-')
                    ->numeric()
                    ->required(),
                TextInput::make('label')
                    ->label('Label (contoh: Hari 1)'),
                DatePicker::make('test_date')
                    ->label('Tanggal Ujian'),
                Textarea::make('description')
                    ->label('Keterangan'),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}