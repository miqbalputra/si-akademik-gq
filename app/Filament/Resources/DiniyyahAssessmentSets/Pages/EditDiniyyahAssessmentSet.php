<?php

namespace App\Filament\Resources\DiniyyahAssessmentSets\Pages;

use App\Filament\Resources\DiniyyahAssessmentSets\DiniyyahAssessmentSetResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditDiniyyahAssessmentSet extends EditRecord
{
    protected static string $resource = DiniyyahAssessmentSetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
