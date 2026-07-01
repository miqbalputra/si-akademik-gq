<?php

namespace App\Filament\Resources\DiniyyahAssessmentResults\Pages;

use App\Filament\Resources\DiniyyahAssessmentResults\DiniyyahAssessmentResultResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDiniyyahAssessmentResult extends EditRecord
{
    protected static string $resource = DiniyyahAssessmentResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
