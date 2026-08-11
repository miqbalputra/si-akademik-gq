<?php

namespace App\Filament\Resources\TasmiExaminerAssignments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TasmiExaminerAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Penugasan PJ Tasmi\'')
                    ->description('Tugaskan guru sebagai penguji ujian tasmi\'. Ustadz hanya akan menguji kelas ikhwan, ustadzah hanya kelas akhwat (otomatis sesuai gender guru).')
                    ->schema([
                        Select::make('academic_term_id')
                            ->label('Periode Akademik')
                            ->relationship('academicTerm', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Pilih periode. Biasanya periode aktif yang sedang berjalan.'),
                        Select::make('teacher_id')
                            ->label('Guru (PJ Tasmi\')')
                            ->relationship('teacher', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Pilih guru. Gender guru menentukan kelas yang bisa diuji (ustadz → ikhwan, ustadzah → akhwat).'),
                        Select::make('status')
                            ->label('Status Penugasan')
                            ->options([
                                'active' => 'Aktif',
                                'inactive' => 'Nonaktif',
                            ])
                            ->default('active')
                            ->required(),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(2),
                    ]),
            ]);
    }
}