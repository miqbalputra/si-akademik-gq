<?php

namespace App\Observers;

use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahTeacherAssignment;
use App\Services\DiniyyahScoreCalculator;

/**
 * Menghubungkan jurnal kelas diniyyah dengan komponen skor presensi
 * (keaktifan_presensi). Setiap kali jurnal dibuat/dihapus, skor presensi untuk
 * semua assessment set pada mapel kelas tersebut dihitung ulang otomatis —
 * tanpa menunggu recalc manual admin.
 *
 * Sumber data presensi: catatan harian wali kelas (StudentAttendance) yang
 * sudah disalin ke diniyyah_class_journal_absences saat jurnal diisi, plus
 * centang "bolos sesi" (status=skipped) oleh guru diniyyah. Lihat
 * GuruDiniyyahJournalController dan view guru/diniyyah-journals/index.blade.php.
 */
class DiniyyahClassJournalObserver
{
    public function __construct(private readonly DiniyyahScoreCalculator $calculator) {}

    public function created(DiniyyahClassJournal $journal): void
    {
        $this->recalculate($journal);
    }

    public function deleted(DiniyyahClassJournal $journal): void
    {
        $this->recalculate($journal);
    }

    private function recalculate(DiniyyahClassJournal $journal): void
    {
        $assignment = DiniyyahTeacherAssignment::find($journal->diniyyah_teacher_assignment_id);

        if (! $assignment) {
            return;
        }

        $this->calculator->syncAttendanceForClassSubject($assignment->diniyyah_class_subject_id);
    }
}