<?php

namespace App\Filament\Resources\DiniyyahClassJournals\Pages;

use App\Filament\Concerns\ChecksJournalAttendance;
use App\Filament\Resources\DiniyyahClassJournals\DiniyyahClassJournalResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateDiniyyahClassJournal extends CreateRecord
{
    use ChecksJournalAttendance;

    protected static string $resource = DiniyyahClassJournalResource::class;

    protected function afterFill(): void
    {
        $this->journalAttendanceEligibility = null;
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->disabled(fn (): bool => ! ($this->journalAttendanceEligibility['allowed'] ?? false));
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()
            ->disabled(fn (): bool => ! ($this->journalAttendanceEligibility['allowed'] ?? false));
    }

    /**
     * Snapshot jam mulai/selesai sesi dari matrix (kelas + hari tanggal) sebelum
     * disimpan, supaya jurnal yang dicatat admin punya snapshot jam sama dengan
     * jurnal portal guru (konsistensi rekap/performa/tampilan). null bila matrix
     * tak ada — tidak menolak penyimpanan.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->enforceJournalAttendance($data);

        return DiniyyahClassJournalResource::resolveSessionTimes($data);
    }
}
