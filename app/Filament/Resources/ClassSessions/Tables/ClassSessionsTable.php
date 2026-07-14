<?php

namespace App\Filament\Resources\ClassSessions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClassSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session_name')
                    ->label('Sesi / Jam')
                    ->searchable(),
                TextColumn::make('starts_at')
                    ->label('Waktu Mulai')
                    ->time('H:i')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Waktu Selesai')
                    ->time('H:i')
                    ->sortable(),
                IconColumn::make('is_break')
                    ->label('Istirahat')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
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
