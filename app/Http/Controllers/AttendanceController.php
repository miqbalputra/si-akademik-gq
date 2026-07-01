<?php

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
use App\Models\ClassroomTerm;
use App\Models\HomeroomAssignment;
use App\Models\SchoolEvent;
use App\Models\SchoolHoliday;
use App\Models\StudentAttendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $classroomTerms = ClassroomTerm::query()
            ->with(['academicTerm.academicYear', 'homeroomAssignments.teacher'])
            ->withCount(['enrollments'])
            ->when(! $this->canViewAllClasses($request->user()), function ($query) use ($request) {
                $teacher = $request->user()->teacher;

                $query->whereHas('homeroomAssignments', function ($query) use ($teacher) {
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

        $termIds = $classroomTerms->pluck('academic_term_id')->unique()->values();
        $schoolEvents = SchoolEvent::query()
            ->when($termIds->isNotEmpty(), fn ($query) => $query->whereIn('academic_term_id', $termIds))
            ->when($termIds->isEmpty(), fn ($query) => $query->whereRaw('1 = 0'))
            ->visibleToTeachers()
            ->overlapping(today(), today()->addDays(21))
            ->orderBy('starts_on')
            ->orderBy('title')
            ->get();

        return view('attendance.index', [
            'classroomTerms' => $classroomTerms,
            'selectedMonth' => $request->query('month', now()->format('Y-m')),
            'schoolEvents' => $schoolEvents,
        ]);
    }

    public function edit(Request $request, ClassroomTerm $classroomTerm): View
    {
        abort_unless($this->canViewClass($request->user(), $classroomTerm), 403);

        $classroomTerm->load(['academicTerm.academicYear.school', 'homeroomAssignments.teacher']);

        $monthStart = $this->selectedMonth($request, $classroomTerm);
        [$startDate, $endDate] = $this->dateRangeForMonth($classroomTerm, $monthStart);
        $schoolHolidays = $this->schoolHolidaysForRange($classroomTerm, $startDate, $endDate);
        $days = collect(CarbonPeriod::create($startDate, $endDate))
            ->map(fn ($date) => CarbonImmutable::parse($date));
        $days = $this->schoolAttendanceDays($days, $schoolHolidays);

        $enrollments = ClassEnrollment::query()
            ->with('student')
            ->where('classroom_term_id', $classroomTerm->id)
            ->where('status', 'active')
            ->orderBy('roll_number')
            ->orderBy('student_id')
            ->get();

        $attendances = StudentAttendance::query()
            ->whereIn('class_enrollment_id', $enrollments->pluck('id'))
            ->whereBetween('attendance_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy(fn (StudentAttendance $attendance) => $attendance->class_enrollment_id.'-'.$attendance->attendance_date->toDateString());

        [$studentTotals, $classTotals] = $this->totalsFor($enrollments, $days, $attendances);

        return view('attendance.edit', [
            'classroomTerm' => $classroomTerm,
            'enrollments' => $enrollments,
            'attendances' => $attendances,
            'studentTotals' => $studentTotals,
            'classTotals' => $classTotals,
            'days' => $days,
            'schoolHolidays' => $schoolHolidays,
            'selectedMonth' => $monthStart->format('Y-m'),
            'selectedMonthLabel' => $monthStart->locale('id')->translatedFormat('F Y'),
            'availableMonths' => $this->availableMonths($classroomTerm),
            'canUpdate' => $this->canUpdateClass($request->user(), $classroomTerm),
        ]);
    }

    public function update(Request $request, ClassroomTerm $classroomTerm): RedirectResponse
    {
        abort_unless($this->canUpdateClass($request->user(), $classroomTerm), 403);

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'attendance' => ['array'],
            'attendance.*' => ['array'],
            'attendance.*.*' => ['required', 'string', Rule::in(StudentAttendance::acceptedCodes())],
        ]);

        $monthStart = CarbonImmutable::createFromFormat('Y-m-d', $validated['month'].'-01')->startOfMonth();
        [$startDate, $endDate] = $this->dateRangeForMonth($classroomTerm->loadMissing('academicTerm'), $monthStart);
        $schoolHolidays = $this->schoolHolidaysForRange($classroomTerm, $startDate, $endDate);
        $days = collect(CarbonPeriod::create($startDate, $endDate))
            ->map(fn ($date) => CarbonImmutable::parse($date));
        $days = $this->schoolAttendanceDays($days, $schoolHolidays);

        $enrollments = ClassEnrollment::query()
            ->where('classroom_term_id', $classroomTerm->id)
            ->where('status', 'active')
            ->get()
            ->keyBy('id');

        $existingAttendances = StudentAttendance::query()
            ->whereIn('class_enrollment_id', $enrollments->keys())
            ->whereBetween('attendance_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy(fn (StudentAttendance $attendance) => $attendance->class_enrollment_id.'-'.$attendance->attendance_date->toDateString());

        DB::transaction(function () use ($request, $validated, $classroomTerm, $enrollments, $days, $existingAttendances) {
            foreach ($enrollments as $enrollment) {
                foreach ($days as $day) {
                    $date = $day->toDateString();
                    $key = $enrollment->id.'-'.$date;
                    $code = $validated['attendance'][$enrollment->id][$date]
                        ?? StudentAttendance::codeFromStatus($existingAttendances->get($key)?->status);

                    StudentAttendance::updateOrCreate(
                        [
                            'class_enrollment_id' => $enrollment->id,
                            'attendance_date' => $date,
                        ],
                        [
                            'academic_term_id' => $classroomTerm->academic_term_id,
                            'classroom_term_id' => $classroomTerm->id,
                            'student_id' => $enrollment->student_id,
                            'status' => StudentAttendance::statusFromCode($code),
                            'input_by' => $request->user()->id,
                        ],
                    );
                }
            }
        });

        return redirect()
            ->route('attendance.edit', ['classroomTerm' => $classroomTerm, 'month' => $validated['month']])
            ->with('status', 'Presensi berhasil disimpan.');
    }

    public function updateSingle(Request $request, ClassroomTerm $classroomTerm): JsonResponse
    {
        abort_unless($this->canUpdateClass($request->user(), $classroomTerm), 403);

        $validated = $request->validate([
            'class_enrollment_id' => ['required', 'exists:class_enrollments,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'code' => ['required', 'string', Rule::in(StudentAttendance::acceptedCodes())],
        ]);

        // Verify the enrollment belongs to this class
        $enrollment = ClassEnrollment::where('id', $validated['class_enrollment_id'])
            ->where('classroom_term_id', $classroomTerm->id)
            ->firstOrFail();

        $status = StudentAttendance::statusFromCode($validated['code']);

        StudentAttendance::updateOrCreate(
            [
                'class_enrollment_id' => $enrollment->id,
                'attendance_date' => $validated['date'],
            ],
            [
                'academic_term_id' => $classroomTerm->academic_term_id,
                'classroom_term_id' => $classroomTerm->id,
                'student_id' => $enrollment->student_id,
                'status' => $status,
                'input_by' => $request->user()->id,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Tersimpan otomatis'
        ]);
    }

    private function selectedMonth(Request $request, ClassroomTerm $classroomTerm): CarbonImmutable
    {
        $month = $request->query('month')
            ?: $classroomTerm->academicTerm?->starts_at?->format('Y-m')
            ?: now()->format('Y-m');

        try {
            return CarbonImmutable::parse($month.'-01')->startOfMonth();
        } catch (\Throwable) {
            return CarbonImmutable::parse(now()->format('Y-m').'-01')->startOfMonth();
        }
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function dateRangeForMonth(ClassroomTerm $classroomTerm, CarbonImmutable $monthStart): array
    {
        $startDate = $monthStart->startOfMonth();
        $endDate = $monthStart->endOfMonth();
        $term = $classroomTerm->academicTerm;

        if ($term?->starts_at && $term->starts_at->greaterThan($startDate)) {
            $startDate = CarbonImmutable::parse($term->starts_at);
        }

        if ($term?->ends_at && $term->ends_at->lessThan($endDate)) {
            $endDate = CarbonImmutable::parse($term->ends_at);
        }

        if ($startDate->greaterThan($endDate)) {
            return [$monthStart->startOfMonth(), $monthStart->endOfMonth()];
        }

        return [$startDate, $endDate];
    }

    /** @return Collection<int, array{value: string, label: string}> */
    private function availableMonths(ClassroomTerm $classroomTerm): Collection
    {
        $term = $classroomTerm->academicTerm;

        if (! $term?->starts_at || ! $term?->ends_at) {
            return collect([[
                'value' => now()->format('Y-m'),
                'label' => now()->locale('id')->translatedFormat('F Y'),
            ]]);
        }

        return collect(CarbonPeriod::create(
            CarbonImmutable::parse($term->starts_at)->startOfMonth(),
            '1 month',
            CarbonImmutable::parse($term->ends_at)->startOfMonth(),
        ))->map(fn ($date) => [
            'value' => CarbonImmutable::parse($date)->format('Y-m'),
            'label' => CarbonImmutable::parse($date)->locale('id')->translatedFormat('F Y'),
        ])->values();
    }

    /** @return Collection<string, SchoolHoliday> */
    private function schoolHolidaysForRange(ClassroomTerm $classroomTerm, CarbonImmutable $startDate, CarbonImmutable $endDate): Collection
    {
        return SchoolHoliday::query()
            ->where('academic_term_id', $classroomTerm->academic_term_id)
            ->whereBetween('holiday_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('holiday_date')
            ->get()
            ->keyBy(fn (SchoolHoliday $holiday) => $holiday->holiday_date->toDateString());
    }

    /**
     * @param  Collection<int, CarbonImmutable>  $days
     * @param  Collection<string, SchoolHoliday>  $schoolHolidays
     * @return Collection<int, CarbonImmutable>
     */
    private function schoolAttendanceDays(Collection $days, Collection $schoolHolidays): Collection
    {
        return $days
            ->filter(function (CarbonImmutable $day) use ($schoolHolidays): bool {
                if ($day->isSaturday() || $day->isSunday()) {
                    return false;
                }

                return ! $schoolHolidays->has($day->toDateString());
            })
            ->values();
    }

    /**
     * @param  Collection<int, ClassEnrollment>  $enrollments
     * @param  Collection<int, CarbonImmutable>  $days
     * @param  Collection<string, StudentAttendance>  $attendances
     * @return array{array<int, array{sick: int, permission: int, absent: int}>, array{sick: int, permission: int, absent: int}}
     */
    private function totalsFor(Collection $enrollments, Collection $days, Collection $attendances): array
    {
        $studentTotals = [];
        $classTotals = ['sick' => 0, 'permission' => 0, 'absent' => 0];

        foreach ($enrollments as $enrollment) {
            $totals = ['sick' => 0, 'permission' => 0, 'absent' => 0];

            foreach ($days as $day) {
                $attendance = $attendances->get($enrollment->id.'-'.$day->toDateString());
                $status = $attendance?->status;

                if ($status === StudentAttendance::STATUS_SICK) {
                    $totals['sick']++;
                    $classTotals['sick']++;
                } elseif ($status === StudentAttendance::STATUS_PERMISSION) {
                    $totals['permission']++;
                    $classTotals['permission']++;
                } elseif ($status === StudentAttendance::STATUS_ABSENT) {
                    $totals['absent']++;
                    $classTotals['absent']++;
                }
            }

            $studentTotals[$enrollment->id] = $totals;
        }

        return [$studentTotals, $classTotals];
    }

    private function canViewAllClasses(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']);
    }

    private function canViewClass(User $user, ClassroomTerm $classroomTerm): bool
    {
        return $this->canViewAllClasses($user) || $this->isActiveHomeroomTeacher($user, $classroomTerm);
    }

    private function canUpdateClass(User $user, ClassroomTerm $classroomTerm): bool
    {
        return $user->hasRole('admin') || $this->isActiveHomeroomTeacher($user, $classroomTerm);
    }

    private function isActiveHomeroomTeacher(User $user, ClassroomTerm $classroomTerm): bool
    {
        $teacher = $user->teacher;

        if (! $teacher) {
            return false;
        }

        return HomeroomAssignment::query()
            ->where('classroom_term_id', $classroomTerm->id)
            ->where('teacher_id', $teacher->id)
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()->toDateString());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString());
            })
            ->exists();
    }
}
