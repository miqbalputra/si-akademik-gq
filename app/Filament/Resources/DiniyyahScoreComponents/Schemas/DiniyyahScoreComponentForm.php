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
                    ->relationship('assessmentSet', 'title')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Select::make('component_group')
                    ->options([
                        'daily' => 'Nilai Harian',
                        'exam' => 'Nilai Ujian',
                        'final' => 'Nilai Akhir',
                    ])
                    ->required(),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_required')
                    ->default(true),
            ]);
    }
}
