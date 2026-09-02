<?php

namespace App\Filament\Resources\RppPromes\Pages;

use App\Filament\Resources\RppPromes\RppPromesResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRppPromes extends CreateRecord
{
    protected static string $resource = RppPromesResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array { $data['updated_by'] = auth()->id(); return $data; }
}
