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
                    ->label('Periode Akademik')
                    ->relationship('academicTerm', 'name')
                    ->required(),
                Select::make('classroom_term_id')
                    ->label('Kelas Periode')
                    ->relationship('classroomTerm', 'name')
                    ->required(),
                Select::make('student_id')
                    ->label('Santri')
                    ->relationship('student', 'name')
                    ->required(),
                TextInput::make('roll_number')
                    ->label('No. Absen')
                    ->numeric()
                    ->minValue(1),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'active'   => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                    ])
                    ->required()
                    ->default('active'),
            ]);
    }
}
