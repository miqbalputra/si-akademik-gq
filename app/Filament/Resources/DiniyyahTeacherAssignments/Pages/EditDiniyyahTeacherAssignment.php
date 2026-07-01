<?php

namespace App\Filament\Resources\DiniyyahTeacherAssignments\Pages;

use App\Filament\Resources\DiniyyahTeacherAssignments\DiniyyahTeacherAssignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDiniyyahTeacherAssignment extends EditRecord
{
    protected static string $resource = DiniyyahTeacherAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
