<?php

namespace App\Filament\Resources\DiniyyahClassJournals\Pages;

use App\Filament\Resources\DiniyyahClassJournals\DiniyyahClassJournalResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDiniyyahClassJournal extends CreateRecord
{
    protected static string $resource = DiniyyahClassJournalResource::class;

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
        return DiniyyahClassJournalResource::resolveSessionTimes($data);
    }
}