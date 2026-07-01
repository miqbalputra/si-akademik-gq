<?php

namespace App\Filament\Resources\DiniyyahScoreComponents\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DiniyyahScoreComponentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('assessmentSet.title')->label('Assessment')->searchable(),
                TextColumn::make('sort_order')->label('Urutan Tampil')->sortable(),
                TextColumn::make('code')->label('Kode')->searchable(),
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('component_group')->label('Grup Komponen')->badge(),
                IconColumn::make('is_required')->label('Wajib')->boolean(),
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
