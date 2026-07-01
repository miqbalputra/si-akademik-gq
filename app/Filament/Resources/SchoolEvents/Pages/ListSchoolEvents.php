<?php

namespace App\Filament\Resources\SchoolEvents\Pages;

use App\Filament\Resources\SchoolEvents\SchoolEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSchoolEvents extends ListRecords
{
    protected static string $resource = SchoolEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
