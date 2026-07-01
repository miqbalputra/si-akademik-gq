<?php

namespace App\Filament\Resources\DiniyyahAssessmentResults\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DiniyyahAssessmentResultsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('assessmentSet.title')->label('Assessment')->searchable(),
                TextColumn::make('classEnrollment.student.name')->label('Santri')->searchable(),
                TextColumn::make('daily_raw_score')->label('Nilai Mentah Harian')->numeric(),
                TextColumn::make('exam_raw_score')->label('Nilai Mentah Ujian')->numeric(),
                TextColumn::make('final_score')->label('Nilai Akhir')->numeric()->sortable(),
                IconColumn::make('is_complete')->label('Lengkap')->boolean(),
                IconColumn::make('is_passed')->label('Lulus')->boolean(),
                TextColumn::make('calculated_at')->label('Dihitung Pada')->dateTime()->sortable(),
            ])
            ->filters([
                //
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
