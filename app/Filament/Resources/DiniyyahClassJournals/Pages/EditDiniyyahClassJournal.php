<?php

namespace App\Filament\Resources\DiniyyahClassJournals\Pages;

use App\Filament\Resources\DiniyyahClassJournals\DiniyyahClassJournalResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDiniyyahClassJournal extends EditRecord
{
    protected static string $resource = DiniyyahClassJournalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * Re-resolve snapshot jam sesi bila date/session_hour diubah saat edit, agar
     * tetap konsisten dengan matrix (kelas + hari tanggal).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return DiniyyahClassJournalResource::resolveSessionTimes($data);
    }
}