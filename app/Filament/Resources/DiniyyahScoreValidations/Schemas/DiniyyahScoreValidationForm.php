<?php

namespace App\Filament\Resources\DiniyyahScoreValidations\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class DiniyyahScoreValidationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('diniyyah_assessment_set_id')->label('Set Ujian Diniyyah')
                    ->relationship('assessmentSet', 'title')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('validated_by')->label('Divalidasi Oleh')
                    ->relationship('validator', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('status')->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'needs_revision' => 'Perlu Revisi',
                    ])
                    ->required()
                    ->default('pending'),
                DateTimePicker::make('validated_at')->label('Divalidasi Pada'),
                Textarea::make('notes')->label('Catatan')
                    ->columnSpanFull(),
            ]);
    }
}
