<?php

namespace App\Filament\Resources\TahfidzUasDays\Pages;

use App\Filament\Resources\TahfidzUasDays\TahfidzUasDayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTahfidzUasDays extends ListRecords
{
    protected static string $resource = TahfidzUasDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
