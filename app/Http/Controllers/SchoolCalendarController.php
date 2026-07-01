<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\ClassEnrollment;
use App\Models\ClassroomTerm;
use App\Models\SchoolEvent;
use App\Models\SchoolHoliday;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SchoolCalendarController extends Controller
{
    public function guru(Request $request): View
    {
        abort_unless($request->user()->hasRole('guru'), 403);

        $teacher = $request->user()->teacher;
        $teacherClassroomTerms = ClassroomTerm::query()
            ->with('classroom')
            ->where(function (Builder $query) use ($teacher): void {
                $query->whereHas('homeroomAssignments', function (Builder $query) use ($teacher): void {
                    $query->where('teacher_id', $teacher?->id ?? 0)
                        ->where(function (Builder $query): void {
                            $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()->toDateString());
                        })
                        ->where(function (Builder $query): void {
                            $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString());
                        });
                })->orWhereHas('diniyyahClassSubjects.teacherAssignments', function (Builder $query) use ($teacher): void {
                    $query->where('teacher_id', $teacher?->id ?? 0)
                        ->where(function (Builder $query): void {
                            $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()->toDateString());
                        })
                        ->where(function (Builder $query): void {
                            $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString());
                        });
                });
            })
            ->get()
            ->unique('id')
            ->values();

        $termIds = AcademicTerm::query()
            ->orderByDesc('starts_at')
            ->get();

        return $this->renderCalendar(
            viewName: 'calendar.guru',
            request: $request,
            terms: $termIds,
            relevantClassroomTerms: $teacherClassroomTerms,
            eventVisibilityColumn: 'show_to_teachers',
            title: 'Kalender Guru',
            subtitle: 'Lihat libur sekolah dan event yang dibagikan admin untuk guru.',
            backUrl: route('guru.diniyyah-scores.index'),
        );
    }

    public function guardian(Request $request): View
    {
        abort_unless($request->user()->hasRole('wali_santri'), 403);

        $guardian = $request->user()->guardian;
        $studentIds = $guardian?->students()->wherePivot('can_login', true)->pluck('students.id') ?? collect();
        $classroomTermIds = ClassEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', 'active')
            ->pluck('classroom_term_id')
            ->unique();
        $classroomTerms = ClassroomTerm::query()
            ->with('classroom')
            ->whereIn('id', $classroomTermIds)
            ->get()
            ->unique('id')
            ->values();

        $terms = AcademicTerm::query()
            ->orderByDesc('starts_at')
            ->get();

        return $this->renderCalendar(
            viewName: 'calendar.guardian',
            request: $request,
            terms: $terms,
            relevantClassroomTerms: $classroomTerms,
            eventVisibilityColumn: 'show_to_guardians',
            title: 'Kalender Wali Santri',
            subtitle: 'Agenda sekolah dan libur yang dibagikan admin untuk wali santri.',
            backUrl: route('wali.dashboard'),
        );
    }

    /**
     * @param  Collection<int, AcademicTerm>  $terms
     * @param  Collection<int, ClassroomTerm>  $relevantClassroomTerms
     */
    private function renderCalendar(string $viewName, Request $request, Collection $terms, Collection $relevantClassroomTerms, string $eventVisibilityColumn, string $title, string $subtitle, string $backUrl): View
    {
        $terms = $terms->load('academicYear.school')->values();
        $selectedTerm = $terms->firstWhere('id', (int) $request->query('term'))
            ?? $terms->firstWhere('is_active', true)
            ?? $terms->first();

        $selectedMonth = (string) $request->query('month', '');
        $selectedCategory = $this->normalizeCategory((string) $request->query('category', 'all'));

        if (! $selectedTerm) {
            return view($viewName, [
                'title' => $title,
                'subtitle' => $subtitle,
                'backUrl' => $backUrl,
                'termOptions' => [],
                'categoryOptions' => $this->categoryOptions(),
                'selectedAcademicTermId' => null,
                'selectedMonth' => now()->format('Y-m'),
                'selectedMonthLabel' => now()->locale('id')->translatedFormat('F Y'),
                'selectedTermLabel' => 'Belum ada periode ajaran',
                'selectedCategory' => $selectedCategory,
                'calendarWeeks' => [],
                'holidayList' => [],
                'eventList' => [],
            ]);
        }

        $monthStart = $this->resolveMonthStart($selectedMonth, $selectedTerm);
        $monthEnd = $monthStart->endOfMonth();

        $holidays = collect();

        if (in_array($selectedCategory, ['all', 'holiday'], true)) {
            $holidays = SchoolHoliday::query()
                ->where('academic_term_id', $selectedTerm->id)
                ->whereBetween('holiday_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->orderBy('holiday_date')
                ->get()
                ->keyBy(fn (SchoolHoliday $holiday) => $holiday->holiday_date->toDateString());
        }

        $events = SchoolEvent::query()
            ->with('targetClassroomTerms.classroom')
            ->where('academic_term_id', $selectedTerm->id)
            ->where($eventVisibilityColumn, true)
            ->when($selectedCategory !== 'all' && $selectedCategory !== 'holiday', fn ($query) => $query->where('event_type', $selectedCategory))
            ->relevantToClassroomTerms($relevantClassroomTerms)
            ->overlapping($monthStart, $monthEnd)
            ->orderBy('starts_on')
            ->orderBy('title')
            ->get();

        return view($viewName, [
            'title' => $title,
            'subtitle' => $subtitle,
            'backUrl' => $backUrl,
            'termOptions' => $terms->map(fn (AcademicTerm $term) => [
                'id' => $term->id,
                'label' => trim(sprintf('%s - %s', $term->academicYear?->name ?? '-', $term->name)),
            ])->all(),
            'categoryOptions' => $this->categoryOptions(),
            'selectedAcademicTermId' => $selectedTerm->id,
            'selectedMonth' => $monthStart->format('Y-m'),
            'selectedMonthLabel' => $monthStart->locale('id')->translatedFormat('F Y'),
            'selectedTermLabel' => trim(sprintf('%s - %s', $selectedTerm->academicYear?->name ?? '-', $selectedTerm->name)),
            'selectedCategory' => $selectedCategory,
            'calendarWeeks' => $this->buildCalendarWeeks($monthStart, $holidays, $this->eventsByDate($events, $monthStart, $monthEnd)),
            'holidayList' => $holidays->values()->map(fn (SchoolHoliday $holiday) => [
                'date_label' => $holiday->holiday_date->locale('id')->translatedFormat('l, d F Y'),
                'title' => $holiday->title,
                'description' => $holiday->description,
            ])->all(),
            'eventList' => $events->map(fn (SchoolEvent $event) => [
                'date_label' => $this->eventDateLabel($event),
                'title' => $event->title,
                'type_label' => $event->typeLabel(),
                'location' => $event->location,
                'description' => $event->description,
                'target_label' => $event->targetSummary(),
            ])->all(),
        ]);
    }

    /** @return array<int, array{value: string, label: string}> */
    private function categoryOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'Semua'],
            ['value' => 'holiday', 'label' => 'Libur Sekolah'],
            ['value' => 'exam', 'label' => 'Ujian'],
            ['value' => 'outdoor', 'label' => 'Outdoor'],
            ['value' => 'religious', 'label' => 'Agenda Diniyyah'],
            ['value' => 'meeting', 'label' => 'Pertemuan'],
            ['value' => 'general', 'label' => 'Agenda Sekolah'],
        ];
    }

    private function normalizeCategory(string $category): string
    {
        $allowed = collect($this->categoryOptions())->pluck('value')->all();

        return in_array($category, $allowed, true) ? $category : 'all';
    }

    private function resolveMonthStart(string $requestedMonth, AcademicTerm $term): CarbonImmutable
    {
        try {
            if ($requestedMonth !== '') {
                $monthStart = CarbonImmutable::parse($requestedMonth.'-01')->startOfMonth();

                if ($term->starts_at && $monthStart->lessThan(CarbonImmutable::parse($term->starts_at)->startOfMonth())) {
                    return CarbonImmutable::parse($term->starts_at)->startOfMonth();
                }

                if ($term->ends_at && $monthStart->greaterThan(CarbonImmutable::parse($term->ends_at)->startOfMonth())) {
                    return CarbonImmutable::parse($term->ends_at)->startOfMonth();
                }

                return $monthStart;
            }
        } catch (\Throwable) {
            // fallback below
        }

        return $term->starts_at
            ? CarbonImmutable::parse($term->starts_at)->startOfMonth()
            : now()->startOfMonth();
    }

    /**
     * @param  Collection<string, SchoolHoliday>  $holidays
     * @param  Collection<string, Collection<int, SchoolEvent>>  $eventsByDate
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function buildCalendarWeeks(CarbonImmutable $monthStart, Collection $holidays, Collection $eventsByDate): array
    {
        $firstGridDay = $monthStart->startOfMonth()->startOfWeek(CarbonImmutable::MONDAY);
        $lastGridDay = $monthStart->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);
        $days = [];

        for ($cursor = $firstGridDay; $cursor->lessThanOrEqualTo($lastGridDay); $cursor = $cursor->addDay()) {
            $holiday = $holidays->get($cursor->toDateString());
            $events = $eventsByDate->get($cursor->toDateString(), collect());

            $days[] = [
                'day_number' => $cursor->format('d'),
                'day_name' => $cursor->locale('id')->translatedFormat('l'),
                'is_current_month' => $cursor->month === $monthStart->month,
                'is_weekend' => $cursor->isSaturday() || $cursor->isSunday(),
                'holiday' => $holiday ? [
                    'title' => $holiday->title,
                    'description' => $holiday->description,
                ] : null,
                'events' => $events->map(fn (SchoolEvent $event) => [
                    'title' => $event->title,
                    'type_label' => $event->typeLabel(),
                    'location' => $event->location,
                    'target_label' => $event->targetSummary(),
                ])->all(),
            ];
        }

        return array_chunk($days, 7);
    }

    /**
     * @param  Collection<int, SchoolEvent>  $events
     * @return Collection<string, Collection<int, SchoolEvent>>
     */
    private function eventsByDate(Collection $events, CarbonImmutable $monthStart, CarbonImmutable $monthEnd): Collection
    {
        $byDate = collect();

        foreach ($events as $event) {
            $eventStart = CarbonImmutable::parse($event->starts_on)->max($monthStart);
            $eventEnd = CarbonImmutable::parse($event->ends_on)->min($monthEnd);

            for ($cursor = $eventStart; $cursor->lessThanOrEqualTo($eventEnd); $cursor = $cursor->addDay()) {
                $dateKey = $cursor->toDateString();
                $byDate[$dateKey] = ($byDate[$dateKey] ?? collect())->push($event);
            }
        }

        return $byDate;
    }

    private function eventDateLabel(SchoolEvent $event): string
    {
        return $event->starts_on->equalTo($event->ends_on)
            ? $event->starts_on->locale('id')->translatedFormat('l, d F Y')
            : $event->starts_on->locale('id')->translatedFormat('l, d F Y').' s.d. '.$event->ends_on->locale('id')->translatedFormat('l, d F Y');
    }
}
