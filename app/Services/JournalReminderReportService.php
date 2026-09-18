<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class JournalReminderReportService
{
    public function __construct(private readonly AdminMonthlyJpReportService $scheduleReport) {}

    /** @return array<string, mixed> */
    public function build(?int $academicTermId, Carbon|string $start, Carbon|string $end): array
    {
        $source = $this->scheduleReport->buildForRange($academicTermId, $start, $end);
        $missing = collect($source['missing']);
        $teachers = $missing
            ->groupBy('teacher_id')
            ->map(function (Collection $rows): array {
                $first = $rows->first();

                return [
                    'teacher_id' => (int) $first['teacher_id'],
                    'teacher_name' => $first['teacher_name'],
                    'niy' => $first['niy'],
                    'missing_count' => $rows->count(),
                    'rows' => $rows
                        ->sortBy(fn (array $row) => [$row['date'], $row['session_time'], $row['session']])
                        ->values(),
                ];
            })
            ->sortBy([
                ['missing_count', 'desc'],
                ['teacher_name', 'asc'],
            ])
            ->values();

        $unverifiedTeacherIds = collect($source['attendance'] ?? [])
            ->filter(fn (array $status): bool => ! ($status['available'] ?? false))
            ->keys()
            ->map(fn (string $id): int => (int) $id);
        $unverifiedReminderTeachers = $teachers
            ->pluck('teacher_id')
            ->filter(fn (int $id): bool => $unverifiedTeacherIds->contains($id))
            ->values();

        return [
            'term' => $source['term'],
            'start' => $source['start'],
            'end' => $source['end'],
            'generated_at' => now('Asia/Jakarta'),
            'teachers' => $teachers,
            'stats' => [
                'teachers_to_remind' => $teachers->count(),
                'total_missing' => $missing->count(),
                'attendance_unverified_teachers' => $unverifiedReminderTeachers->count(),
            ],
        ];
    }
}
