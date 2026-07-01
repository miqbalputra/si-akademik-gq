<?php

namespace App\Filament\Resources\Schools\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SchoolForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Nama')
                    ->required(),
                TextInput::make('short_name')->label('Nama Singkat'),
                Textarea::make('address')
                    ->columnSpanFull(),
                TextInput::make('phone')->label('Telepon')
                    ->tel(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('logo_path')->label('Logo'),
            ]);
    }
}
