<?php

namespace App\Filament\Resources\DiniyyahLedgerSnapshots\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DiniyyahLedgerSnapshotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('academic_term_id')
                    ->label('Periode Akademik')
                    ->relationship('academicTerm', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('classroom_term_id')
                    ->label('Kelas Periode')
                    ->relationship('classroomTerm', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('title')
                    ->label('Judul')
                    ->required(),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'validated' => 'Tervalidasi',
                        'locked' => 'Terkunci',
                        'published' => 'Published',
                    ])
                    ->required()
                    ->default('draft'),
                DateTimePicker::make('generated_at')->disabled(),
                    ->label('Dibuat Pada')
                DateTimePicker::make('locked_at')->disabled(),
                DateTimePicker::make('published_at')->disabled(),
            ]);
    }
}
