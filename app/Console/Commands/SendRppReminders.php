<?php

namespace App\Console\Commands;

use App\Models\DiniyyahTeachingSchedule;
use App\Models\Rpp;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendRppReminders extends Command
{
    protected $signature = 'rpp:send-reminders {--date= : YYYY-MM-DD, default hari ini WIB}';
    protected $description = 'Kirim pengingat RPP untuk jadwal Diniyyah hari ini yang belum memiliki pertemuan bertanggal sama.';

    public function handle(NotificationDispatcher $notifications): int
    {
        $date = Carbon::parse($this->option('date') ?: now('Asia/Jakarta')->toDateString(), 'Asia/Jakarta');
        $schedules = DiniyyahTeachingSchedule::with('teacherAssignment.teacher.user')->where('day_of_week', $date->dayOfWeekIso)->get();
        $sent = 0;
        foreach ($schedules as $schedule) {
            $assignment = $schedule->teacherAssignment;
            $teacher = $assignment?->teacher;
            if (! $assignment || ! $teacher?->user_id) continue;
            $exists = Rpp::query()->where('diniyyah_teacher_assignment_id', $assignment->id)->whereHas('meetings', fn ($query) => $query->whereDate('tanggal_kbm', $date->toDateString()))->exists();
            if ($exists) continue;
            $notifications->dispatchToUser($teacher->user_id, 'Pengingat RPP', 'Jadwal mengajar hari ini belum memiliki RPP/pertemuan bertanggal hari ini.', 'rpp_reminder', route('guru.rpp.create'), 'warning');
            $sent++;
        }
        $this->info("{$sent} pengingat RPP dikirim.");
        return self::SUCCESS;
    }
}
