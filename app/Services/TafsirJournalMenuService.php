<?php

namespace App\Services;

use App\Models\DiniyyahTeachingSchedule;
use App\Models\Teacher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TafsirJournalMenuService
{
    public function __construct(private readonly TafsirScheduleGroupingService $tafsirScheduleGroupingService) {}

    /**
     * Menu Jurnal Tafsir hanya ditampilkan bila guru memiliki sedikitnya satu
     * kelompok Tafsir serentak yang masih aktif. Pemeriksaan dilakukan terhadap
     * tanggal pertemuan berikutnya untuk setiap hari jadwal, sehingga menu tidak
     * hilang saat dibuka di hari lain.
     */
    public function hasActiveSimultaneousSchedule(Teacher $teacher, Carbon|string|null $referenceDate = null): bool
    {
        $referenceDate = Carbon::parse($referenceDate ?? now('Asia/Jakarta'), 'Asia/Jakarta')->startOfDay();
        $schedules = $this->tafsirSchedulesFor($teacher);

        return $schedules
            ->filter(fn ($schedule): bool => $this->tafsirScheduleGroupingService->isTafsirSchedule($schedule))
            ->contains(function ($schedule) use ($schedules, $referenceDate): bool {
                $notBefore = $referenceDate->copy();
                if ($schedule->version_status === DiniyyahTeachingSchedule::STATUS_ACTIVE
                    && $schedule->effective_from
                    && $schedule->effective_from->gt($notBefore)) {
                    $notBefore = $schedule->effective_from->copy()->startOfDay();
                }

                $dayOfWeek = (int) $schedule->day_of_week;
                if ($dayOfWeek < 1 || $dayOfWeek > 7) {
                    return false;
                }

                $nextMeetingDate = $notBefore->copy()->startOfWeek()->addDays($dayOfWeek - 1);
                if ($nextMeetingDate->lt($notBefore)) {
                    $nextMeetingDate->addWeek();
                }
                if ($schedule->effective_until && $schedule->effective_until->lt($nextMeetingDate)) {
                    return false;
                }

                return $this->tafsirScheduleGroupingService
                    ->simultaneousGroupsForDate($schedules, $nextMeetingDate)
                    ->contains(fn (array $group): bool => $group['schedules']->contains(fn ($item) => (int) $item->id === (int) $schedule->id));
            });
    }

    /** @return Collection<int, DiniyyahTeachingSchedule> */
    private function tafsirSchedulesFor(Teacher $teacher): Collection
    {
        return DiniyyahTeachingSchedule::with([
            'teacherAssignment.classSubject.subject',
            'teacherAssignment.classSubject.classroomTerm.classroom',
            'classSession',
        ])->whereHas('teacherAssignment', fn ($query) => $query->where('teacher_id', $teacher->id))
            ->get()
            ->filter(fn ($schedule) => $this->tafsirScheduleGroupingService->isTafsirSchedule($schedule))
            ->values();
    }
}
