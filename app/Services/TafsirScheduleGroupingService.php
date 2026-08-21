<?php

namespace App\Services;

use App\Support\SessionTimetable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Menentukan sesi Tafsir yang benar-benar diajar bersamaan.
 *
 * Subject Tafsir tidak otomatis berarti serentak. Sesi hanya digabung bila
 * minimal dua kelas berbeda milik guru yang sama berlangsung pada hari dan
 * rentang waktu aktual yang sama. Waktu diambil dari SessionTimetable agar
 * tetap benar bila nama sesi antar kelas berbeda.
 */
class TafsirScheduleGroupingService
{
    /** @var array<string, array{starts_at: ?string, ends_at: ?string}> */
    private array $timeCache = [];

    /**
     * @param  Collection<int, mixed>  $schedules
     * @return Collection<int, array{key: string, schedules: Collection<int, mixed>, assignment_ids: list<int>, classroom_term_ids: list<int>, starts_at: string, ends_at: string, day_of_week: int}>
     */
    public function simultaneousGroupsForDate(Collection $schedules, Carbon|string $date): Collection
    {
        $date = Carbon::parse($date, 'Asia/Jakarta');
        $dayOfWeek = $date->dayOfWeekIso;

        return $schedules
            ->filter(fn ($schedule): bool => $this->isTafsirSchedule($schedule))
            ->filter(fn ($schedule): bool => (int) ($schedule->day_of_week ?? 0) === $dayOfWeek)
            ->filter(fn ($schedule): bool => $this->assignmentActiveOn($schedule->teacherAssignment ?? null, $date))
            ->map(function ($schedule) use ($dayOfWeek) {
                $time = $this->resolveSessionTime($schedule, $dayOfWeek);
                $teacherId = $schedule->teacherAssignment?->teacher_id;
                $academicTermId = $schedule->teacherAssignment?->classSubject?->classroomTerm?->academic_term_id;

                // Waktu tidak lengkap tidak boleh disatukan secara spekulatif.
                if (! $teacherId || ! $academicTermId || ! $time['starts_at'] || ! $time['ends_at']) {
                    return null;
                }

                return [
                    'schedule' => $schedule,
                    'key' => implode('|', [$teacherId, $academicTermId, $dayOfWeek, $time['starts_at'], $time['ends_at']]),
                    'starts_at' => $time['starts_at'],
                    'ends_at' => $time['ends_at'],
                ];
            })
            ->filter()
            ->groupBy('key')
            ->map(function (Collection $items, string $key) use ($dayOfWeek): ?array {
                $schedulesInGroup = $items->pluck('schedule')->values();
                $classroomTermIds = $schedulesInGroup
                    ->map(fn ($schedule) => (int) ($schedule->teacherAssignment?->classSubject?->classroom_term_id ?? 0))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (count($classroomTermIds) < 2) {
                    return null;
                }

                return [
                    'key' => $key,
                    'schedules' => $schedulesInGroup,
                    'assignment_ids' => $schedulesInGroup
                        ->pluck('diniyyah_teacher_assignment_id')
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                        ->all(),
                    'classroom_term_ids' => $classroomTermIds,
                    'starts_at' => (string) $items->first()['starts_at'],
                    'ends_at' => (string) $items->first()['ends_at'],
                    'day_of_week' => $dayOfWeek,
                ];
            })
            ->filter()
            ->values();
    }

    /** @param Collection<int, mixed> $schedules */
    public function isSimultaneousSchedule(Collection $schedules, mixed $schedule, Carbon|string $date): bool
    {
        $scheduleId = (int) ($schedule->id ?? 0);

        return $this->simultaneousGroupsForDate($schedules, $date)
            ->contains(fn (array $group): bool => $group['schedules']->contains(fn ($item) => (int) ($item->id ?? 0) === $scheduleId));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $groups
     * @param  array<int, int|string>  $assignmentIds
     * @return array<string, mixed>|null
     */
    public function groupContainingAssignments(Collection $groups, array $assignmentIds): ?array
    {
        $assignmentIds = collect($assignmentIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($assignmentIds->isEmpty()) {
            return null;
        }

        return $groups->first(fn (array $group): bool => $assignmentIds
            ->every(fn (int $id): bool => in_array($id, $group['assignment_ids'], true)));
    }

    public function isTafsirSchedule(mixed $schedule): bool
    {
        $subject = $schedule->teacherAssignment?->classSubject?->subject ?? null;

        return $subject !== null && (
            strtolower((string) $subject->code) === SessionTimetable::SESSION_TAFSIR
            || str_contains(strtolower((string) $subject->name), 'tafsir')
        );
    }

    /**
     * Pencocokan backward-compatible: jurnal Tafsir lama selalu menyimpan
     * session_hour="tafsir", termasuk saat jadwal kelasnya memakai sesi reguler.
     */
    public function journalMatchesSchedule(mixed $journal, mixed $schedule): bool
    {
        if ((int) ($journal->diniyyah_teacher_assignment_id ?? 0) !== (int) ($schedule->diniyyah_teacher_assignment_id ?? 0)) {
            return false;
        }

        $sessionName = (string) ($schedule->classSession?->session_name ?? '');
        if ((string) ($journal->session_hour ?? '') === $sessionName) {
            return true;
        }

        return $this->isTafsirSchedule($schedule)
            && strtolower((string) ($journal->session_hour ?? '')) === SessionTimetable::SESSION_TAFSIR;
    }

    /**
     * @return array{starts_at: ?string, ends_at: ?string}
     */
    public function resolveSessionTime(mixed $schedule, ?int $dayOfWeek = null): array
    {
        $classroom = $schedule->teacherAssignment?->classSubject?->classroomTerm?->classroom ?? null;
        $sessionName = (string) ($schedule->classSession?->session_name ?? '');
        $dayOfWeek ??= (int) ($schedule->day_of_week ?? 0);

        if ($classroom && $sessionName !== '' && $dayOfWeek > 0) {
            $cacheKey = $classroom->id.'|'.$dayOfWeek.'|'.$sessionName;
            if (! array_key_exists($cacheKey, $this->timeCache)) {
                $resolved = SessionTimetable::resolve($classroom->id, $dayOfWeek, $sessionName);
                $this->timeCache[$cacheKey] = [
                    'starts_at' => $resolved['starts_at'] ?? null,
                    'ends_at' => $resolved['ends_at'] ?? null,
                ];
            }

            if ($this->timeCache[$cacheKey]['starts_at'] && $this->timeCache[$cacheKey]['ends_at']) {
                return $this->timeCache[$cacheKey];
            }
        }

        return [
            'starts_at' => $schedule->classSession?->starts_at ?? null,
            'ends_at' => $schedule->classSession?->ends_at ?? null,
        ];
    }

    private function assignmentActiveOn(mixed $assignment, Carbon $date): bool
    {
        $dateString = $date->toDateString();
        $startsAt = $assignment?->starts_at ? Carbon::parse($assignment->starts_at)->toDateString() : null;
        $endsAt = $assignment?->ends_at ? Carbon::parse($assignment->ends_at)->toDateString() : null;

        return ($startsAt === null || $startsAt <= $dateString)
            && ($endsAt === null || $endsAt >= $dateString);
    }
}
