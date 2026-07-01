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
                    ->label('Judul Ujian')
                TextColumn::make('sort_order')->sortable(),
                    ->label('Urutan Tampil')
                TextColumn::make('code')->searchable(),
                    ->label('Kode')
                TextColumn::make('name')->searchable(),
                    ->label('Nama')
                TextColumn::make('component_group')->badge(),
                    ->label('Grup Komponen')
                IconColumn::make('is_required')->boolean(),
                    ->label('Wajib')
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
