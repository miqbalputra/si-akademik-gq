<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ClassEnrollment;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\DiniyyahSubject;
use App\Models\SchoolEvent;
use App\Models\SchoolHoliday;
use App\Models\Teacher;
use App\Support\SessionTimetable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Satu sumber data untuk laporan jurnal Diniyyah.
 *
 * Scope guru memakai guru efektif (guru asli atau pengganti), sedangkan
 * scope manajemen dapat memakai filter lintas guru efektif, kelas, mapel, dan tipe.
 */
class DiniyyahJournalReportService
{
    public function __construct(
        private readonly DiniyyahNoKbmAgendaService $noKbmAgendaService,
        private readonly TafsirScheduleGroupingService $tafsirScheduleGroupingService,
    ) {}

    /** @return array<string, mixed> */
    public function build(array $filters = [], ?int $teacherId = null): array
    {
        $filters = $this->normalizeFilters($filters);
        $journals = $this->query($filters, $teacherId)->get();
        $rows = $this->mapRows($journals);
        $agendaRows = $this->agendaRows($filters, $teacherId, $journals);
        $rows = $rows->concat($agendaRows)
            ->sortBy(fn (array $row) => [
                $row['date'] ?? '',
                $row['session_time'] ?? '',
                $row['session_hour'] ?? '',
            ])
            ->values();
        $term = $filters['academic_term_id']
            ? AcademicTerm::with('academicYear')->find($filters['academic_term_id'])
            : null;

        return [
            'rows' => $rows,
            'agenda_rows' => $agendaRows,
            'filters' => $filters,
            'stats' => $this->buildStats($rows),
            'term' => $term,
            'filter_labels' => $this->filterLabels($filters, $term),
        ];
    }

    /** @return array<string, Collection<int, mixed>> */
    public function options(): array
    {
        return [
            'terms' => $this->academicTerms(),
            'teachers' => Teacher::query()
                ->orderBy('name')
                ->get(['id', 'name', 'niy', 'status']),
            'classes' => ClassroomTerm::query()
                ->whereHas('diniyyahClassSubjects.teacherAssignments')
                ->orderBy('name')
                ->get(['id', 'name', 'academic_term_id']),
            'subjects' => DiniyyahSubject::query()
                ->whereHas('classSubjects.teacherAssignments')
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    /** @return Collection<int, AcademicTerm> */
    public function academicTerms(): Collection
    {
        return AcademicTerm::query()
            ->with('academicYear')
            ->orderByDesc('starts_at')
            ->get();
    }

    /** @return Builder<DiniyyahClassJournal> */
    public function query(array $filters = [], ?int $teacherId = null): Builder
    {
        $filters = $this->normalizeFilters($filters);

        return DiniyyahClassJournal::query()
            ->with([
                'teacherAssignment.teacher',
                'substituteTeacher',
                'teacherAssignment.classSubject.subject',
                'teacherAssignment.classSubject.classroomTerm.classroom',
                'teacherAssignment.classSubject.classroomTerm.academicTerm.academicYear',
                'absences.classEnrollment.student',
            ])
            ->when($teacherId, function (Builder $query, int $id): void {
                $this->whereEffectiveTeacher($query, $id);
            })
            ->when($filters['academic_term_id'], function (Builder $query, int $id): void {
                $query->whereHas('teacherAssignment.classSubject.classroomTerm', function (Builder $q) use ($id): void {
                    $q->where('academic_term_id', $id);
                });
            })
            ->when($filters['date_from'], fn (Builder $query, string $date) => $query->whereDate('date', '>=', $date))
            ->when($filters['date_until'], fn (Builder $query, string $date) => $query->whereDate('date', '<=', $date))
            ->when($filters['teacher_id'], function (Builder $query, int $id): void {
                $this->whereEffectiveTeacher($query, $id);
            })
            ->when($filters['classroom_term_id'], function (Builder $query, int $id): void {
                $query->whereHas('teacherAssignment.classSubject', fn (Builder $q) => $q->where('classroom_term_id', $id));
            })
            ->when($filters['subject_id'], function (Builder $query, int $id): void {
                $query->whereHas('teacherAssignment.classSubject', fn (Builder $q) => $q->where('subject_id', $id));
            })
            ->when($filters['type'] === 'regular', fn (Builder $query) => $query->whereNull('substitute_teacher_id'))
            ->when($filters['type'] === 'substitute', fn (Builder $query) => $query->whereNotNull('substitute_teacher_id'))
            ->when($filters['search'], function (Builder $query, string $search): void {
                $like = '%'.$search.'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->whereHas('teacherAssignment.teacher', fn (Builder $q) => $q->where('name', 'like', $like))
                        ->orWhereHas('substituteTeacher', fn (Builder $q) => $q->where('name', 'like', $like))
                        ->orWhereHas('teacherAssignment.classSubject.classroomTerm', fn (Builder $q) => $q->where('name', 'like', $like))
                        ->orWhereHas('teacherAssignment.classSubject.subject', fn (Builder $q) => $q->where('name', 'like', $like))
                        ->orWhere('material', 'like', $like);
                });
            })
            ->orderBy('date')
            ->orderBy('session_starts_at')
            ->orderBy('session_hour');
    }

