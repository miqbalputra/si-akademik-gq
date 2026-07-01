<?php

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
use App\Models\ClassroomTerm;
use App\Models\ReportCard;
use App\Models\SchoolEvent;
use App\Models\SchoolHoliday;
use App\Models\SchoolEventResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class GuardianDashboardController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasRole('wali_santri'), 403);

        $guardian = $request->user()->guardian;
        $students = $guardian?->students()
            ->wherePivot('can_login', true)
            ->orderBy('name')
            ->get() ?? collect();

        $reportCards = ReportCard::query()
            ->with(['student', 'academicTerm.academicYear', 'classroomTerm'])
            ->whereIn('student_id', $students->pluck('id'))
            ->where('status', 'published')
            ->latest('published_at')
            ->get();

        $reportCardsByStudent = $reportCards->groupBy('student_id');
        $latestReportCards = $reportCardsByStudent
            ->map(fn ($cards) => $cards->sortByDesc('published_at')->first())
            ->filter()
            ->values();

        $termIds = ClassEnrollment::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->where('status', 'active')
            ->pluck('academic_term_id')
            ->unique()
            ->values();
        $classroomTermIds = ClassEnrollment::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->where('status', 'active')
            ->pluck('classroom_term_id')
            ->unique();
        $classroomTerms = ClassroomTerm::query()
            ->with('classroom')
            ->whereIn('id', $classroomTermIds)
            ->get()
            ->unique('id')
            ->values();

        $schoolEvents = SchoolEvent::query()
            ->with('targetClassroomTerms.classroom')
            ->when($termIds->isNotEmpty(), fn ($query) => $query->whereIn('academic_term_id', $termIds))
            ->when($termIds->isEmpty(), fn ($query) => $query->whereRaw('1 = 0'))
            ->visibleToGuardians()
            ->relevantToClassroomTerms($classroomTerms)
            ->overlapping(today(), today()->addDays(21))
            ->orderBy('starts_on')
            ->orderBy('title')
            ->get();
        $guardianEventResponses = $guardian
            ? SchoolEventResponse::query()
                ->where('guardian_id', $guardian->id)
                ->whereIn('school_event_id', $schoolEvents->pluck('id'))
                ->get()
                ->keyBy('school_event_id')
            : collect();

        $schoolHolidays = SchoolHoliday::query()
            ->when($termIds->isNotEmpty(), fn ($query) => $query->whereIn('academic_term_id', $termIds))
            ->when($termIds->isEmpty(), fn ($query) => $query->whereRaw('1 = 0'))
            ->whereBetween('holiday_date', [today()->toDateString(), today()->addDays(7)->toDateString()])
            ->orderBy('holiday_date')
            ->get();

        $upcomingAlerts = $this->buildUpcomingAlerts($schoolEvents, $schoolHolidays);

        return view('wali.dashboard', [
            'guardian' => $guardian,
            'students' => $students,
            'reportCards' => $reportCards,
            'reportCardsByStudent' => $reportCardsByStudent,
            'latestReportCards' => $latestReportCards,
            'schoolEvents' => $schoolEvents,
            'guardianEventResponses' => $guardianEventResponses,
            'upcomingAlerts' => $upcomingAlerts,
        ]);
    }

    /** @param Collection<int, SchoolEvent> $events
     *  @param Collection<int, SchoolHoliday> $holidays
     *  @return Collection<int, array<string, mixed>>
     */
    private function buildUpcomingAlerts(Collection $events, Collection $holidays): Collection
    {
        $eventAlerts = $events
            ->filter(fn (SchoolEvent $event) => $event->starts_on->lessThanOrEqualTo(today()->addDays(7)))
            ->map(function (SchoolEvent $event): array {
                return [
                    'kind' => 'event',
                    'kind_label' => $event->typeLabel(),
                    'priority_key' => $event->priorityKey(),
                    'priority_label' => $event->priorityLabel(),
                    'title' => $event->title,
                    'date_label' => $event->starts_on->equalTo($event->ends_on)
                        ? $event->starts_on->locale('id')->translatedFormat('l, d F Y')
                        : $event->starts_on->locale('id')->translatedFormat('l, d F Y').' s.d. '.$event->ends_on->locale('id')->translatedFormat('l, d F Y'),
                    'meta' => collect([$event->location, 'Target: '.$event->targetSummary()])->filter()->implode(' · '),
                    'description' => $event->description,
                    'countdown_label' => $this->countdownLabel($event->starts_on),
                    'sort_date' => $event->starts_on->toDateString(),
                ];
            });

        $holidayAlerts = $holidays->map(function (SchoolHoliday $holiday): array {
            return [
                'kind' => 'holiday',
                'kind_label' => 'Libur Sekolah',
                'priority_key' => 'medium',
                'priority_label' => 'Perlu Perhatian',
                'title' => $holiday->title,
                'date_label' => $holiday->holiday_date->locale('id')->translatedFormat('l, d F Y'),
                'meta' => null,
                'description' => $holiday->description,
                'countdown_label' => $this->countdownLabel($holiday->holiday_date),
                'sort_date' => $holiday->holiday_date->toDateString(),
            ];
        });

        return $eventAlerts
            ->concat($holidayAlerts)
            ->sortBy('sort_date')
            ->values()
            ->take(5);
    }

    private function countdownLabel(\Carbon\CarbonInterface $date): string
    {
        $days = today()->diffInDays($date, false);

        return match (true) {
            $days < 0 => 'Sudah lewat',
            $days === 0 => 'Hari ini',
            $days === 1 => 'Besok',
            default => $days.' hari lagi',
        };
    }
}
