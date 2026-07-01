<?php

namespace App\Filament\Resources\DiniyyahScores\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DiniyyahScoreForm
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
                Select::make('diniyyah_score_component_id')
                    ->label('Komponen Nilai')
                    ->relationship('component', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('class_enrollment_id')
                    ->label('Enrollment Kelas')
                    ->relationship('classEnrollment.student', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('score')
                    ->label('Nilai')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),
                DateTimePicker::make('input_at'),
                    ->label('Diinput Pada')
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'validated' => 'Validated',
                        'locked' => 'Locked',
                    ])
                    ->required()
                    ->default('draft'),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->columnSpanFull(),
            ]);
    }
}
