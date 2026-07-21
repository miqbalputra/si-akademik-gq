<?php

namespace App\Filament\Resources\TahfidzHalaqahs\Pages;

use App\Filament\Resources\TahfidzHalaqahs\TahfidzHalaqahResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTahfidzHalaqahs extends ListRecords
{
    protected static string $resource = TahfidzHalaqahResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
