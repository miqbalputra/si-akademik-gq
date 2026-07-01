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
                    ->relationship('assessmentSet', 'title')
                    ->disabled(),
                Select::make('class_enrollment_id')
                    ->relationship('classEnrollment.student', 'name')
                    ->disabled(),
                TextInput::make('daily_raw_score')->disabled(),
                TextInput::make('exam_raw_score')->disabled(),
                TextInput::make('daily_weighted_score')->disabled(),
                TextInput::make('exam_weighted_score')->disabled(),
                TextInput::make('final_score')->disabled(),
                TextInput::make('kkm')->disabled(),
                Toggle::make('is_complete')->disabled(),
                Toggle::make('is_passed')->disabled(),
                DateTimePicker::make('calculated_at')->disabled(),
                DateTimePicker::make('locked_at')->disabled(),
            ]);
    }
}
