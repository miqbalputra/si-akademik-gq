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
                    ->label('Kelas Periode')
                    ->relationship('classroomTerm', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('subject_id')
                    ->label('Mata Pelajaran')
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('assessment_method')
                    ->label('Metode Penilaian')
                    ->options([
                        'weighted' => 'Weighted 40/60',
                        'direct_final' => 'Nilai Akhir Langsung',
                        'practical' => 'Praktik',
                    ])
                    ->required()
                    ->default('weighted'),
                TextInput::make('kkm')
                    ->label('KKM')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),
                TextInput::make('daily_weight')
                    ->label('Bobot Harian (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(40),
                TextInput::make('exam_weight')
                    ->label('Bobot Ujian (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(60),
                Toggle::make('appears_on_ledger')
                    ->label('Tampil di Leger')
                    ->default(true),
                Toggle::make('appears_on_report')
                    ->label('Tampil di Rapor')
                    ->default(true),
                TextInput::make('sort_order')
                    ->label('Urutan Tampil')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_active')
                    ->label('Status Aktif')
                    ->default(true),
            ]);
    }
}
