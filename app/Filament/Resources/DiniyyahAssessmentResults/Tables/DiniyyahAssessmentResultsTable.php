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
                    ->label('Nama Santri')
                TextColumn::make('daily_raw_score')->numeric(),
                    ->label('Nilai Mentah Harian')
                TextColumn::make('exam_raw_score')->numeric(),
                    ->label('Nilai Mentah Ujian')
                TextColumn::make('final_score')->numeric()->sortable(),
                    ->label('Nilai Akhir')
                IconColumn::make('is_complete')->boolean(),
                    ->label('Lengkap')
                IconColumn::make('is_passed')->boolean(),
                    ->label('Lulus')
                TextColumn::make('calculated_at')->dateTime()->sortable(),
                    ->label('Dihitung Pada')
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
