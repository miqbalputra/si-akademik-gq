<?php

namespace App\Filament\Resources\TahfidzWeeks\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TahfidzWeeksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('week_number')->label('Pekan')->sortable(),
                TextColumn::make('month_label')->label('Bulan')->sortable(),
                TextColumn::make('date_label')->label('Tanggal'),
                TextColumn::make('starts_on')->date('d M Y')->label('Mulai'),
                TextColumn::make('ends_on')->date('d M Y')->label('Selesai'),
                TextColumn::make('academicTerm.name')->label('Periode'),
            ])
            ->defaultSort('week_number');
    }
}