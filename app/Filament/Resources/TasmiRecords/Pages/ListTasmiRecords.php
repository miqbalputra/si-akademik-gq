<?php

namespace App\Filament\Resources\TasmiRecords\Pages;

use App\Filament\Resources\TasmiRecords\TasmiRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTasmiRecords extends ListRecords
{
    protected static string $resource = TasmiRecordResource::class;

    protected function getHeaderActions(): array
    {
        return TasmiRecordResource::canCreate() ? [CreateAction::make()] : [];
    }
}
