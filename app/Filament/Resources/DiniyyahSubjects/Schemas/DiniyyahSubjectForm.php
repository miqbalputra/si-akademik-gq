<?php

namespace App\Filament\Resources\DiniyyahSubjects\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DiniyyahSubjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')->label('Kode')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('name')->label('Nama')
                    ->required(),
                TextInput::make('arabic_name')->label('Nama Arab'),
                Select::make('default_assessment_method')->label('Metode Penilaian Default')
                    ->options([
                        'weighted' => 'Weighted 40/60',
                        'direct_final' => 'Nilai Akhir Langsung',
                        'practical' => 'Praktik',
                    ])
                    ->required()
                    ->default('weighted'),
                TextInput::make('sort_order')->label('Urutan Tampil')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_active')->label('Status Aktif')
                    ->default(true)
                    ->required(),
            ]);
    }
}
