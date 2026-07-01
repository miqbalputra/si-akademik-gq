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
                    ->label('Mapel Kelas')
                    ->relationship('classSubject.subject', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('title')
                    ->label('Judul')
                    ->required(),
                TextInput::make('tested_material')
                    ->label('Materi Ujian')
                    ->helperText('Wajib diisi untuk Praktik Ibadah.')
                    ->required(fn (callable $get) => $get('assessment_method') === 'practical'),
                Select::make('assessment_method')
                    ->label('Metode Penilaian')
                    ->options([
                        'weighted' => 'Weighted 40/60',
                        'practical' => 'Praktik (Weighted 40/60, multi-blok)',
                        'direct_final' => 'Nilai Akhir Langsung',
                    ])
                    ->required()
                    ->default('weighted')
                    ->live(),
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
                Select::make('status')
                    ->label('Status')
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
