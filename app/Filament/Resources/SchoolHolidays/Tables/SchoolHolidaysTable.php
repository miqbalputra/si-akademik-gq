<?php

namespace App\Filament\Resources\SchoolHolidays\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SchoolHolidaysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('holiday_date')
            ->columns([
                TextColumn::make('school.name')
                    ->label('Sekolah')
                    ->searchable(),
                TextColumn::make('academicTerm.name')
                    ->label('Periode')
                    ->formatStateUsing(fn ($state, $record) => trim(sprintf(
                        '%s - %s',
                        $record->academicTerm?->academicYear?->name ?? '-',
                        $state
                    )))
                    ->searchable(),
                TextColumn::make('holiday_date')
                    ->label('Tanggal')
                    ->date('d F Y')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Keterangan Libur')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('description')
                    ->label('Catatan')
                    ->limit(80)
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('academic_term_id')
                    ->label('Periode')
                    ->relationship('academicTerm', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
