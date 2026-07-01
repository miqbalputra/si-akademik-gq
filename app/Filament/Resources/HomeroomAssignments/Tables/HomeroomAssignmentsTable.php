<?php

namespace App\Filament\Resources\HomeroomAssignments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HomeroomAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('classroomTerm.name')->label('Kelas Periode')
                    ->searchable(),
                TextColumn::make('teacher.name')->label('Nama Guru')
                    ->searchable(),
                TextColumn::make('starts_at')->label('Dimulai Pada')
                    ->date()
                    ->sortable(),
                TextColumn::make('ends_at')->label('Selesai Pada')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')->label('Dibuat Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label('Diperbarui Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
