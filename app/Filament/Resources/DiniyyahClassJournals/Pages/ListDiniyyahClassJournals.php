<?php

namespace App\Filament\Resources\DiniyyahClassJournals\Pages;

use App\Filament\Resources\DiniyyahClassJournals\DiniyyahClassJournalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDiniyyahClassJournals extends ListRecords
{
    protected static string $resource = DiniyyahClassJournalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
