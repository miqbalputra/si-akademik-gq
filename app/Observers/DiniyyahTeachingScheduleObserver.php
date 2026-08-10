<?php

namespace App\Observers;

use App\Models\DiniyyahTeachingSchedule;
use App\Services\DiniyyahScheduleChangeLogger;
use Illuminate\Support\Facades\Auth;

/**
 * Mencatat perubahan jadwal mengajar diniyyah ke riwayat audit.
 *
 * `created`/`updated` hanya dicatat bila ada user terautentikasi (perubahan
 * user-initiated via Filament/HTTP) — seeding migration & operasi CLI tanpa
 * Auth tidak menghasilkan log noise. `deleting` selalu dicatat (capture context
 * sebelum DB hapus; child terhapus via DB cascade, bukan Eloquent, sehingga
 * observer child tidak fire).
 */
class DiniyyahTeachingScheduleObserver
{
    public function __construct(private readonly DiniyyahScheduleChangeLogger $logger)
    {
    }

    public function created(DiniyyahTeachingSchedule $schedule): void
    {
        if (! Auth::check()) {
            return;
        }

        $this->logger->logScheduleCreated($schedule);
    }

    public function updated(DiniyyahTeachingSchedule $schedule): void
    {
        if (! Auth::check()) {
            return;
        }

        $this->logger->logScheduleUpdated($schedule, $schedule->getOriginal());
    }

    public function deleting(DiniyyahTeachingSchedule $schedule): void
    {
        $this->logger->logScheduleDeleted($schedule);
    }
}