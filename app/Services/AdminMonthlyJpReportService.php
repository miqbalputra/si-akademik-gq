<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\SchoolHoliday;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Rekap penggajian bulanan lintas kelas. Berbeda dengan rekap jurnal lama,
 * sumber slotnya adalah jadwal agar guru yang belum mengisi jurnal tetap
 * terlihat oleh admin.
 */
class AdminMonthlyJpReportService
{
    public function __construct(
        private readonly AttendanceStatusClient $attendanceStatusClient,
        private readonly DiniyyahNoKbmAgendaService $noKbmAgendaService,
        private readonly TafsirScheduleGroupingService $tafsirGroups,
    ) {}

    /** @return array<string, mixed> */
    public function build(?int $academicTermId, int $month, int $year): array
    {
        $term = $academicTermId
            ? AcademicTerm::with('academicYear')->findOrFail($academicTermId)
            : AcademicTerm::with('academicYear')->where('is_active', true)->firstOrFail();
        $start = Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        if ($end->isFuture()) {
            $end = now('Asia/Jakarta')->endOfDay();
        }

        $schedules = DiniyyahTeachingSchedule::query()->with([
            'teacherAssignment.teacher',
            'teacherAssignment.classSubject.subject',
            'teacherAssignment.classSubject.classroomTerm.classroom',
            'classSession',
        ])->whereHas('teacherAssignment.classSubject.classroomTerm', fn ($query) => $query->where('academic_term_id', $term->id))->get();
        $teachers = Teacher::query()->with('user')->whereNotNull('user_id')->orderBy('name')->get();
        $journals = DiniyyahClassJournal::query()->with([
            'teacherAssignment.teacher',
            'teacherAssignment.classSubject.subject',
            'teacherAssignment.classSubject.classroomTerm.classroom',
            'substituteTeacher',
        ])->whereHas('teacherAssignment.classSubject.classroomTerm', fn ($query) => $query->where('academic_term_id', $term->id))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get();
        $holidays = SchoolHoliday::query()->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
            ->get()->keyBy(fn ($holiday) => $holiday->holiday_date->toDateString());
        $attendance = $this->attendanceStatusClient->statusesForTeachers(
            $schedules->map(fn ($schedule) => $schedule->teacherAssignment?->teacher)->filter()->unique('id')->values(), $start, $end, true,
        );
        $agendaTerms = $schedules->map(fn ($schedule) => $schedule->teacherAssignment?->classSubject?->classroomTerm)->filter()->unique('id')->values();
        $agendaEvents = $this->noKbmAgendaService->eventsForRange($agendaTerms, $start, $end);

        $summary = collect();
        foreach ($teachers as $teacher) {
            $this->seedTeacher($summary, $teacher);
        }
        $realized = collect();
        $missing = collect();
        $usedJournalIds = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateString = $date->toDateString();
            $daySchedules = $schedules->filter(fn ($schedule) => (int) $schedule->day_of_week === $date->dayOfWeekIso && $this->assignmentActiveOn($schedule->teacherAssignment, $date));
            if ($daySchedules->isEmpty()) {
                continue;
            }
            $dayJournals = $journals->filter(fn ($journal) => $journal->date?->toDateString() === $dateString);
            $holiday = $holidays->get($dateString);
            $groups = $this->tafsirGroups->simultaneousGroupsForDate($schedules, $date);
            $groupedScheduleIds = [];

            foreach ($groups as $group) {
                $groupSchedules = $group['schedules']->filter(fn ($schedule) => $daySchedules->contains('id', $schedule->id));
                if ($groupSchedules->count() < 2) {
                    continue;
                }
                $groupedScheduleIds = [...$groupedScheduleIds, ...$groupSchedules->pluck('id')->map(fn ($id) => (int) $id)->all()];
                $this->processTafsirGroup($groupSchedules, $dayJournals, $date, $holiday, $agendaEvents, $attendance, $summary, $realized, $missing, $usedJournalIds);
            }

            foreach ($daySchedules->reject(fn ($schedule) => in_array((int) $schedule->id, $groupedScheduleIds, true)) as $schedule) {
                $journal = $dayJournals->first(fn ($item) => $this->tafsirGroups->journalMatchesSchedule($item, $schedule));
                if ($journal) {
                    $usedJournalIds[$journal->id] = true;
                }
                $this->processRegularSlot($schedule, $journal, $date, $holiday, $agendaEvents, $attendance, $summary, $realized, $missing);
            }
        }

        // Jurnal ekstra tetap bernilai JP bagi guru efektif, walau slot jadwalnya tidak ditemukan.
        foreach ($journals->reject(fn ($journal) => isset($usedJournalIds[$journal->id])) as $journal) {
            $this->addRealizedJournal($journal, $summary, $realized, false);
        }

