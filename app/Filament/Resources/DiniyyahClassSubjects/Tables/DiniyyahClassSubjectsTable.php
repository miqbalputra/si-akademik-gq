<?php

namespace App\Filament\Resources\DiniyyahClassSubjects\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class DiniyyahClassSubjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('classroomTerm.name')->label('Kelas')->searchable()->sortable(),
                TextColumn::make('subject.name')->label('Mapel')->searchable()->sortable(),
                    ->label('Mata Pelajaran')
                TextColumn::make('assessment_method')->badge(),
                    ->label('Metode Penilaian')
                TextColumn::make('kkm')->numeric(),
                    ->label('KKM')
                TextColumn::make('daily_weight')->suffix('%'),
                    ->label('Bobot Harian (%)')
                TextColumn::make('exam_weight')->suffix('%'),
                    ->label('Bobot Ujian (%)')
                IconColumn::make('appears_on_ledger')->boolean(),
                    ->label('Tampil di Leger')
                IconColumn::make('appears_on_report')->boolean(),
                    ->label('Tampil di Rapor')
                IconColumn::make('is_active')->boolean(),
                    ->label('Status Aktif')
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
