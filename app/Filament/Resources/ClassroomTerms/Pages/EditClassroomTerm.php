<?php

namespace App\Filament\Resources\ClassroomTerms\Pages;

use App\Filament\Resources\ClassroomTerms\ClassroomTermResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClassroomTerm extends EditRecord
{
    protected static string $resource = ClassroomTermResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
