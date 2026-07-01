<?php

namespace App\Filament\Resources\DiniyyahLedgerSnapshots\Pages;

use App\Filament\Resources\DiniyyahLedgerSnapshots\DiniyyahLedgerSnapshotResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDiniyyahLedgerSnapshot extends EditRecord
{
    protected static string $resource = DiniyyahLedgerSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
