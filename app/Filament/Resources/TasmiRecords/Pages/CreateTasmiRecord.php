<?php

namespace App\Filament\Resources\TasmiRecords\Pages;

use App\Filament\Resources\TasmiRecords\TasmiRecordResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTasmiRecord extends CreateRecord
{
    protected static string $resource = TasmiRecordResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['input_by'] = auth()->id();
        $data['input_at'] = now();
        $data['last_updated_by'] = auth()->id();

        return $data;
    }
}