    /** @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        $academicTermId = $filters['academic_term_id'] ?? $filters['term'] ?? null;
        $teacherId = $filters['teacher_id'] ?? $filters['guru'] ?? null;
        $type = $filters['type'] ?? $filters['tipe'] ?? null;

        return [
            'academic_term_id' => filled($academicTermId) ? (int) $academicTermId : null,
            'date_from' => filled($filters['date_from'] ?? null) ? (string) $filters['date_from'] : null,
            'date_until' => filled($filters['date_until'] ?? null) ? (string) $filters['date_until'] : null,
            'teacher_id' => filled($teacherId) ? (int) $teacherId : null,
            'classroom_term_id' => filled($filters['classroom_term_id'] ?? null) ? (int) $filters['classroom_term_id'] : null,
            'subject_id' => filled($filters['subject_id'] ?? null) ? (int) $filters['subject_id'] : null,
            'type' => in_array($type, ['regular', 'substitute'], true) ? $type : null,
            'search' => filled($filters['search'] ?? null) ? trim((string) $filters['search']) : null,
        ];
    }

    /** @param Collection<int, DiniyyahClassJournal> $journals */
    private function mapRows(Collection $journals): Collection
    {
        $classroomTermIds = $journals
            ->map(fn (DiniyyahClassJournal $journal) => $journal->teacherAssignment?->classSubject?->classroom_term_id)
            ->filter()
            ->unique()
            ->values();

        $activeEnrollmentCounts = $classroomTermIds->isEmpty()
            ? collect()
            : ClassEnrollment::query()
                ->whereIn('classroom_term_id', $classroomTermIds)
                ->where('status', 'active')
                ->selectRaw('classroom_term_id, count(*) as aggregate')
                ->groupBy('classroom_term_id')
                ->pluck('aggregate', 'classroom_term_id');

        return $journals->map(function (DiniyyahClassJournal $journal) use ($activeEnrollmentCounts): array {
            $assignment = $journal->teacherAssignment;
            $classSubject = $assignment?->classSubject;
            $classroomTerm = $classSubject?->classroomTerm;
            $subject = $classSubject?->subject;
            $absenceCounts = [
                'sick' => 0,
                'permission' => 0,
                'absent' => 0,
                'skipped' => 0,
            ];

            foreach ($journal->absences as $absence) {
                if (array_key_exists($absence->status, $absenceCounts)) {
                    $absenceCounts[$absence->status]++;
                }
            }

            $absenceTotal = array_sum($absenceCounts);
            $activeEnrollmentCount = (int) ($activeEnrollmentCounts[$classroomTerm?->id] ?? 0);
            $creditedTeacher = $journal->effectiveTeacher();
            $isSubstitute = $journal->substitute_teacher_id !== null;

            return [
                'journal' => $journal,
                'id' => $journal->id,
                'date' => $journal->date?->toDateString(),
                'date_label' => $journal->date?->locale('id')->translatedFormat('d M Y'),
                'session_hour' => (string) $journal->session_hour,
                'session_label' => strtolower((string) $journal->session_hour) === 'tafsir'
                    ? 'Tafsir serentak'
                    : 'Jam '.($journal->session_hour ?: '-'),
                'session_time' => $this->sessionTime($journal),
                'classroom_term_id' => $classroomTerm?->id,
                'subject_id' => $subject?->id,
                'kelas' => $classroomTerm?->name ?? '-',
                'mapel' => $subject?->name ?? '-',
                'guru_asli_id' => $assignment?->teacher_id,
                'guru_asli' => $assignment?->teacher?->name ?? '-',
                'pengganti_id' => $journal->substitute_teacher_id,
                'pengganti' => $journal->substituteTeacher?->name,
                'guru_mengajar_id' => $creditedTeacher?->id,
                'guru_mengajar' => $creditedTeacher?->name ?? '-',
                'type' => $isSubstitute ? 'substitute' : 'regular',
                'type_label' => $isSubstitute ? 'Pengganti' : 'Reguler',
                'material' => (string) ($journal->material ?? '-'),
                'jp' => (int) ($journal->jp_count ?? 0),
                'hadir' => max(0, $activeEnrollmentCount - $absenceTotal),
                'sakit' => $absenceCounts['sick'],
                'izin' => $absenceCounts['permission'],
                'alpa' => $absenceCounts['absent'],
                'bolos' => $absenceCounts['skipped'],
            ];
        })->values();
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function buildStats(Collection $rows): array
    {
        $journalRows = $rows->reject(fn (array $row): bool => ($row['type'] ?? null) === 'agenda');
        $byTeacher = $journalRows
            ->groupBy('guru_mengajar_id')
            ->map(function (Collection $teacherRows): array {
                return [
                    'teacher_id' => $teacherRows->first()['guru_mengajar_id'],
                    'name' => $teacherRows->first()['guru_mengajar'],
                    'journals' => $teacherRows->count(),
                    'jp' => (int) $teacherRows->sum('jp'),
                ];
            })
            ->filter(fn (array $row) => filled($row['teacher_id']))
            ->sortByDesc(fn (array $row) => [$row['journals'], $row['name']])
            ->values();

        $byClass = $rows
            ->groupBy('kelas')
            ->map(fn (Collection $classRows): array => [
                'name' => $classRows->first()['kelas'],
                'journals' => $classRows->count(),
                'jp' => (int) $classRows->sum('jp'),
            ])
            ->sortByDesc('journals')
            ->values();

        return [
            'total_jurnal' => $journalRows->count(),
            'total_slot' => $rows->count(),
            'agenda' => $rows->where('type', 'agenda')->count(),
            'total_guru' => $journalRows->pluck('guru_mengajar_id')->filter()->unique()->count(),
            'total_kelas' => $rows->pluck('classroom_term_id')->filter()->unique()->count(),
            'total_mapel' => $rows->pluck('subject_id')->filter()->unique()->count(),
            'total_jp' => (int) $rows->sum('jp'),
            'jurnal_reguler' => $journalRows->where('type', 'regular')->count(),
            'jurnal_pengganti' => $journalRows->where('type', 'substitute')->count(),
            'hari_tercatat' => $rows->pluck('date')->filter()->unique()->count(),
            'total_hadir' => (int) $journalRows->sum('hadir'),
            'total_sakit' => (int) $journalRows->sum('sakit'),
            'total_izin' => (int) $journalRows->sum('izin'),
            'total_alpa' => (int) $journalRows->sum('alpa'),
            'total_bolos' => (int) $journalRows->sum('bolos'),
            'by_teacher' => $byTeacher,
            'by_class' => $byClass,
        ];
    }

    /** @return array<string, string> */
    private function filterLabels(array $filters, ?AcademicTerm $term): array
    {
        $teacher = $filters['teacher_id'] ? Teacher::find($filters['teacher_id']) : null;
        $classroomTerm = $filters['classroom_term_id'] ? ClassroomTerm::find($filters['classroom_term_id']) : null;
        $subject = $filters['subject_id'] ? DiniyyahSubject::find($filters['subject_id']) : null;

        return [
            'Periode' => $term ? trim(($term->academicYear?->name ?? '').' - '.$term->name) : 'Semua periode',
            'Tanggal' => $filters['date_from'] || $filters['date_until']
                ? ($filters['date_from'] ?? 'awal').' s.d. '.($filters['date_until'] ?? 'akhir')
                : 'Semua tanggal',
            'Guru' => $teacher?->name ?? 'Semua guru',
            'Kelas' => $classroomTerm?->name ?? 'Semua kelas',
            'Mapel' => $subject?->name ?? 'Semua mapel',
            'Tipe jurnal' => match ($filters['type']) {
                'regular' => 'Reguler',
                'substitute' => 'Pengganti',
                default => 'Semua tipe',
            },
            'Pencarian' => $filters['search'] ?? 'Tidak ada',
        ];
    }

    /**
     * Agenda tanpa KBM adalah baris virtual laporan: tidak ada record jurnal
     * yang dibuat, tetapi slot tetap terlihat agar laporan menjelaskan mengapa
     * guru tidak perlu mengisi jurnal.
     *
     * @param  Collection<int, DiniyyahClassJournal>  $journals
     * @return Collection<int, array<string, mixed>>
     */
    private function agendaRows(array $filters, ?int $teacherId, Collection $journals): Collection
    {
        // Agenda virtual bukan jurnal pengganti. Saat pengguna memilih filter
        // khusus "Pengganti", hasil agenda harus tetap kosong agar filter laporan
        // tidak bocor ke kategori lain.
        if (($filters['type'] ?? null) === 'substitute') {
            return collect();
        }

        $term = $filters['academic_term_id']
            ? AcademicTerm::query()->find($filters['academic_term_id'])
            : null;

        $eventsQuery = SchoolEvent::query()
            ->with('targetClassroomTerms.classroom')
            ->noKbm()
            ->when($term, fn (Builder $query) => $query->where('academic_term_id', $term->id))
            ->when($filters['date_from'], fn (Builder $query, string $date) => $query->whereDate('ends_on', '>=', $date))
            ->when($filters['date_until'], fn (Builder $query, string $date) => $query->whereDate('starts_on', '<=', $date))
            ->orderBy('starts_on')
            ->orderBy('title');
        $events = $eventsQuery->get();

        if ($events->isEmpty()) {
            return collect();
        }

        $start = $filters['date_from']
            ? Carbon::parse($filters['date_from'], 'Asia/Jakarta')->startOfDay()
            : ($term?->starts_at
                ? Carbon::parse($term->starts_at, 'Asia/Jakarta')->startOfDay()
                : Carbon::parse($events->min('starts_on'), 'Asia/Jakarta')->startOfDay());
        $end = $filters['date_until']
            ? Carbon::parse($filters['date_until'], 'Asia/Jakarta')->endOfDay()
            : ($term?->ends_at
                ? Carbon::parse($term->ends_at, 'Asia/Jakarta')->endOfDay()
                : Carbon::parse($events->max('ends_on'), 'Asia/Jakarta')->endOfDay());

        if ($start->greaterThan($end)) {
            return collect();
        }

        $schedules = DiniyyahTeachingSchedule::query()
            ->with([
                'teacherAssignment.teacher',
                'teacherAssignment.classSubject.subject',
                'teacherAssignment.classSubject.classroomTerm.classroom',
                'classSession',
            ])
            ->whereHas('teacherAssignment', function (Builder $query) use ($filters, $teacherId): void {
                $query
                    ->when($teacherId, fn (Builder $query, int $id) => $query->where('teacher_id', $id))
                    ->when($filters['teacher_id'], fn (Builder $query, int $id) => $query->where('teacher_id', $id))
                    ->when($filters['academic_term_id'], fn (Builder $query, int $id) => $query->whereHas('classSubject.classroomTerm', fn (Builder $q) => $q->where('academic_term_id', $id)));
            })
            ->when($filters['classroom_term_id'], fn (Builder $query, int $id) => $query->whereHas('teacherAssignment.classSubject', fn (Builder $q) => $q->where('classroom_term_id', $id)))
            ->when($filters['subject_id'], fn (Builder $query, int $id) => $query->whereHas('teacherAssignment.classSubject', fn (Builder $q) => $q->where('subject_id', $id)))
            ->get()
            ->filter(fn ($schedule): bool => $schedule->teacherAssignment?->classSubject?->classroomTerm !== null)
            ->values();

        if ($schedules->isEmpty()) {
            return collect();
        }

        $terms = $schedules
            ->map(fn ($schedule) => $schedule->teacherAssignment?->classSubject?->classroomTerm)
            ->filter()
            ->unique('id')
            ->values();
        $agendaEvents = $this->noKbmAgendaService->eventsForRange($terms, $start, $end);
        $holidayDates = SchoolHoliday::query()
            ->whereDate('holiday_date', '>=', $start->toDateString())
            ->whereDate('holiday_date', '<=', $end->toDateString())
            ->pluck('holiday_date')
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())
            ->flip();

        $rows = collect();
        $date = $start->copy();

        while ($date <= $end) {
            $dateString = $date->toDateString();
            if ($holidayDates->has($dateString)) {
                $date->addDay();

                continue;
            }
            $daySchedules = $schedules->where('day_of_week', $date->dayOfWeekIso)
                ->filter(fn ($schedule): bool => $this->assignmentActiveOn($schedule->teacherAssignment, $date));

            $dayJournals = $journals->filter(fn (DiniyyahClassJournal $journal): bool => $journal->date?->toDateString() === $dateString);
            $tafsirGroups = $this->tafsirScheduleGroupingService->simultaneousGroupsForDate($daySchedules, $date);
            $simultaneousScheduleIds = $tafsirGroups
                ->flatMap(fn (array $group) => $group['schedules']->pluck('id'))
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($daySchedules->reject(fn ($schedule) => in_array((int) $schedule->id, $simultaneousScheduleIds, true)) as $schedule) {
                $assignment = $schedule->teacherAssignment;
                $classroomTerm = $assignment->classSubject->classroomTerm;
                if ($dayJournals->contains(fn ($journal) => $this->tafsirScheduleGroupingService->journalMatchesSchedule($journal, $schedule))) {
                    continue;
                }
                $agenda = $this->noKbmAgendaService->forClassroomTerm($agendaEvents, $classroomTerm, $date);
                if ($agenda !== null && $this->agendaMatchesSearch($agenda, $schedule, $filters['search'])) {
                    $rows->push($this->agendaReportRow($schedule, $date, $agenda));
                }
            }

            foreach ($tafsirGroups as $group) {
                $groupSchedules = $group['schedules'];
                $hasJournal = $dayJournals->contains(fn ($journal) => $groupSchedules
                    ->contains(fn ($schedule) => $this->tafsirScheduleGroupingService->journalMatchesSchedule($journal, $schedule)));
                if ($hasJournal) {
                    continue;
                }
                $tafsirTerms = $groupSchedules
                    ->map(fn ($schedule) => $schedule->teacherAssignment?->classSubject?->classroomTerm)
                    ->filter()
                    ->unique('id')
                    ->values();
                $agenda = $this->noKbmAgendaService->forClassroomTerms($agendaEvents, $tafsirTerms, $date);
                if ($agenda !== null && $this->agendaMatchesSearch($agenda, $groupSchedules->first(), $filters['search'])) {
                    $rows->push($this->agendaTafsirReportRow($groupSchedules, $date, $agenda));
                }
            }

            $date->addDay();
        }

        return $rows->values();
    }

