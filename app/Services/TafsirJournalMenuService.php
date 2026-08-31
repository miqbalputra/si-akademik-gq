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
            ->pluck('day_of_week')
            ->map(fn ($day): int => (int) $day)
            ->filter(fn (int $day): bool => $day >= 1 && $day <= 7)
            ->unique()
            ->contains(function (int $dayOfWeek) use ($schedules, $referenceDate): bool {
                $nextMeetingDate = $referenceDate->copy()->startOfWeek()->addDays($dayOfWeek - 1);

                if ($nextMeetingDate->lt($referenceDate)) {
                    $nextMeetingDate->addWeek();
                }

                return $this->tafsirScheduleGroupingService
                    ->simultaneousGroupsForDate($schedules, $nextMeetingDate)
                    ->isNotEmpty();
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
