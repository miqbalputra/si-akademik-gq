<?php

namespace App\Filament\Resources\DiniyyahAssessmentResults\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DiniyyahAssessmentResultForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('diniyyah_assessment_set_id')->label('Set Ujian Diniyyah')
                    ->relationship('assessmentSet', 'title')
                    ->disabled(),
                Select::make('class_enrollment_id')->label('Enrollment Kelas')
                    ->relationship('classEnrollment.student', 'name')
                    ->disabled(),
                TextInput::make('daily_raw_score')->label('Nilai Mentah Harian')->disabled(),
                TextInput::make('exam_raw_score')->label('Nilai Mentah Ujian')->disabled(),
                TextInput::make('daily_weighted_score')->label('Nilai Bobot Harian')->disabled(),
                TextInput::make('exam_weighted_score')->label('Nilai Bobot Ujian')->disabled(),
                TextInput::make('final_score')->label('Nilai Akhir')->disabled(),
                TextInput::make('kkm')->label('KKM')->disabled(),
                Toggle::make('is_complete')->label('Lengkap')->disabled(),
                Toggle::make('is_passed')->label('Lulus')->disabled(),
                DateTimePicker::make('calculated_at')->label('Dihitung Pada')->disabled(),
                DateTimePicker::make('locked_at')->disabled(),
            ]);
    }
}
