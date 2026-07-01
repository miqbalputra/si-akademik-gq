<?php

namespace App\Filament\Resources\DiniyyahSubjects\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class DiniyyahSubjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')->sortable(),
                    ->label('Urutan Tampil')
                TextColumn::make('code')->searchable()->sortable(),
                    ->label('Kode')
                TextColumn::make('name')->searchable()->sortable(),
                    ->label('Nama')
                TextColumn::make('default_assessment_method')->badge(),
                    ->label('Metode Penilaian Default')
                IconColumn::make('is_active')->boolean(),
                    ->label('Status Aktif')
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                    ->label('Diperbarui Pada')
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
