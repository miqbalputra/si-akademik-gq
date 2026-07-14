<?php

namespace App\Filament\Resources\DiniyyahTeachingSchedules\Pages;

use App\Filament\Resources\DiniyyahTeachingSchedules\DiniyyahTeachingScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDiniyyahTeachingSchedules extends ListRecords
{
    protected static string $resource = DiniyyahTeachingScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
