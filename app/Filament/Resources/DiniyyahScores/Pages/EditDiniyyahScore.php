<?php

namespace App\Filament\Resources\DiniyyahScores\Pages;

use App\Filament\Resources\DiniyyahScores\DiniyyahScoreResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditDiniyyahScore extends EditRecord
{
    protected static string $resource = DiniyyahScoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
