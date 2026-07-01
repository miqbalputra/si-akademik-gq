<?php

namespace App\Filament\Resources\TahfidzUasCategories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TahfidzUasCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('academic_term_id')->label('Periode Akademik')
                    ->relationship('academicTerm', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('code')
                    ->label('Kode (contoh: kelancaran)')
                    ->required(),
                TextInput::make('name')
                    ->label('Nama Kategori (contoh: KELANCARAN)')
                    ->required(),
                Textarea::make('description')
                    ->label('Deskripsi'),
                TextInput::make('max_score')
                    ->label('Nilai Maksimal')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->required()
                    ->default(20),
                TextInput::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}