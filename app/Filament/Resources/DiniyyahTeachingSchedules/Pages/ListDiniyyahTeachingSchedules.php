<?php

namespace App\Filament\Resources\DiniyyahTeachingSchedules\Pages;

use App\Filament\Resources\DiniyyahTeachingSchedules\DiniyyahTeachingScheduleResource;
use App\Filament\Pages\DiniyyahScheduleVersionManager;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListDiniyyahTeachingSchedules extends ListRecords
{
    protected static string $resource = DiniyyahTeachingScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manage_versions')
                ->label('Kelola versi & koreksi jadwal')
                ->icon('heroicon-o-calendar-days')
                ->url(DiniyyahScheduleVersionManager::getUrl()),
        ];
    }
}
