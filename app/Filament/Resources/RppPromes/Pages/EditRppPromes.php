<?php

namespace App\Filament\Resources\RppPromes\Pages;

use App\Filament\Resources\RppPromes\RppPromesResource;
use Filament\Resources\Pages\EditRecord;

class EditRppPromes extends EditRecord
{
    protected static string $resource = RppPromesResource::class;
    protected function mutateFormDataBeforeSave(array $data): array { $data['updated_by'] = auth()->id(); return $data; }
}
