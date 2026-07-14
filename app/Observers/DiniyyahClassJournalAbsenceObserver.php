<?php

namespace App\Observers;

use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassJournalAbsence;
use App\Services\DiniyyahScoreCalculator;

/**
 * Menghitung ulang skor presensi ketika catatan absensi jurnal diniyyah berubah
 * secara independen (mis. dihapus/diedit lewat panel Filament). Saat jurnal
 * induk dihapus, absensi terhapus via cascade FK tingkat DB — observer ini tidak
 * terpanggil, tetapi DiniyyahClassJournalObserver::deleted sudah menangani
 * hitung ulang tersebut.
 */
class DiniyyahClassJournalAbsenceObserver
{
    public function __construct(private readonly DiniyyahScoreCalculator $calculator) {}

    public function created(DiniyyahClassJournalAbsence $absence): void
    {
        $this->recalculate($absence);
    }

    public function updated(DiniyyahClassJournalAbsence $absence): void
    {
        $this->recalculate($absence);
    }

    public function deleted(DiniyyahClassJournalAbsence $absence): void
    {
        $this->recalculate($absence);
    }

    private function recalculate(DiniyyahClassJournalAbsence $absence): void
    {
        $journal = DiniyyahClassJournal::with('teacherAssignment')->find($absence->diniyyah_class_journal_id);

        if (! $journal?->teacherAssignment) {
            return;
        }

        $this->calculator->syncAttendanceForClassSubject($journal->teacherAssignment->diniyyah_class_subject_id);
    }
}