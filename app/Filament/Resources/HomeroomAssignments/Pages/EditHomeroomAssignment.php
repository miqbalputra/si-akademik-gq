<?php

namespace App\Filament\Resources\HomeroomAssignments\Pages;

use App\Filament\Resources\HomeroomAssignments\HomeroomAssignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHomeroomAssignment extends EditRecord
{
    protected static string $resource = HomeroomAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
