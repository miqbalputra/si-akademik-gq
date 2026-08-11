<?php

namespace App\Filament\Resources\TasmiExaminerAssignments\Pages;

use App\Filament\Resources\TasmiExaminerAssignments\TasmiExaminerAssignmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTasmiExaminerAssignment extends CreateRecord
{
    protected static string $resource = TasmiExaminerAssignmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['assigned_by'] = auth()->id();

        return $data;
    }
}