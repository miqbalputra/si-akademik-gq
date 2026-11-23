<?php

namespace App\Filament\Resources\TasmiRecords\Pages;

use App\Filament\Resources\TasmiRecords\TasmiRecordResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTasmiRecords extends ListRecords
{
    protected static string $resource = TasmiRecordResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [];
        if (auth()->user()?->hasAnyRole(['admin', 'kabag_tahfidz'])) {
            $actions[] = Action::make('workflowStatistics')->label('Statistik & unduh Tasmi\'')->url(route('admin.tasmi-report.index'));
        }

        return TasmiRecordResource::canCreate() ? [...$actions, CreateAction::make()] : $actions;
    }
}
