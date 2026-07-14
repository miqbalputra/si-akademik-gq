<?php

namespace App\Filament\Resources\DiniyyahTeachingSchedules\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DiniyyahTeachingScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('diniyyah_teacher_assignment_id')
                    ->label('Tugas Mengajar (Mapel & Guru)')
                    ->relationship('teacherAssignment', 'id')
                    ->getOptionLabelFromRecordUsing(fn (\App\Models\DiniyyahTeacherAssignment $record) => "{$record->classSubject->classroomTerm->name} - {$record->classSubject->subject->name} ({$record->teacher->name})")
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('day_of_week')
                    ->label('Hari')
                    ->options([
                        1 => 'Senin',
                        2 => 'Selasa',
                        3 => 'Rabu',
                        4 => 'Kamis',
                        5 => 'Jumat',
                        6 => 'Sabtu',
                        7 => 'Minggu',
                    ])
                    ->required(),
                Select::make('class_session_id')
                    ->label('Jam Pelajaran / Sesi')
                    ->relationship('classSession', 'session_name')
                    ->getOptionLabelFromRecordUsing(fn (\App\Models\ClassSession $record) => "Jam Ke-{$record->session_name} (" . ($record->starts_at ? \Carbon\Carbon::parse($record->starts_at)->format('H:i') . ' - ' . \Carbon\Carbon::parse($record->ends_at)->format('H:i') : '') . ")" . ($record->is_break ? " [Istirahat]" : ""))
                    ->searchable()
                    ->preload()
                    ->required(),
            ])->columns(1);
    }
}
