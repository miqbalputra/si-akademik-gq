<?php

namespace App\Filament\Resources\HomeroomAssignments\Pages;

use App\Filament\Resources\HomeroomAssignments\HomeroomAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHomeroomAssignments extends ListRecords
{
    protected static string $resource = HomeroomAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
