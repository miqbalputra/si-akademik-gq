<?php

namespace App\Observers;

use App\Models\DiniyyahTeacherAssignment;
use App\Services\DiniyyahScheduleChangeLogger;
use App\Services\NotificationDispatcher;
use Illuminate\Support\Facades\Auth;

/**
 * Mencatat perubahan penugasan guru diniyyah ke riwayat audit. Lihat
 * {@see DiniyyahTeachingScheduleObserver} untuk rationale skip-no-auth & event
 * `deleting`.
 *
 * Juga mengirim notifikasi ke guru tsb saat penugasan dibuat/diubah/dihapus.
 */
class DiniyyahTeacherAssignmentObserver
{
    public function __construct(
        private readonly DiniyyahScheduleChangeLogger $logger,
    ) {}

    public function created(DiniyyahTeacherAssignment $assignment): void
    {
        if (Auth::check()) {
            $this->logger->logAssignmentCreated($assignment);
        }

        $this->notifyAssignment($assignment, 'created');
    }

    public function updated(DiniyyahTeacherAssignment $assignment): void
    {
        if (Auth::check()) {
            $this->logger->logAssignmentUpdated($assignment, $assignment->getOriginal());
        }

        $this->notifyAssignment($assignment, 'updated');
    }

    public function deleting(DiniyyahTeacherAssignment $assignment): void
    {
        $this->logger->logAssignmentDeleted($assignment);
        $this->notifyAssignment($assignment, 'removed');
    }

    private function notifyAssignment(DiniyyahTeacherAssignment $assignment, string $event): void
    {
        $cs = $assignment->classSubject ?? $assignment->classSubject()->with(['subject', 'classroomTerm'])->first();
        if (! $cs) {
            return;
        }
        $mapel = $cs->subject?->name ?? 'mapel';
        $kelas = $cs->classroomTerm?->name ?? 'kelas';

        $dispatcher = app(NotificationDispatcher::class);

        if ($event === 'created') {
            $dispatcher->dispatchToTasmiExaminer(
                $assignment->teacher_id,
                'Penugasan mengajar baru',
                "Anda ditugaskan mengajar {$mapel} di kelas {$kelas}.",
                'assignment_created',
                route('guru.diniyyah-scores.index'),
                'info',
            );
        } elseif ($event === 'updated') {
            // Bila teacher_id berubah → notif guru baru + guru lama.
            if ($assignment->wasChanged('teacher_id')) {
                $oldId = (int) $assignment->getOriginal('teacher_id');
                $newId = (int) $assignment->teacher_id;
                if ($oldId && $oldId !== $newId) {
                    $dispatcher->dispatchToTasmiExaminer(
                        $oldId,
                        'Penugasan mengajar dicabut',
                        "Penugasan mengajar {$mapel} di kelas {$kelas} dipindahkan ke guru lain.",
                        'assignment_removed',
                        route('guru.dashboard'),
                        'warning',
                    );
                }
                if ($newId) {
                    $dispatcher->dispatchToTasmiExaminer(
                        $newId,
                        'Penugasan mengajar baru',
                        "Anda ditugaskan mengajar {$mapel} di kelas {$kelas}.",
                        'assignment_created',
                        route('guru.diniyyah-scores.index'),
                        'info',
                    );
                }
            } else {
                $dispatcher->dispatchToTasmiExaminer(
                    $assignment->teacher_id,
                    'Penugasan mengajar diperbarui',
                    "Penugasan mengajar {$mapel} di kelas {$kelas} diperbarui.",
                    'assignment_updated',
                    route('guru.diniyyah-scores.index'),
                    'info',
                );
            }
        } elseif ($event === 'removed') {
            $dispatcher->dispatchToTasmiExaminer(
                $assignment->teacher_id,
                'Penugasan mengajar dicabut',
                "Penugasan mengajar {$mapel} di kelas {$kelas} dicabut oleh admin.",
                'assignment_removed',
                route('guru.dashboard'),
                'warning',
            );
        }
    }
}