<?php

namespace App\Filament\Resources\RppAiSettings\Pages;

use App\Filament\Resources\RppAiSettings\RppAiSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditRppAiSetting extends EditRecord
{
    protected static string $resource = RppAiSettingResource::class;
    protected function mutateFormDataBeforeSave(array $data): array { $data['updated_by'] = auth()->id(); return $data; }
}
