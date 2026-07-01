<?php

namespace App\Filament\Resources\DiniyyahLedgerSnapshots\Pages;

use App\Filament\Resources\DiniyyahLedgerSnapshots\DiniyyahLedgerSnapshotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDiniyyahLedgerSnapshots extends ListRecords
{
    protected static string $resource = DiniyyahLedgerSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
