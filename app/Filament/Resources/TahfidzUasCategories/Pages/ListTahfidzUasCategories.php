<?php

namespace App\Filament\Resources\TahfidzUasCategories\Pages;

use App\Filament\Resources\TahfidzUasCategories\TahfidzUasCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTahfidzUasCategories extends ListRecords
{
    protected static string $resource = TahfidzUasCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
