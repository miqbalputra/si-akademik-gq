<?php

namespace App\Filament\Resources\TahfidzHalaqahs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TahfidzHalaqahsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Halaqah')
                    ->searchable(),
                TextColumn::make('teacher.name')
                    ->label('Guru Pengampu')
                    ->searchable(),
                TextColumn::make('academicTerm.name')
                    ->label('Periode'),
                TextColumn::make('activeMembers_count')
                    ->counts('activeMembers')
                    ->label('Santri Aktif'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('name');
    }
}