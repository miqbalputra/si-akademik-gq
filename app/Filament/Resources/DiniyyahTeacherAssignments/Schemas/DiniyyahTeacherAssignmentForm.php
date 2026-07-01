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
                    ->relationship('classSubject.subject', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('teacher_id')
                    ->relationship('teacher', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('assignment_role')
                    ->options([
                        'primary' => 'Utama',
                        'assistant' => 'Pendamping',
                    ])
                    ->required()
                    ->default('primary'),
                DatePicker::make('starts_at'),
                DatePicker::make('ends_at'),
            ]);
    }
}
