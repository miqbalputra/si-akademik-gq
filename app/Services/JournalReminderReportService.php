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
            'attendance_unverified_teacher_ids' => $unverifiedReminderTeachers->all(),
            'stats' => [
                'teachers_to_remind' => $teachers->count(),
                'total_missing' => $missing->count(),
                'attendance_unverified_teachers' => $unverifiedReminderTeachers->count(),
            ],
        ];
    }

    /**
     * Limit a ready-made report to one teacher without recalculating schedules.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>|null
     */
    public function forTeacher(array $report, int $teacherId): ?array
    {
        $teachers = $report['teachers']
            ->filter(fn (array $teacher): bool => $teacher['teacher_id'] === $teacherId)
            ->values();

        if ($teachers->isEmpty()) {
            return null;
        }

        $report['teachers'] = $teachers;
        $report['stats'] = [
            'teachers_to_remind' => $teachers->count(),
            'total_missing' => (int) $teachers->sum('missing_count'),
            'attendance_unverified_teachers' => collect($report['attendance_unverified_teacher_ids'] ?? [])
                ->intersect($teachers->pluck('teacher_id'))
                ->count(),
        ];

        return $report;
    }
}
