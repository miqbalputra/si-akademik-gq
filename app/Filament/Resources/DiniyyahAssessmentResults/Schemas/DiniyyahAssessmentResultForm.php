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
                Select::make('diniyyah_assessment_set_id')
                    ->label('Set Ujian Diniyyah')
                    ->relationship('assessmentSet', 'title')
                    ->disabled(),
                Select::make('class_enrollment_id')
                    ->label('Enrollment Kelas')
                    ->relationship('classEnrollment.student', 'name')
                    ->disabled(),
                TextInput::make('daily_raw_score')->disabled(),
                    ->label('Nilai Mentah Harian')
                TextInput::make('exam_raw_score')->disabled(),
                    ->label('Nilai Mentah Ujian')
                TextInput::make('daily_weighted_score')->disabled(),
                    ->label('Nilai Bobot Harian')
                TextInput::make('exam_weighted_score')->disabled(),
                    ->label('Nilai Bobot Ujian')
                TextInput::make('final_score')->disabled(),
                    ->label('Nilai Akhir')
                TextInput::make('kkm')->disabled(),
                    ->label('KKM')
                Toggle::make('is_complete')->disabled(),
                    ->label('Lengkap')
                Toggle::make('is_passed')->disabled(),
                    ->label('Lulus')
                DateTimePicker::make('calculated_at')->disabled(),
                    ->label('Dihitung Pada')
                DateTimePicker::make('locked_at')->disabled(),
            ]);
    }
}
