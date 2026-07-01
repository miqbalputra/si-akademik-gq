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
                    ->label('Kelas Periode')
                    ->relationship('classroomTerm', 'name')
                    ->required(),
                Select::make('teacher_id')
                    ->label('Guru Utama')
                    ->relationship('teacher', 'name')
                    ->required(),
                DatePicker::make('starts_at'),
                    ->label('Dimulai Pada')
                DatePicker::make('ends_at'),
                    ->label('Selesai Pada')
            ]);
    }
}
