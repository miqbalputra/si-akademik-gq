<?php

namespace App\Observers;

use App\Models\DiniyyahTeachingSchedule;
use App\Services\DiniyyahScheduleChangeLogger;
use App\Services\NotificationDispatcher;
use Illuminate\Support\Facades\Auth;

/**
 * Mencatat perubahan jadwal mengajar diniyyah ke riwayat audit.
 *
 * `created`/`updated` hanya dicatat bila ada user terautentikasi (perubahan
 * user-initiated via Filament/HTTP) — seeding migration & operasi CLI tanpa
 * Auth tidak menghasilkan log noise. `deleting` selalu dicatat (capture context
 * sebelum DB hapus; child terhapus via DB cascade, bukan Eloquent, sehingga
 * observer child tidak fire).
 *
 * Juga mengirim notifikasi ke guru tsb saat jadwalnya berubah.
 */
class DiniyyahTeachingScheduleObserver
{
    public function __construct(
        private readonly DiniyyahScheduleChangeLogger $logger,
    ) {}

    public function created(DiniyyahTeachingSchedule $schedule): void
    {
        if (Auth::check()) {
            $this->logger->logScheduleCreated($schedule);
        }
        $this->notifySchedule($schedule, 'created');
    }

    public function updated(DiniyyahTeachingSchedule $schedule): void
    {
        if (Auth::check()) {
            $this->logger->logScheduleUpdated($schedule, $schedule->getOriginal());
        }
        $this->notifySchedule($schedule, 'updated');
    }

    public function deleting(DiniyyahTeachingSchedule $schedule): void
    {
        $this->logger->logScheduleDeleted($schedule);
        $this->notifySchedule($schedule, 'removed');
    }

    private function notifySchedule(DiniyyahTeachingSchedule $schedule, string $event): void
    {
        $assignment = $schedule->teacherAssignment ?? $schedule->teacherAssignment()->with(['classSubject.subject', 'teacher.user'])->first();
        if (! $assignment?->classSubject) {
            return;
        }
        $mapel = $assignment->classSubject->subject?->name ?? 'mapel';
        $dayLabel = match ((int) $schedule->day_of_week) {
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 0 => 'Minggu',
            default => "hari {$schedule->day_of_week}",
        };
        $session = (string) ($schedule->classSession?->session_name ?? $schedule->class_session_id);

        $verb = match ($event) {
            'created' => 'ditambahkan',
            'updated' => 'diubah',
            'removed' => 'dihapus',
            default => 'diperbarui',
        };

        app(NotificationDispatcher::class)->dispatchToTasmiExaminer(
            $assignment->teacher_id,
            'Jadwal mengajar diperbarui',
            "Jadwal mengajar {$mapel} hari {$dayLabel} sesi {$session} {$verb}.",
            'schedule_changed',
            route('guru.jadwal.riwayat'),
            $event === 'removed' ? 'warning' : 'info',
        );
    }
}