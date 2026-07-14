<?php

namespace App\Filament\Resources\DiniyyahClassJournals\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class DiniyyahClassJournalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('diniyyah_teacher_assignment_id')
                    ->required()
                    ->numeric(),
                DatePicker::make('date')
                    ->required(),
                TextInput::make('session_hour')
                    ->required(),
                Textarea::make('material')
                    ->columnSpanFull(),
                TextInput::make('jp_count')
                    ->required()
                    ->numeric()
                    ->default(1),
            ]);
    }
}
