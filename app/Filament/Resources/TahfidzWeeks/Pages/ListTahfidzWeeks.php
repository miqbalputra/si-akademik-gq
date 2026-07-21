<?php

namespace App\Filament\Resources\TahfidzWeeks\Pages;

use App\Filament\Resources\TahfidzWeeks\TahfidzWeekResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTahfidzWeeks extends ListRecords
{
    protected static string $resource = TahfidzWeekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
