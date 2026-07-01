<?php

namespace App\Filament\Resources\DiniyyahScoreComponents\Pages;

use App\Filament\Resources\DiniyyahScoreComponents\DiniyyahScoreComponentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDiniyyahScoreComponent extends EditRecord
{
    protected static string $resource = DiniyyahScoreComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
