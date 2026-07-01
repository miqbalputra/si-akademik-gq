<?php

namespace App\Filament\Resources\DiniyyahAssessmentSets\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DiniyyahAssessmentSetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('diniyyah_class_subject_id')
                    ->relationship('classSubject.subject', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('title')
                    ->required(),
                TextInput::make('tested_material')
                    ->helperText('Wajib diisi untuk Praktik Ibadah.')
                    ->required(fn (callable $get) => $get('assessment_method') === 'practical'),
                Select::make('assessment_method')
                    ->options([
                        'weighted' => 'Weighted 40/60',
                        'practical' => 'Praktik (Weighted 40/60, multi-blok)',
                        'direct_final' => 'Nilai Akhir Langsung',
                    ])
                    ->required()
                    ->default('weighted')
                    ->live(),
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
                Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Aktif',
                        'submitted' => 'Submitted',
                        'validated' => 'Tervalidasi',
                        'needs_revision' => 'Perlu Revisi',
                        'closed' => 'Ditutup',
                    ])
                    ->required()
                    ->default('draft'),
            ]);
    }
}
