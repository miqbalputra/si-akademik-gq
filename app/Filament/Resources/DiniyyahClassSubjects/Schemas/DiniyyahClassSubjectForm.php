<?php

namespace App\Filament\Resources\DiniyyahClassSubjects\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DiniyyahClassSubjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('classroom_term_id')
                    ->relationship('classroomTerm', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('subject_id')
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('assessment_method')
                    ->options([
                        'weighted' => 'Weighted 40/60',
                        'direct_final' => 'Nilai Akhir Langsung',
                        'practical' => 'Praktik',
                    ])
                    ->required()
                    ->default('weighted'),
                TextInput::make('kkm')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),
                TextInput::make('daily_weight')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(40),
                TextInput::make('exam_weight')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(60),
                Toggle::make('appears_on_ledger')
                    ->default(true),
                Toggle::make('appears_on_report')
                    ->default(true),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
