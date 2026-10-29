<?php

namespace App\Filament\Resources\DiniyyahClassJournals\Pages;

use App\Filament\Concerns\ChecksJournalAttendance;
use App\Filament\Resources\DiniyyahClassJournals\DiniyyahClassJournalResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDiniyyahClassJournal extends EditRecord
{
    use ChecksJournalAttendance;

    protected static string $resource = DiniyyahClassJournalResource::class;

    protected function afterFill(): void
    {
        $this->checkJournalAttendance();
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->disabled(fn (): bool => ! ($this->journalAttendanceEligibility['allowed'] ?? false));
    }

    protected function beforeSave(): void
    {
        // Filament saves relationship selects after this hook and before the
        // data-mutation hook. Reject here so teacher associations also stay intact.
        $this->enforceJournalAttendance($this->form->getRawState());
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
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
