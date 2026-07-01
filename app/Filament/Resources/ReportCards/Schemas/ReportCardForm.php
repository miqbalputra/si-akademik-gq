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
                Select::make('academic_term_id')->relationship('academicTerm', 'name')->searchable()->preload()->required(),
                    ->label('Periode Akademik')
                Select::make('classroom_term_id')->relationship('classroomTerm', 'name')->searchable()->preload()->required(),
                    ->label('Kelas Periode')
                Select::make('class_enrollment_id')->relationship('classEnrollment.student', 'name')->searchable()->preload()->required(),
                    ->label('Enrollment Kelas')
                Select::make('student_id')->relationship('student', 'name')->searchable()->preload()->required(),
                    ->label('Santri')
                Select::make('report_type')
                    ->label('Jenis Laporan')
                    ->options(['diniyyah' => 'Diniyyah', 'combined' => 'Gabungan'])
                    ->default('diniyyah')
                    ->required(),
                Select::make('status')
                    ->label('Status')
                    ->options(['draft' => 'Draft', 'locked' => 'Locked', 'published' => 'Published'])
                    ->default('draft')
                    ->required(),
                DatePicker::make('issue_date'),
                    ->label('Tanggal Terbit')
                TextInput::make('total_score')->disabled(),
                    ->label('Total Nilai')
                TextInput::make('average_score')->disabled(),
                    ->label('Nilai Rata-rata')
                TextInput::make('rank_in_class')->disabled(),
                    ->label('Peringkat Kelas')
                Textarea::make('homeroom_note')->columnSpanFull(),
            ]);
    }
}
