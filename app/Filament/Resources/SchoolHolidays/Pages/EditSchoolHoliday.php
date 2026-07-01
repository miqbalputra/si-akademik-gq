<?php

namespace App\Filament\Resources\SchoolHolidays\Pages;

use App\Filament\Resources\SchoolHolidays\SchoolHolidayResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSchoolHoliday extends EditRecord
{
    protected static string $resource = SchoolHolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
