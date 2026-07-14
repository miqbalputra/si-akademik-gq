<?php

namespace App\Http\Controllers;

use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\SchoolEvent;
use App\Models\SchoolHoliday;
use App\Models\TahfidzHalaqah;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class GuruDashboardController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasRole('guru'), 403);

        $teacher = $request->user()->teacher;

        // Auto-create assessment sets for assignments that don't have one yet
        if ($teacher) {
            $assignments = \App\Models\DiniyyahTeacherAssignment::with('classSubject.subject')
                ->where('teacher_id', $teacher->id)
                ->where(function ($query) {
                    $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()->toDateString());
                })
                ->where(function ($query) {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString());
                })
                ->get();
                
            $builder = new \App\Services\DiniyyahAssessmentComponentBuilder();
            
            foreach ($assignments as $assignment) {
                $classSubject = $assignment->classSubject;
                if ($classSubject) {
                    $exists = DiniyyahAssessmentSet::where('diniyyah_class_subject_id', $classSubject->id)->exists();
                    if (!$exists) {
                        $newSet = DiniyyahAssessmentSet::create([
                            'diniyyah_class_subject_id' => $classSubject->id,
                            'title' => 'Penilaian ' . $classSubject->subject?->name,
                            'tested_material' => '-',
                            'assessment_method' => $classSubject->assessment_method ?? 'weighted',
                            'kkm' => $classSubject->kkm ?? 70,
                            'daily_weight' => $classSubject->daily_weight ?? 40,
                            'exam_weight' => $classSubject->exam_weight ?? 60,
                            'appears_on_ledger' => $classSubject->appears_on_ledger ?? true,
                            'appears_on_report' => $classSubject->appears_on_report ?? true,
                            'sort_order' => $classSubject->sort_order ?? 10,
                            'status' => 'active',
                            'created_by' => $request->user()->id,
                            'updated_by' => $request->user()->id,
                        ]);
                        $builder->createDefaults($newSet);
                    }
                }
            }
        }

        // 1. Data Wali Kelas
        $homeroomClassroomTerms = ClassroomTerm::query()
            ->with(['classroom', 'academicTerm'])
            ->whereHas('homeroomAssignments', function (Builder $query) use ($teacher): void {
                $query->where('teacher_id', $teacher?->id ?? 0)
                    ->where(function (Builder $query): void {
                        $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()->toDateString());
                    })
                    ->where(function (Builder $query): void {
                        $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString());
                    });
            })
            ->get();

        // 2. Data Guru Diniyyah
        $diniyyahAssessmentSets = DiniyyahAssessmentSet::query()
            ->with(['classSubject.classroomTerm.classroom', 'classSubject.subject'])
            ->whereIn('status', ['active', 'needs_revision'])
            ->whereHas('classSubject.teacherAssignments', function (Builder $query) use ($teacher) {
                $query->where('teacher_id', $teacher?->id ?? 0)
                    ->where(function ($query) {
                        $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()->toDateString());
                    })
                    ->where(function ($query) {
                        $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString());
                    });
            })
            ->latest()
            ->get();

        // 3. Data Guru Tahfidz
        $tahfidzHalaqahs = TahfidzHalaqah::query()
            ->with(['academicTerm.academicYear', 'activeMembers.student'])
            ->where(function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher?->id ?? 0)
                  ->orWhere('assistant_teacher_id', $teacher?->id ?? 0);
            })
            ->latest()
            ->get();
            
        // 3b. Data Assignments Guru Diniyyah (Untuk Jurnal)
        $diniyyahAssignments = DiniyyahTeacherAssignment::query()
            ->where('teacher_id', $teacher?->id ?? 0)
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()->toDateString());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString());
            })
            ->get();

        // 4. Data Agenda & Libur Sekolah
        // We get classroom terms associated with this teacher (all roles) to filter events
        $allTeacherClassroomTerms = $homeroomClassroomTerms->concat(
            $diniyyahAssessmentSets->pluck('classSubject.classroomTerm')->filter()
        )->unique('id')->values();

        $schoolEvents = SchoolEvent::query()
            ->with('targetClassroomTerms.classroom')
            ->visibleToTeachers()
            ->relevantToClassroomTerms($allTeacherClassroomTerms)
            ->overlapping(today(), today()->addDays(21))
            ->orderBy('starts_on')
            ->orderBy('title')
            ->get();

        $schoolHolidays = SchoolHoliday::query()
            ->whereBetween('holiday_date', [today()->toDateString(), today()->addDays(21)->toDateString()])
            ->orderBy('holiday_date')
            ->get();

        $upcomingAlerts = $this->buildUpcomingAlerts($schoolEvents, $schoolHolidays);

        return view('guru.dashboard', compact(
            'teacher',
            'homeroomClassroomTerms',
            'diniyyahAssessmentSets',
            'tahfidzHalaqahs',
            'diniyyahAssignments',
            'upcomingAlerts'
        ));
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

        $holidayAlerts = $holidays
            ->filter(fn (SchoolHoliday $holiday) => $holiday->holiday_date->lessThanOrEqualTo(today()->addDays(7)))
            ->map(function (SchoolHoliday $holiday): array {
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
