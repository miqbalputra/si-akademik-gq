<?php

namespace App\Filament\Resources\DiniyyahScoreValidations\Pages;

use App\Filament\Resources\DiniyyahScoreValidations\DiniyyahScoreValidationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDiniyyahScoreValidations extends ListRecords
{
    protected static string $resource = DiniyyahScoreValidationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
