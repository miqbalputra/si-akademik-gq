<?php

namespace App\Filament\Resources\TasmiRecords\Schemas;

use App\Models\TasmiRecord;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TasmiRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Ujian')
                    ->schema([
                        Select::make('academic_term_id')
                            ->label('Periode Akademik')
                            ->relationship('academicTerm', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('classroom_term_id')
                            ->label('Kelas (Periode)')
                            ->relationship('classroomTerm', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Kelas tempat santri terdaftar saat tasmi\'.'),
                        Select::make('class_enrollment_id')
                            ->label('Enrollment')
                            ->relationship('classEnrollment', 'id')
                            ->helperText('Otomatis terisi saat input lewat portal guru. Hanya isi manual jika tahu enrollment ID.'),
                        Select::make('student_id')
                            ->label('Santri')
                            ->relationship('student', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('examiner_teacher_id')
                            ->label('Penguji (PJ Tasmi\')')
                            ->relationship('examinerTeacher', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('tasmi_examiner_assignment_id')
                            ->label('Penugasan PJ Tasmi\'')
                            ->relationship('examinerAssignment', 'id')
                            ->helperText('Otomatis terisi saat input lewat portal guru.'),
                    ]),
                Section::make('Detail Ujian')
                    ->schema([
                        Select::make('exam_type')
                            ->label('Jenis Ujian')
                            ->options(TasmiRecord::examTypeOptions())
                            ->required()
                            ->live()
                            ->helperText(fn ($state) => match ($state) {
                                TasmiRecord::EXAM_TYPE_ONE_JUZ => 'Setoran 1 juz full — juz awal = juz akhir.',
                                TasmiRecord::EXAM_TYPE_FIVE_JUZ => 'Setoran 5 juz full — rentang harus tepat 5 juz.',
                                default => 'Pilih tipe ujian untuk melihat aturan juz.',
                            }),
                        TextInput::make('juz_start')
                            ->label('Juz Awal')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(30)
                            ->required()
                            ->helperText('Untuk 1 juz: juz awal = juz akhir. Untuk 5 juz: rentang harus 5 juz.'),
                        TextInput::make('juz_end')
                            ->label('Juz Akhir')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(30)
                            ->required()
                            ->helperText('Untuk 1 juz: juz akhir = juz awal. Untuk 5 juz: juz akhir - juz awal + 1 = 5.'),
                        TextInput::make('exam_day_label')
                            ->label('Label Hari')
                            ->placeholder('Mis. Hari 1')
                            ->maxLength(50),
                        DatePicker::make('exam_date')
                            ->label('Tanggal Ujian (Masehi)')
                            ->required(),
                        TextInput::make('hijri_date')
                            ->label('Tanggal Hijriyah')
                            ->placeholder('Mis. 15 Sya\'ban 1448 H')
                            ->maxLength(50),
                        Select::make('predicate')
                            ->label('Predikat')
                            ->options(TasmiRecord::predicateOptions())
                            ->required(),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->maxLength(1000),
                    ]),
            ]);
    }
}