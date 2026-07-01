<?php

namespace App\Filament\Resources\ClassEnrollments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClassEnrollmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('academic_term_id')
                    ->relationship('academicTerm', 'name')
                    ->required(),
                Select::make('classroom_term_id')
                    ->relationship('classroomTerm', 'name')
                    ->required(),
                Select::make('student_id')
                    ->relationship('student', 'name')
                    ->required(),
                TextInput::make('roll_number')
                    ->numeric()
                    ->minValue(1),
                Select::make('status')
                    ->options([
                        'active'   => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                    ])
                    ->required()
                    ->default('active'),
            ]);
    }
}
