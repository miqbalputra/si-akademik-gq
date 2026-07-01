<?php

namespace App\Filament\Resources\ClassroomTerms\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClassroomTermsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('academicTerm.name')->label('Periode Akademik')
                    ->searchable(),
                TextColumn::make('classroom.name')->label('Kelas Master')
                    ->searchable(),
                TextColumn::make('name')->label('Nama')
                    ->searchable(),
                TextColumn::make('capacity')->label('Kapasitas')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')->label('Status')
                    ->searchable(),
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
