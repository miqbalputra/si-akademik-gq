<?php

namespace App\Observers;

use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahTeacherAssignment;
use App\Services\DiniyyahScoreCalculator;
use App\Services\NotificationDispatcher;

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
 *
 * Juga mengirim notifikasi ke wali kelas (jurnal kelas baru/dihapus) dan ke
 * guru asli (bila diisi oleh pengganti).
 */
class DiniyyahClassJournalObserver
{
    public function __construct(
        private readonly DiniyyahScoreCalculator $calculator,
    ) {}

    public function created(DiniyyahClassJournal $journal): void
    {
        $this->recalculate($journal);
        $this->notifyJournalCreated($journal);
    }

    public function deleted(DiniyyahClassJournal $journal): void
    {
        $this->recalculate($journal);
        $this->notifyJournalDeleted($journal);
    }

    private function recalculate(DiniyyahClassJournal $journal): void
    {
        $assignment = DiniyyahTeacherAssignment::find($journal->diniyyah_teacher_assignment_id);

        if (! $assignment) {
            return;
        }

        $this->calculator->syncAttendanceForClassSubject($assignment->diniyyah_class_subject_id);
    }

    private function notifyJournalCreated(DiniyyahClassJournal $journal): void
    {
        $assignment = DiniyyahTeacherAssignment::with(['classSubject.subject', 'classSubject.classroomTerm', 'teacher.user'])
            ->find($journal->diniyyah_teacher_assignment_id);
        if (! $assignment || ! $assignment->classSubject) {
            return;
        }
        $mapel = $assignment->classSubject->subject?->name ?? 'mapel';
        $kelas = $assignment->classSubject->classroomTerm?->name ?? 'kelas';
        $dateLabel = $journal->date?->locale('id')->translatedFormat('d M Y') ?? '-';
        $session = (string) $journal->session_hour;
        $isSubstitute = $journal->substitute_teacher_id !== null;
        $verb = $isSubstitute ? 'diisi oleh guru pengganti' : 'diisi';

        $body = "Jurnal {$mapel} kelas {$kelas} tanggal {$dateLabel} sesi {$session} {$verb}.";
        $linkUrl = route('guru.diniyyah-journals.index', [
            'classroom_term_id' => $assignment->classSubject->classroom_term_id,
            'date' => $journal->date?->toDateString(),
        ]);

        $dispatcher = app(NotificationDispatcher::class);

        // Wali kelas kelas tsb.
        if ($assignment->classSubject->classroom_term_id) {
            $dispatcher->dispatchToHomeroomTeacher(
                $assignment->classSubject->classroom_term_id,
                "Jurnal baru: {$mapel} {$kelas}",
                $body,
                'journal_created',
                $linkUrl,
                'info',
            );
        }

        // Bila pengganti → guru asli dapat notif (tahu digantikan).
        if ($isSubstitute && $assignment->teacher?->user_id) {
            $dispatcher->dispatchToUser(
                $assignment->teacher->user_id,
                'Jurnal Anda diisi oleh pengganti',
                $body,
                'journal_created',
                $linkUrl,
                'info',
            );
        }
    }

    private function notifyJournalDeleted(DiniyyahClassJournal $journal): void
    {
        $assignment = DiniyyahTeacherAssignment::with(['classSubject.subject', 'classSubject.classroomTerm', 'teacher.user'])
            ->find($journal->diniyyah_teacher_assignment_id);
        if (! $assignment || ! $assignment->classSubject) {
            return;
        }
        $mapel = $assignment->classSubject->subject?->name ?? 'mapel';
        $kelas = $assignment->classSubject->classroomTerm?->name ?? 'kelas';
        $dateLabel = $journal->date?->locale('id')->translatedFormat('d M Y') ?? '-';
        $session = (string) $journal->session_hour;

        $body = "Jurnal {$mapel} kelas {$kelas} tanggal {$dateLabel} sesi {$session} dihapus.";
        $linkUrl = route('guru.diniyyah-journals.index', [
            'classroom_term_id' => $assignment->classSubject->classroom_term_id,
        ]);

        $dispatcher = app(NotificationDispatcher::class);

        if ($assignment->classSubject->classroom_term_id) {
            $dispatcher->dispatchToHomeroomTeacher(
                $assignment->classSubject->classroom_term_id,
                "Jurnal dihapus: {$mapel} {$kelas}",
                $body,
                'journal_deleted',
                $linkUrl,
                'warning',
            );
        }

        if ($assignment->teacher?->user_id) {
            $dispatcher->dispatchToUser(
                $assignment->teacher->user_id,
                'Jurnal Anda dihapus',
                $body,
                'journal_deleted',
                $linkUrl,
                'warning',
            );
        }
    }
}