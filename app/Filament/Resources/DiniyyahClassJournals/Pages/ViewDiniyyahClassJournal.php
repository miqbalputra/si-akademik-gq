<?php

namespace App\Filament\Resources\DiniyyahClassJournals\Pages;

use App\Filament\Resources\DiniyyahClassJournals\DiniyyahClassJournalResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDiniyyahClassJournal extends ViewRecord
{
    protected static string $resource = DiniyyahClassJournalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
