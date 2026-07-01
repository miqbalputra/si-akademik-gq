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
                TextColumn::make('assessment_method')->label('Metode Penilaian')->badge(),
                TextColumn::make('kkm')->label('KKM')->numeric(),
                TextColumn::make('daily_weight')->label('Bobot Harian (%)')->suffix('%'),
                TextColumn::make('exam_weight')->label('Bobot Ujian (%)')->suffix('%'),
                IconColumn::make('appears_on_ledger')->label('Tampil di Leger')->boolean(),
                IconColumn::make('appears_on_report')->label('Tampil di Rapor')->boolean(),
                IconColumn::make('is_active')->label('Status Aktif')->boolean(),
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
