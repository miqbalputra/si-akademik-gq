<?php

namespace App\Filament\Resources\DiniyyahTeacherAssignments\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class DiniyyahTeacherAssignmentForm
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
                Select::make('teacher_id')
                    ->label('Guru Utama')
                    ->relationship('teacher', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('assignment_role')
                    ->label('Peran Tugas')
                    ->options([
                        'primary' => 'Utama',
                        'assistant' => 'Pendamping',
                    ])
                    ->required()
                    ->default('primary'),
                DatePicker::make('starts_at'),
                    ->label('Dimulai Pada')
                DatePicker::make('ends_at'),
                    ->label('Selesai Pada')
            ]);
    }
}
