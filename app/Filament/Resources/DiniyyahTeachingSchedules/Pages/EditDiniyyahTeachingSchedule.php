<?php

namespace App\Filament\Resources\DiniyyahTeachingSchedules\Pages;

use App\Filament\Resources\DiniyyahTeachingSchedules\DiniyyahTeachingScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDiniyyahTeachingSchedule extends EditRecord
{
    protected static string $resource = DiniyyahTeachingScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
