<?php

namespace App\Filament\Resources\AcademicTerms\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AcademicTermsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('academicYear.name')->label('Tahun Ajaran')
                    ->searchable(),
                TextColumn::make('name')->label('Nama')
                    ->searchable(),
                TextColumn::make('semester')->label('Semester')
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
