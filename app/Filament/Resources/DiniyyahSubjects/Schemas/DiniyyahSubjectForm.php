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
                TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->required(),
                TextInput::make('arabic_name'),
                Select::make('default_assessment_method')
                    ->options([
                        'weighted' => 'Weighted 40/60',
                        'direct_final' => 'Nilai Akhir Langsung',
                        'practical' => 'Praktik',
                    ])
                    ->required()
                    ->default('weighted'),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }
}
