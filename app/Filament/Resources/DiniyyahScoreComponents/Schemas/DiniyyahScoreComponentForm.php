<?php

namespace App\Filament\Resources\DiniyyahScoreComponents\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DiniyyahScoreComponentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('diniyyah_assessment_set_id')
                    ->label('Set Ujian Diniyyah')
                    ->relationship('assessmentSet', 'title')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('code')
                    ->label('Kode')
                    ->required(),
                TextInput::make('name')
                    ->label('Nama')
                    ->required(),
                Select::make('component_group')
                    ->label('Grup Komponen')
                    ->options([
                        'daily' => 'Nilai Harian',
                        'exam' => 'Nilai Ujian',
                        'final' => 'Nilai Akhir',
                    ])
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Urutan Tampil')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_required')
                    ->label('Wajib')
                    ->default(true),
            ]);
    }
}
