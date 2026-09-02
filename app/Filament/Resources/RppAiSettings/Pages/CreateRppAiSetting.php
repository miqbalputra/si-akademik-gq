<?php

namespace App\Filament\Resources\RppAiSettings\Pages;

use App\Filament\Resources\RppAiSettings\RppAiSettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRppAiSetting extends CreateRecord
{
    protected static string $resource = RppAiSettingResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array { $data['updated_by'] = auth()->id(); return $data; }
}
