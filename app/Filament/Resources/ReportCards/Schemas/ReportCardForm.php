<?php

namespace App\Filament\Resources\ReportCards\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ReportCardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('academic_term_id')->label('Periode Akademik')->relationship('academicTerm', 'name')->searchable()->preload()->required(),
                Select::make('classroom_term_id')->label('Kelas Periode')->relationship('classroomTerm', 'name')->searchable()->preload()->required(),
                Select::make('class_enrollment_id')->label('Enrollment Kelas')->relationship('classEnrollment.student', 'name')->searchable()->preload()->required(),
                Select::make('student_id')->label('Santri')->relationship('student', 'name')->searchable()->preload()->required(),
                Select::make('report_type')->label('Jenis Laporan')
                    ->options(['diniyyah' => 'Diniyyah', 'combined' => 'Gabungan'])
                    ->default('diniyyah')
                    ->required(),
                Select::make('status')->label('Status')
                    ->options(['draft' => 'Draft', 'locked' => 'Locked', 'published' => 'Published'])
                    ->default('draft')
                    ->required(),
                DatePicker::make('issue_date')->label('Tanggal Terbit'),
                TextInput::make('total_score')->label('Total Nilai')->disabled(),
                TextInput::make('average_score')->label('Nilai Rata-rata')->disabled(),
                TextInput::make('rank_in_class')->label('Peringkat Kelas')->disabled(),
                Textarea::make('homeroom_note')->columnSpanFull(),
            ]);
    }
}
