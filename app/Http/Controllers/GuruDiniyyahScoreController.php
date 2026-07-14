<?php

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentResult;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\DiniyyahScore;
use App\Models\SchoolEvent;
use App\Models\SchoolHoliday;
use App\Services\DiniyyahAssessmentWorkflow;
use App\Services\DiniyyahInputProgressService;
use App\Services\DiniyyahScoreCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Collection;

class GuruDiniyyahScoreController extends Controller
{
    public function index(Request $request, DiniyyahInputProgressService $progressService): View
    {
        $teacher = $request->user()->teacher;

        // Auto-create assessment sets for assignments that don't have one yet
        if ($teacher && ! $request->user()->hasAnyRole(['admin', 'kabag_diniyyah'])) {
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

        $assessmentSets = DiniyyahAssessmentSet::query()
            ->with([
                'classSubject.classroomTerm' => function ($query) {
                    $query->withCount(['enrollments as active_enrollments_count' => function ($q) {
                        $q->where('status', 'active');
                    }]);
                },
                'classSubject.subject',
                'components',
                'results'
            ])
            ->when(! $request->user()->hasAnyRole(['admin', 'kabag_diniyyah']), function ($query) use ($request) {
                $teacher = $request->user()->teacher;

                $query->whereHas('classSubject.teacherAssignments', function ($query) use ($teacher) {
                    $query->where('teacher_id', $teacher?->id ?? 0)
                        ->where(function ($query) {
                            $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()->toDateString());
                        })
                        ->where(function ($query) {
                            $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString());
                        });
                });
            })
            ->latest()
            ->get();

        $summaries = $assessmentSets->mapWithKeys(fn (DiniyyahAssessmentSet $assessmentSet) => [
            $assessmentSet->id => $progressService->summary($assessmentSet),
        ]);

        $termIds = $assessmentSets
            ->pluck('classSubject.classroomTerm.academic_term_id')
            ->filter()
            ->unique()
            ->values();
        $classroomTermIds = $assessmentSets
            ->pluck('classSubject.classroom_term_id')
            ->filter()
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
            ->visibleToTeachers()
            ->relevantToClassroomTerms($classroomTerms)
            ->overlapping(today(), today()->addDays(21))
            ->orderBy('starts_on')
            ->orderBy('title')
            ->get();

        $schoolHolidays = SchoolHoliday::query()
            ->when($termIds->isNotEmpty(), fn ($query) => $query->whereIn('academic_term_id', $termIds))
            ->when($termIds->isEmpty(), fn ($query) => $query->whereRaw('1 = 0'))
            ->whereBetween('holiday_date', [today()->toDateString(), today()->addDays(7)->toDateString()])
            ->orderBy('holiday_date')
            ->get();

        $upcomingAlerts = $this->buildUpcomingAlerts($schoolEvents, $schoolHolidays);

