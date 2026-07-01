<?php

namespace App\Filament\Resources\SchoolHolidays\Pages;

use App\Filament\Resources\SchoolHolidays\SchoolHolidayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSchoolHolidays extends ListRecords
{
    protected static string $resource = SchoolHolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
