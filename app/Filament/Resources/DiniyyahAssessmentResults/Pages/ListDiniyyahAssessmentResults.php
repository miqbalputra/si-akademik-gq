<?php

namespace App\Filament\Resources\DiniyyahAssessmentResults\Pages;

use App\Filament\Resources\DiniyyahAssessmentResults\DiniyyahAssessmentResultResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDiniyyahAssessmentResults extends ListRecords
{
    protected static string $resource = DiniyyahAssessmentResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