        $teachers = $summary->map(function (array $row): array {
            $row['classes'] = collect($row['classes'])->filter()->unique()->sort()->values()->all();
            $row['subjects'] = collect($row['subjects'])->filter()->unique()->sort()->values()->all();
            $row['missing_count'] = (int) $row['missing_count'];

            return $row;
        })->sortBy('name')->values();
        $realized = $realized->sortBy(fn (array $row) => [$row['date'], $row['session_time'], $row['teacher_name']])->values();
        $missing = $missing->sortBy(fn (array $row) => [$row['date'], $row['session_time'], $row['teacher_name']])->values();

        return compact('term', 'month', 'year', 'start', 'end', 'teachers', 'realized', 'missing') + [
            'stats' => [
                'total_teachers' => $teachers->count(),
                'total_jp' => (int) $teachers->sum('total_jp'),
                'total_missing' => $missing->count(),
                'total_tafsir' => (int) $teachers->sum('sesi_tafsir'),
            ],
        ];
    }

    private function processRegularSlot($schedule, $journal, Carbon $date, $holiday, Collection $events, array $attendance, Collection $summary, Collection $realized, Collection $missing): void
    {
        $assignment = $schedule->teacherAssignment;
        $teacher = $assignment?->teacher;
        $classTerm = $assignment?->classSubject?->classroomTerm;
        $subject = $assignment?->classSubject?->subject;
        $time = $this->tafsirGroups->resolveSessionTime($schedule, $date->dayOfWeekIso);
        $this->addAssignmentLabels($summary, $teacher, $classTerm?->name, $subject?->name);
        if ($journal) {
            $this->addRealizedJournal($journal, $summary, $realized, false);

            return;
        }
        $agenda = $classTerm ? $this->noKbmAgendaService->forClassroomTerm($events, $classTerm, $date) : null;
        $status = $this->slotStatus($teacher, $date, $holiday, $agenda, $attendance);
        if ($status === 'KOSONG') {
            $this->addMissing($missing, $summary, $teacher, $date, $time, [$classTerm?->name], [$subject?->name], 'Jam '.($schedule->classSession?->session_name ?? '-'), $status);
        }
    }

    private function processTafsirGroup(Collection $schedules, Collection $dayJournals, Carbon $date, $holiday, Collection $events, array $attendance, Collection $summary, Collection $realized, Collection $missing, array &$usedJournalIds): void
    {
        $first = $schedules->first();
        $teacher = $first->teacherAssignment?->teacher;
        $classes = $schedules->map(fn ($schedule) => $schedule->teacherAssignment?->classSubject?->classroomTerm?->name)->filter()->unique()->values()->all();
        $subjects = $schedules->map(fn ($schedule) => $schedule->teacherAssignment?->classSubject?->subject?->name)->filter()->unique()->values()->all();
        $time = $this->tafsirGroups->resolveSessionTime($first, $date->dayOfWeekIso);
        $this->addAssignmentLabels($summary, $teacher, $classes, $subjects);
        $matches = $dayJournals->filter(fn ($journal) => $schedules->contains(fn ($schedule) => $this->tafsirGroups->journalMatchesSchedule($journal, $schedule)));
        foreach ($matches as $journal) {
            $usedJournalIds[$journal->id] = true;
        }
        if ($matches->isNotEmpty()) {
            // Satu sesi Tafsir dapat menghasilkan beberapa record kelas. Kelompokkan
            // menurut guru efektif agar pengganti tidak pernah mengkredit guru asli.
            foreach ($matches->groupBy(fn ($journal) => $journal->effectiveTeacher()?->id ?: 0) as $teacherJournals) {
                $journal = $teacherJournals->first();
                $this->addTafsirRealized($journal, $classes, $subjects, $time, $summary, $realized);
            }

            return;
        }
        $agendaTerms = $schedules->map(fn ($schedule) => $schedule->teacherAssignment?->classSubject?->classroomTerm)->filter()->unique('id')->values();
        $agenda = $this->noKbmAgendaService->forClassroomTerms($events, $agendaTerms, $date);
        if ($this->slotStatus($teacher, $date, $holiday, $agenda, $attendance) === 'KOSONG') {
            $this->addMissing($missing, $summary, $teacher, $date, $time, $classes, $subjects, 'Tafsir serentak', 'KOSONG');
        }
    }

    private function addRealizedJournal($journal, Collection $summary, Collection $realized, bool $tafsir): void
    {
        $teacher = $journal->effectiveTeacher();
        if (! $teacher) {
            return;
        }
        $assignment = $journal->teacherAssignment;
        $class = $assignment?->classSubject?->classroomTerm?->name;
        $subject = $assignment?->classSubject?->subject?->name;
        if (strtolower((string) $journal->session_hour) === 'tafsir') {
            $this->addTafsirRealized($journal, [$class], [$subject], ['starts_at' => $journal->session_starts_at, 'ends_at' => $journal->session_ends_at], $summary, $realized);

            return;
        }
        $this->seedTeacher($summary, $teacher);
        $row = $summary->get($teacher->id);
        $row['total_jp'] += (int) $journal->jp_count;
        $row[$journal->substitute_teacher_id ? 'sesi_pengganti' : 'sesi_asli']++;
        $row['classes'][] = $class;
        $row['subjects'][] = $subject;
        $summary->put($teacher->id, $row);
        $realized->push($this->detailRow($journal, [$class], [$subject], (int) $journal->jp_count, false));
    }

    private function addTafsirRealized($journal, array $classes, array $subjects, array $time, Collection $summary, Collection $realized): void
    {
        $teacher = $journal->effectiveTeacher();
        if (! $teacher) {
            return;
        }
        $this->seedTeacher($summary, $teacher);
        $row = $summary->get($teacher->id);
        $row['sesi_tafsir']++;
        $row['total_jp']++;
        $row['classes'] = [...$row['classes'], ...$classes];
        $row['subjects'] = [...$row['subjects'], ...$subjects];
        $summary->put($teacher->id, $row);
        $detail = $this->detailRow($journal, $classes, $subjects, 1, true);
        $detail['session_time'] = $this->timeLabel($time);
        $realized->push($detail);
    }

    private function addMissing(Collection $missing, Collection $summary, $teacher, Carbon $date, array $time, array $classes, array $subjects, string $session, string $status): void
    {
        if (! $teacher) {
            return;
        }
        $this->seedTeacher($summary, $teacher);
        $row = $summary->get($teacher->id);
        $row['missing_count']++;
        $row['classes'] = [...$row['classes'], ...$classes];
        $row['subjects'] = [...$row['subjects'], ...$subjects];
        $summary->put($teacher->id, $row);
        $missing->push(['teacher_name' => $teacher->name, 'niy' => $teacher->niy, 'date' => $date->toDateString(), 'date_label' => $date->translatedFormat('l, d F Y'), 'session' => $session, 'session_time' => $this->timeLabel($time), 'classes' => collect($classes)->filter()->unique()->values()->all(), 'subjects' => collect($subjects)->filter()->unique()->values()->all(), 'status' => $status]);
    }

    private function detailRow($journal, array $classes, array $subjects, int $jp, bool $tafsir): array
    {
        return ['date' => $journal->date?->toDateString(), 'date_label' => $journal->date?->translatedFormat('l, d F Y'), 'session' => $tafsir ? 'Tafsir serentak' : 'Jam '.($journal->session_hour ?: '-'), 'session_time' => $this->timeLabel(['starts_at' => $journal->session_starts_at, 'ends_at' => $journal->session_ends_at]), 'classes' => collect($classes)->filter()->unique()->values()->all(), 'subjects' => collect($subjects)->filter()->unique()->values()->all(), 'teacher_name' => $journal->effectiveTeacher()?->name ?? '-', 'original_teacher' => $journal->teacherAssignment?->teacher?->name ?? '-', 'substitute_teacher' => $journal->substituteTeacher?->name, 'type' => $tafsir ? 'Tafsir serentak' : ($journal->substitute_teacher_id ? 'Pengganti' : 'Reguler'), 'material' => (string) ($journal->material ?: '-'), 'jp' => $jp];
    }

    private function seedTeacher(Collection $summary, $teacher): void
    {
        if ($teacher && ! $summary->has($teacher->id)) {
            $summary->put($teacher->id, ['teacher_id' => $teacher->id, 'name' => $teacher->name, 'niy' => $teacher->niy, 'status' => $teacher->status, 'classes' => [], 'subjects' => [], 'sesi_asli' => 0, 'sesi_pengganti' => 0, 'sesi_tafsir' => 0, 'total_jp' => 0, 'missing_count' => 0]);
        }
    }

    private function addAssignmentLabels(Collection $summary, $teacher, array|string|null $classes, array|string|null $subjects): void
    {
        if (! $teacher) {
            return;
        } $this->seedTeacher($summary, $teacher);
        $row = $summary->get($teacher->id);
        $row['classes'] = [...$row['classes'], ...collect($classes)->filter()->all()];
        $row['subjects'] = [...$row['subjects'], ...collect($subjects)->filter()->all()];
        $summary->put($teacher->id, $row);
    }

    private function assignmentActiveOn($assignment, Carbon $date): bool
    {
        $value = $date->toDateString();

        return (! $assignment?->starts_at || $assignment->starts_at->toDateString() <= $value) && (! $assignment?->ends_at || $assignment->ends_at->toDateString() >= $value);
    }

    private function slotStatus($teacher, Carbon $date, $holiday, $agenda, array $attendance): string
    {
        if ($holiday) {
            return 'LIBUR';
        } if ($agenda) {
            return 'AGENDA';
        } $record = $attendance[(string) ($teacher?->id ?? '')] ?? [];
        $status = ($record['available'] ?? false) ? ($record['statuses'][$date->toDateString()] ?? null) : null;

        return $this->attendanceStatusClient->isExempt($status) ? strtoupper((string) $status) : 'KOSONG';
    }

    private function timeLabel(array $time): string
    {
        $values = collect([$time['starts_at'] ?? null, $time['ends_at'] ?? null])->filter()->map(fn ($value) => substr((string) $value, 0, 5));

        return $values->isNotEmpty() ? $values->implode(' - ') : '-';
    }
}
