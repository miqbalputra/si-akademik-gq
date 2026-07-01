<?php

namespace App\Filament\Resources\TahfidzUasDays\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TahfidzUasDaysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('day_number')->label('Hari')->sortable(),
                TextColumn::make('label')->label('Label'),
                TextColumn::make('test_date')->date('d M Y')->label('Tanggal'),
                TextColumn::make('academicTerm.name')->label('Periode'),
                    ->label('Periode Akademik')
                TextColumn::make('is_active')->badge(),
                    ->label('Status Aktif')
            ])
            ->defaultSort('day_number');
    }
}