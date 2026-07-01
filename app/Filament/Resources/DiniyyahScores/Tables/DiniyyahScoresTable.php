<?php

namespace App\Filament\Resources\DiniyyahScores\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class DiniyyahScoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with([
                'assessmentSet', 
                'component', 
                'classEnrollment.student'
            ]))
            ->columns([
                TextColumn::make('assessmentSet.title')->label('Assessment')->searchable(),
                TextColumn::make('component.name')->label('Komponen')->searchable(),
                TextColumn::make('classEnrollment.student.name')->label('Santri')->searchable(),
                    ->label('Nama Santri')
                TextColumn::make('score')->numeric()->sortable(),
                    ->label('Nilai')
                TextColumn::make('status')->badge(),
                    ->label('Status')
                TextColumn::make('input_at')->dateTime()->sortable(),
                    ->label('Diinput Pada')
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
