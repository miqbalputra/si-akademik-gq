<?php

namespace App\Filament\Resources\DiniyyahScoreValidations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DiniyyahScoreValidationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('assessmentSet.title')->label('Assessment')->searchable(),
                TextColumn::make('validator.name')->label('Validator')->searchable(),
                    ->label('Nama Validator')
                TextColumn::make('status')->badge(),
                    ->label('Status')
                TextColumn::make('validated_at')->dateTime()->sortable(),
                    ->label('Divalidasi Pada')
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
