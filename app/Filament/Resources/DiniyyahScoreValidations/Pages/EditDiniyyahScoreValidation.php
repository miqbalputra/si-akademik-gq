<?php

namespace App\Filament\Resources\DiniyyahScoreValidations\Pages;

use App\Filament\Resources\DiniyyahScoreValidations\DiniyyahScoreValidationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDiniyyahScoreValidation extends EditRecord
{
    protected static string $resource = DiniyyahScoreValidationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
