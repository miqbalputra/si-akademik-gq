<?php

namespace App\Filament\Pages;

use App\Filament\Resources\SchoolHolidays\SchoolHolidayResource;
use App\Models\AcademicTerm;
use App\Models\SchoolEvent;
use App\Models\SchoolHoliday;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use UnitEnum;

class AcademicCalendar extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Data Sekolah';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Kalender Akademik';

    protected static ?int $navigationSort = 34;

    protected string $view = 'filament.pages.academic-calendar';

    /** @var array<int, array{id: int, label: string}> */
    public array $termOptions = [];

    /** @var array<int, array<string, mixed>> */
    public array $calendarWeeks = [];

    /** @var array<int, array<string, mixed>> */
    public array $holidayList = [];

    /** @var array<int, array<string, mixed>> */
    public array $eventList = [];

    public ?int $selectedAcademicTermId = null;
    public ?int $selectedSchoolId = null;

    public string $selectedMonth = '';

    public string $selectedMonthLabel = '';

    public string $selectedTermLabel = '';

    public bool $canManageHolidays = false;

    public ?string $createHolidayUrl = null;
    public ?string $createEventUrl = null;

    public function mount(): void
    {
        $this->canManageHolidays = auth()->user()?->hasRole('admin') ?? false;

        $terms = AcademicTerm::query()
            ->with('academicYear.school')
            ->orderByDesc('starts_at')
            ->get();

        $this->termOptions = $terms
            ->map(fn (AcademicTerm $term) => [
                'id' => $term->id,
                'label' => $this->termLabel($term),
            ])
            ->values()
            ->all();

        $selectedTerm = $terms->firstWhere('id', (int) request()->query('term'))
            ?? $terms->firstWhere('is_active', true)
            ?? $terms->first();

        if (! $selectedTerm) {
            $this->selectedMonth = now()->format('Y-m');
            $this->selectedMonthLabel = now()->locale('id')->translatedFormat('F Y');
            $this->selectedTermLabel = 'Belum ada periode ajaran';
            $this->calendarWeeks = [];
            $this->holidayList = [];
            $this->eventList = [];

            return;
        }

        $this->selectedAcademicTermId = $selectedTerm->id;
        $this->selectedSchoolId = $selectedTerm->academicYear?->school_id;

        $requestedMonth = (string) request()->query('month', '');
        $monthStart = $this->resolveMonthStart($requestedMonth, $selectedTerm);

        $this->selectedMonth = $monthStart->format('Y-m');
        $this->selectedMonthLabel = $monthStart->locale('id')->translatedFormat('F Y');
        $this->selectedTermLabel = $this->termLabel($selectedTerm);

        $holidays = SchoolHoliday::query()
            ->where('academic_term_id', $selectedTerm->id)
            ->whereBetween('holiday_date', [$monthStart->startOfMonth()->toDateString(), $monthStart->endOfMonth()->toDateString()])
            ->orderBy('holiday_date')
            ->get()
            ->keyBy(fn (SchoolHoliday $holiday) => $holiday->holiday_date->toDateString());

        $this->holidayList = $holidays
            ->values()
            ->map(fn (SchoolHoliday $holiday) => [
                'date_label' => $holiday->holiday_date->locale('id')->translatedFormat('l, d F Y'),
                'title' => $holiday->title,
                'description' => $holiday->description,
                'edit_url' => SchoolHolidayResource::getUrl('edit', ['record' => $holiday]),
            ])
            ->all();

        $events = SchoolEvent::query()
            ->with('targetClassroomTerms.classroom')
            ->where('academic_term_id', $selectedTerm->id)
            ->overlapping($monthStart->startOfMonth(), $monthStart->endOfMonth())
            ->orderBy('starts_on')
            ->orderBy('title')
            ->get();

        $this->eventList = $events
            ->map(fn (SchoolEvent $event) => [
                'date_label' => $this->eventDateLabel($event),
                'title' => $event->title,
                'type_label' => $event->typeLabel(),
                'location' => $event->location,
                'description' => $event->description,
                'target_label' => $event->targetSummary(),
                'edit_url' => \App\Filament\Resources\SchoolEvents\SchoolEventResource::getUrl('edit', ['record' => $event]),
            ])
            ->all();

        $eventsByDate = $this->eventsByDate($events, $monthStart->startOfMonth(), $monthStart->endOfMonth());

        $this->calendarWeeks = $this->buildCalendarWeeks($monthStart, $holidays, $eventsByDate);
        $this->createHolidayUrl = $this->canManageHolidays
            ? SchoolHolidayResource::getUrl('create', [
                'school_id' => $this->selectedSchoolId,
                'academic_term_id' => $selectedTerm->id,
                'holiday_date' => $monthStart->toDateString(),
            ])
            : null;
        $this->createEventUrl = $this->canManageHolidays
            ? \App\Filament\Resources\SchoolEvents\SchoolEventResource::getUrl('create', [
                'school_id' => $this->selectedSchoolId,
                'academic_term_id' => $selectedTerm->id,
                'date' => $monthStart->toDateString(),
            ])
            : null;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']) ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Kalender Akademik';
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

    private function termLabel(AcademicTerm $term): string
    {
        return trim(sprintf(
            '%s - %s',
            $term->academicYear?->name ?? '-',
            $term->name
        ));
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
            $isWeekend = $cursor->isSaturday() || $cursor->isSunday();
            $isCurrentMonth = $cursor->month === $monthStart->month;
            $addHolidayUrl = null;
            $addEventUrl = null;

            if ($this->canManageHolidays && ! $holiday) {
                $addHolidayUrl = SchoolHolidayResource::getUrl('create', [
                    'school_id' => $this->selectedSchoolId,
                    'academic_term_id' => $this->selectedAcademicTermId,
                    'holiday_date' => $cursor->toDateString(),
                ]);
            }

            if ($this->canManageHolidays) {
                $addEventUrl = \App\Filament\Resources\SchoolEvents\SchoolEventResource::getUrl('create', [
                    'school_id' => $this->selectedSchoolId,
                    'academic_term_id' => $this->selectedAcademicTermId,
                    'date' => $cursor->toDateString(),
                ]);
            }

            $days[] = [
                'date' => $cursor->toDateString(),
                'day_number' => $cursor->format('d'),
                'day_name' => $cursor->locale('id')->translatedFormat('l'),
                'is_current_month' => $isCurrentMonth,
                'is_weekend' => $isWeekend,
                'is_school_holiday' => (bool) $holiday,
                'events' => $events->map(fn (SchoolEvent $event) => [
                    'title' => $event->title,
                    'type_label' => $event->typeLabel(),
                    'location' => $event->location,
                    'target_label' => $event->targetSummary(),
                    'edit_url' => \App\Filament\Resources\SchoolEvents\SchoolEventResource::getUrl('edit', ['record' => $event]),
                ])->all(),
                'title' => $holiday?->title ?? ($isWeekend ? 'Libur akhir pekan' : 'Hari sekolah'),
                'description' => $holiday?->description,
                'edit_url' => $holiday ? SchoolHolidayResource::getUrl('edit', ['record' => $holiday]) : null,
                'add_url' => $addHolidayUrl,
                'add_event_url' => $addEventUrl,
            ];
        }

        return array_chunk($days, 7);
    }

    /** @param Collection<int, SchoolEvent> $events
     *  @return Collection<string, Collection<int, SchoolEvent>>
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
