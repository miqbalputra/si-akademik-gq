<?php

namespace App\Filament\Resources\ReportCards\Pages;

use App\Filament\Resources\ReportCards\ReportCardResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReportCard extends EditRecord
{
    protected static string $resource = ReportCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
