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
                TextColumn::make('daily_raw_score')->numeric(),
                TextColumn::make('exam_raw_score')->numeric(),
                TextColumn::make('final_score')->numeric()->sortable(),
                IconColumn::make('is_complete')->boolean(),
                IconColumn::make('is_passed')->boolean(),
                TextColumn::make('calculated_at')->dateTime()->sortable(),
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
