<?php

namespace App\Filament\Resources\RppAiSettings\Pages;

use App\Filament\Resources\RppAiSettings\RppAiSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRppAiSettings extends ListRecords
{
    protected static string $resource = RppAiSettingResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
