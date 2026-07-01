<?php

namespace App\Filament\Resources\DiniyyahScores\Pages;

use App\Filament\Resources\DiniyyahScores\DiniyyahScoreResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDiniyyahScores extends ListRecords
{
    protected static string $resource = DiniyyahScoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
