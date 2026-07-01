<?php

namespace App\Filament\Resources\ClassEnrollments\Pages;

use App\Filament\Resources\ClassEnrollments\ClassEnrollmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClassEnrollment extends EditRecord
{
    protected static string $resource = ClassEnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
