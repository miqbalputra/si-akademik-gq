<?php

namespace App\Filament\Resources\TahfidzUasCategories\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TahfidzUasCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')->label('Urutan')->sortable(),
                TextColumn::make('name')->label('Kategori')->searchable(),
                TextColumn::make('code')->label('Kode'),
                TextColumn::make('max_score')->label('Nilai Max'),
                TextColumn::make('academicTerm.name')->label('Periode'),
                TextColumn::make('is_active')->label('Status Aktif')->badge(),
            ])
            ->defaultSort('sort_order');
    }
}