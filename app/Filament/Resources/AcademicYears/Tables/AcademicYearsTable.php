<?php

namespace App\Filament\Resources\AcademicYears\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AcademicYearsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('school.name')->label('Nama Sekolah')
                    ->searchable(),
                TextColumn::make('name')->label('Nama')
                    ->searchable(),
                TextColumn::make('hijri_label')->label('Label Hijriah')
                    ->searchable(),
                TextColumn::make('gregorian_label')->label('Label Masehi')
                    ->searchable(),
                TextColumn::make('starts_at')->label('Dimulai Pada')
                    ->date()
                    ->sortable(),
                TextColumn::make('ends_at')->label('Selesai Pada')
                    ->date()
                    ->sortable(),
                IconColumn::make('is_active')->label('Status Aktif')
                    ->boolean(),
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