    private function assignmentActiveOn($assignment, Carbon $date): bool
    {
        $value = $date->toDateString();
        $starts = $assignment?->starts_at?->toDateString();
        $ends = $assignment?->ends_at?->toDateString();

        return ($starts === null || $starts <= $value) && ($ends === null || $ends >= $value);
    }

    private function agendaMatchesSearch(array $agenda, $schedule, ?string $search): bool
    {
        if (! filled($search)) {
            return true;
        }

        $haystack = collect([
            $agenda['title'] ?? '',
            $agenda['reason'] ?? '',
            $schedule->teacherAssignment?->teacher?->name ?? '',
            $schedule->teacherAssignment?->classSubject?->subject?->name ?? '',
            $schedule->teacherAssignment?->classSubject?->classroomTerm?->name ?? '',
        ])->implode(' ');

        return str_contains(strtolower($haystack), strtolower($search));
    }

    /** @return array<string, mixed> */
    private function agendaReportRow($schedule, Carbon $date, array $agenda): array
    {
        $assignment = $schedule->teacherAssignment;
        $classSubject = $assignment->classSubject;
        $resolvedTime = $this->tafsirScheduleGroupingService->resolveSessionTime($schedule, $date->dayOfWeekIso);
        $time = collect([$resolvedTime['starts_at'], $resolvedTime['ends_at']])->filter()->map(fn ($value) => Carbon::parse($value)->format('H:i'))->implode(' - ');

        return [
            'journal' => null,
            'id' => null,
            'date' => $date->toDateString(),
            'date_label' => $date->locale('id')->translatedFormat('d M Y'),
            'session_hour' => (string) ($schedule->classSession?->session_name ?? ''),
            'session_label' => SessionTimetable::label((string) ($schedule->classSession?->session_name ?? '')),
            'session_time' => $time ?: null,
            'classroom_term_id' => $classSubject->classroomTerm?->id,
            'subject_id' => $classSubject->subject?->id,
            'kelas' => $classSubject->classroomTerm?->name ?? '-',
            'mapel' => $classSubject->subject?->name ?? '-',
            'guru_asli_id' => $assignment->teacher_id,
            'guru_asli' => $assignment->teacher?->name ?? '-',
            'pengganti_id' => null,
            'pengganti' => null,
            'guru_mengajar_id' => $assignment->teacher_id,
            'guru_mengajar' => $assignment->teacher?->name ?? '-',
            'type' => 'agenda',
            'type_label' => 'Agenda tanpa KBM',
            'status' => 'AGENDA',
            'is_virtual' => true,
            'material' => $agenda['reason'],
            'jp' => 1,
            'hadir' => 0,
            'sakit' => 0,
            'izin' => 0,
            'alpa' => 0,
            'bolos' => 0,
        ];
    }

