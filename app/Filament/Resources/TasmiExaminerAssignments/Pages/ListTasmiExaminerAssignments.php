<?php

namespace App\Filament\Resources\TasmiExaminerAssignments\Pages;

use App\Filament\Resources\TasmiExaminerAssignments\TasmiExaminerAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTasmiExaminerAssignments extends ListRecords
{
    protected static string $resource = TasmiExaminerAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}