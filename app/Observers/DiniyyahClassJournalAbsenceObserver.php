<?php

namespace App\Observers;

use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassJournalAbsence;
use App\Services\DiniyyahScoreCalculator;
use App\Services\NotificationDispatcher;

/**
 * Menghitung ulang skor presensi ketika catatan absensi jurnal diniyyah berubah
 * secara independen (mis. dihapus/diedit lewat panel Filament). Saat jurnal
 * induk dihapus, absensi terhapus via cascade FK tingkat DB — observer ini tidak
 * terpanggil, tetapi DiniyyahClassJournalObserver::deleted sudah menangani
 * hitung ulang tersebut.
 *
 * Juga mengirim notifikasi ke wali kelas bila santri tercatat bolos sesi
 * (status=skipped/absent) oleh guru diniyyah.
 */
class DiniyyahClassJournalAbsenceObserver
{
    public function __construct(
        private readonly DiniyyahScoreCalculator $calculator,
    ) {}

    public function created(DiniyyahClassJournalAbsence $absence): void
    {
        $this->recalculate($absence);
        $this->notifySkipped($absence, isCreated: true);
    }

    public function updated(DiniyyahClassJournalAbsence $absence): void
    {
        $this->recalculate($absence);
        if ($absence->wasChanged('status')) {
            $this->notifySkipped($absence, isCreated: false);
        }
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

    /**
     * Notifikasi ke wali kelas hanya bila status = skipped (bolos sesi)
     * atau absent (alpa sesi). Status sick/permission tidak memicu notif
     * di sini karena sudah dicatat wali kelas sendiri (bukan temuan guru).
     */
    private function notifySkipped(DiniyyahClassJournalAbsence $absence, bool $isCreated): void
    {
        if (! in_array($absence->status, ['skipped', 'absent'], true)) {
            return;
        }

        $journal = DiniyyahClassJournal::with(['teacherAssignment.classSubject.classroomTerm', 'teacherAssignment.classSubject.subject'])
            ->find($absence->diniyyah_class_journal_id);
        if (! $journal?->teacherAssignment?->classSubject) {
            return;
        }

        $cs = $journal->teacherAssignment->classSubject;
        $mapel = $cs->subject?->name ?? 'mapel';
        $kelas = $cs->classroomTerm?->name ?? 'kelas';
        $dateLabel = $journal->date?->locale('id')->translatedFormat('d M Y') ?? '-';
        $statusLabel = $absence->status === 'skipped' ? 'bolos sesi' : 'alpa sesi';
        $verb = $isCreated ? 'tercatat' : 'diperbarui';

        $enrollment = \App\Models\ClassEnrollment::with('student')->find($absence->class_enrollment_id);
        $studentName = $enrollment?->student?->name ?? 'santri';

        $body = "Santri {$studentName} {$verb} {$statusLabel} {$mapel} kelas {$kelas} tanggal {$dateLabel}.";

        app(NotificationDispatcher::class)->dispatchToHomeroomTeacher(
            $cs->classroom_term_id,
            "Santri {$statusLabel}: {$studentName}",
            $body,
            'attendance_absent',
            route('guru.diniyyah-journals.edit', $journal),
            $absence->status === 'skipped' ? 'warning' : 'danger',
        );
    }
}