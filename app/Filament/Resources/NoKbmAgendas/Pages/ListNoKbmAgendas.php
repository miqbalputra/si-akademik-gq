<?php

namespace App\Filament\Resources\NoKbmAgendas\Pages;

use App\Filament\Resources\NoKbmAgendas\NoKbmAgendaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNoKbmAgendas extends ListRecords
{
    protected static string $resource = NoKbmAgendaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
