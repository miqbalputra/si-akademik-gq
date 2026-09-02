<?php

namespace App\Filament\Resources\RppPromes\Pages;

use App\Filament\Resources\RppPromes\RppPromesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRppPromes extends ListRecords
{
    protected static string $resource = RppPromesResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