        return view('guru.diniyyah-scores.index', compact('assessmentSets', 'summaries', 'schoolEvents', 'upcomingAlerts'));
    }

    public function edit(Request $request, DiniyyahAssessmentSet $assessmentSet): View
    {
        Gate::authorize('inputScores', $assessmentSet);

        $assessmentSet->load(['classSubject.classroomTerm', 'classSubject.subject', 'components' => fn ($query) => $query->orderBy('sort_order')]);

        $enrollments = ClassEnrollment::query()
            ->with('student')
            ->where('classroom_term_id', $assessmentSet->classSubject->classroom_term_id)
            ->where('status', 'active')
            ->orderBy('roll_number')
            ->orderBy('student_id')
            ->get();

        $scores = DiniyyahScore::query()
            ->where('diniyyah_assessment_set_id', $assessmentSet->id)
            ->whereIn('class_enrollment_id', $enrollments->pluck('id'))
            ->get()
            ->keyBy(fn (DiniyyahScore $score) => $score->class_enrollment_id.'-'.$score->diniyyah_score_component_id);

        $results = DiniyyahAssessmentResult::query()
            ->where('diniyyah_assessment_set_id', $assessmentSet->id)
            ->whereIn('class_enrollment_id', $enrollments->pluck('id'))
            ->get()
            ->keyBy('class_enrollment_id');

        $totalCells = $enrollments->count() * $assessmentSet->components->count();
        $filledCells = $scores->filter(fn (DiniyyahScore $score): bool => $score->score !== null)->count();
        $completeStudents = $results->where('is_complete', true)->count();
        $completionPercentage = $totalCells > 0 ? round($filledCells / $totalCells * 100, 2) : 0.0;

        return view('guru.diniyyah-scores.edit', compact(
            'assessmentSet',
            'enrollments',
            'scores',
            'results',
            'totalCells',
            'filledCells',
            'completeStudents',
            'completionPercentage',
        ));
    }

    public function update(Request $request, DiniyyahAssessmentSet $assessmentSet, DiniyyahScoreCalculator $calculator): RedirectResponse
    {
        Gate::authorize('inputScores', $assessmentSet);
        abort_if(
            ! $request->user()->hasAnyRole(['admin', 'kabag_diniyyah'])
            && ! in_array($assessmentSet->status, ['active', 'needs_revision'], true),
            403,
        );

        // Cegah mengubah nilai setelah leger kelas dikunci/dipublikasikan —
        // nilai sumber sudah dibekukan ke snapshot leger, mengubahnya akan
        // mendesinkronisasi rapor yang sudah terbit.
        $ledgerFrozen = DiniyyahLedgerSnapshot::query()
            ->where('classroom_term_id', $assessmentSet->classSubject->classroom_term_id)
            ->whereIn('status', ['locked', 'published'])
            ->exists();
        abort_if($ledgerFrozen, 403, 'Nilai tidak dapat diubah karena leger kelas sudah dikunci atau dipublikasikan.');

        // Catat apakah set yang akan diedit sudah submitted/validated — admin
        // masih diperbolehkan mengeditnya, tetapi setelah edit set harus kembali
        // ke needs_revision agar status set konsisten dengan isi skor (draft).
        $wasSubmitted = in_array($assessmentSet->status, ['submitted', 'validated'], true);

        $validated = $request->validate([
            'scores' => ['array'],
            'scores.*' => ['array'],
            'scores.*.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $assessmentSet->load('components');
        $componentIds = $assessmentSet->components->pluck('id')->all();
        $enrollmentIds = ClassEnrollment::query()
            ->where('classroom_term_id', $assessmentSet->classSubject->classroom_term_id)
            ->where('status', 'active')
            ->pluck('id')
            ->all();

        foreach ($validated['scores'] ?? [] as $enrollmentId => $componentScores) {
            if (! in_array((int) $enrollmentId, $enrollmentIds, true)) {
                continue;
            }

            foreach ($componentScores as $componentId => $score) {
                if (! in_array((int) $componentId, $componentIds, true)) {
                    continue;
                }

                DiniyyahScore::updateOrCreate(
                    [
                        'diniyyah_score_component_id' => $componentId,
                        'class_enrollment_id' => $enrollmentId,
                    ],
                    [
                        'diniyyah_assessment_set_id' => $assessmentSet->id,
                        'score' => $score,
                        'input_by' => $request->user()->id,
                        'input_at' => now(),
                        'status' => 'draft',
                    ],
                );
            }

            $calculator->calculate($assessmentSet, ClassEnrollment::findOrFail($enrollmentId));
        }

        // Bila admin/kabag mengedit set yang sudah submitted/validated, kembalikan
        // set ke needs_revision agar set tidak ditinggalkan dalam status
        // "submitted"/"validated" berisi skor draft (inkonsisten). Set perlu
        // ditinjau & disubmit ulang sebelum leger dibekukan.
        if ($wasSubmitted && $assessmentSet->status !== 'needs_revision') {
            $assessmentSet->update(['status' => 'needs_revision']);
        }

        return redirect()
            ->route('guru.diniyyah-scores.edit', $assessmentSet)
            ->with('status', 'Nilai berhasil disimpan dan dihitung ulang.');
    }

    public function submit(Request $request, DiniyyahAssessmentSet $assessmentSet, DiniyyahInputProgressService $progressService, DiniyyahAssessmentWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('inputScores', $assessmentSet);
        abort_unless(in_array($assessmentSet->status, ['active', 'needs_revision'], true), 403);

        $summary = $progressService->summary($assessmentSet);

        if ($summary['total_students'] === 0 || $summary['incomplete_students'] > 0) {
            return redirect()
                ->route('guru.diniyyah-scores.edit', $assessmentSet)
                ->withErrors(['scores' => 'Nilai belum lengkap, belum bisa disubmit ke Kabag Diniyyah.']);
        }

        $workflow->submit($assessmentSet);

        return redirect()
            ->route('guru.diniyyah-scores.index')
            ->with('status', 'Nilai berhasil disubmit ke Kabag Diniyyah.');
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
