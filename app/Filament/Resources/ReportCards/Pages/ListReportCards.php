<?php

namespace App\Filament\Resources\ReportCards\Pages;

use App\Filament\Resources\ReportCards\ReportCardResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReportCards extends ListRecords
{
    protected static string $resource = ReportCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
