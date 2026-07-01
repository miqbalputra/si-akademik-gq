<?php

namespace App\Filament\Resources\TahfidzWeeks\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TahfidzWeekForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('academic_term_id')
                    ->label('Periode Akademik')
                    ->relationship('academicTerm', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('week_number')
                    ->label('Nomor Pekan')
                    ->numeric()
                    ->required(),
                Select::make('month_number')
                    ->label('Bulan')
                    ->options([
                        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                    ]),
                TextInput::make('month_label')
                    ->label('Label Bulan'),
                TextInput::make('date_label')
                    ->label('Label Tanggal (contoh: Tgl 5-9)'),
                DatePicker::make('starts_on')
                    ->label('Tanggal Mulai'),
                DatePicker::make('ends_on')
                    ->label('Tanggal Selesai'),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}