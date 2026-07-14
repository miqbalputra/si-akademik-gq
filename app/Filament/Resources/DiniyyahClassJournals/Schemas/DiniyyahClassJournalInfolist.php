<?php

namespace App\Filament\Resources\DiniyyahClassJournals\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DiniyyahClassJournalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('diniyyah_teacher_assignment_id')
                    ->numeric(),
                TextEntry::make('date')
                    ->date(),
                TextEntry::make('session_hour'),
                TextEntry::make('material')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('jp_count')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