    /** @param Collection<int, mixed> $group */
    private function agendaTafsirReportRow(Collection $group, Carbon $date, array $agenda): array
    {
        $first = $group->first();
        $firstRow = $this->agendaReportRow($first, $date, $agenda);
        $firstRow['session_hour'] = SessionTimetable::SESSION_TAFSIR;
        $firstRow['session_label'] = 'Tafsir serentak';
        $firstRow['kelas'] = $group->map(fn ($schedule) => $schedule->teacherAssignment?->classSubject?->classroomTerm?->name)->filter()->unique()->implode(', ');
        $firstRow['mapel'] = 'Tafsir';

        return $firstRow;
    }

    private function sessionTime(DiniyyahClassJournal $journal): ?string
    {
        $startsAt = $journal->session_starts_at;
        $endsAt = $journal->session_ends_at;

        if (! $startsAt && ! $endsAt) {
            return null;
        }

        $format = static function (?string $time): ?string {
            if (! $time) {
                return null;
            }

            try {
                return Carbon::parse($time)->format('H:i');
            } catch (\Throwable) {
                return $time;
            }
        };

        return collect([$format($startsAt), $format($endsAt)])->filter()->implode(' - ') ?: null;
    }

    private function whereEffectiveTeacher(Builder $query, int $teacherId): void
    {
        $query->where(function (Builder $query) use ($teacherId): void {
            $query->where(function (Builder $query) use ($teacherId): void {
                $query->whereNull('substitute_teacher_id')
                    ->whereHas('teacherAssignment', fn (Builder $q) => $q->where('teacher_id', $teacherId));
            })->orWhere('substitute_teacher_id', $teacherId);
        });
    }
}
