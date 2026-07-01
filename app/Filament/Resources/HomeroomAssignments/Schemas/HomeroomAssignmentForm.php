<?php

namespace App\Filament\Resources\HomeroomAssignments\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class HomeroomAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('classroom_term_id')
                    ->relationship('classroomTerm', 'name')
                    ->required(),
                Select::make('teacher_id')
                    ->relationship('teacher', 'name')
                    ->required(),
                DatePicker::make('starts_at'),
                DatePicker::make('ends_at'),
            ]);
    }
}
