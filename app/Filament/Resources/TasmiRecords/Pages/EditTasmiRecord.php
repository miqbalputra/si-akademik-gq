<?php

namespace App\Filament\Resources\TasmiRecords\Pages;

use App\Filament\Resources\TasmiRecords\TasmiRecordResource;
use Filament\Resources\Pages\EditRecord;

class EditTasmiRecord extends EditRecord
{
    protected static string $resource = TasmiRecordResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['last_updated_by'] = auth()->id();

        return $data;
    }
}