<?php

namespace App\Filament\Resources\ClassSessions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ClassSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('session_name')
                    ->label('Nama Sesi / Jam Ke-')
                    ->placeholder('Contoh: 1, 2, atau Istirahat')
                    ->required()
                    ->maxLength(255),
                TimePicker::make('starts_at')
                    ->label('Waktu Mulai')
                    ->required(),
                TimePicker::make('ends_at')
                    ->label('Waktu Selesai')
                    ->required(),
                Toggle::make('is_break')
                    ->label('Ini adalah jam istirahat')
                    ->default(false),
            ])->columns(1);
    }
}
