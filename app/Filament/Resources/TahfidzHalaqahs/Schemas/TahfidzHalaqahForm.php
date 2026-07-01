<?php

namespace App\Filament\Resources\TahfidzHalaqahs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TahfidzHalaqahForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('academic_term_id')
                    ->relationship('academicTerm', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->label('Nama Halaqah')
                    ->required(),
                Select::make('teacher_id')
                    ->relationship('teacher', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Guru Pengampu'),
                Select::make('assistant_teacher_id')
                    ->relationship('assistantTeacher', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Asisten Guru'),
                Select::make('status')
                    ->options([
                        'active' => 'Aktif',
                        'closed' => 'Ditutup',
                    ])
                    ->required()
                    ->default('active'),
                Textarea::make('notes')
                    ->label('Catatan'),
            ]);
    }
}