<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ClassEnrollment;
use App\Models\ClassroomTerm;
use App\Models\SchoolHoliday;
use App\Models\StudentAttendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClassAttendanceReportService
{
    public const LEGEND = 'H: Hadir; H*: Hadir bawaan (tidak ada catatan tersimpan); S: Sakit; I: Izin; A: Alpa; L: Libur; —: belum berlangsung, di luar periode, atau tidak ada catatan setelah keluar.';

    public const ASSUMPTION = 'Tanggal masuk kelas belum tersedia. Keanggotaan mengikuti enrollment kelas/semester; tanggal efektif tanpa catatan sampai hari ini dianggap Hadir. Perhitungan ini tidak membuat atau mengubah catatan presensi.';

    public const FORMULA = 'Kehadiran = Hadir ÷ hari yang dihitung untuk santri × 100%. Libur khusus (L) pada hari efektif, tanggal mendatang, dan tanggal tanpa catatan setelah keluar tidak masuk pembagi.';

    public function canExportClass(User $user, ClassroomTerm $classroom, bool $adminContext = false): bool
    {
        if ($adminContext) {
            return $user->hasRole('admin');
        }

        return $user->hasRole('guru') && $user->teacher !== null
            && $this->classroomsFor($user, false)->whereKey($classroom->id)->exists();
    }

    /** @return array<string, mixed> */
    public function options(User $user, bool $adminContext, ?int $classId = null, ?string $month = null): array
    {
        $classes = $this->classroomsFor($user, $adminContext)
            ->with('academicTerm.academicYear')->when($classId !== null, fn ($query) => $query->whereKey($classId))
            ->orderByDesc('academic_term_id')->orderBy('name')->orderBy('id')->get();
        $terms = $classes->pluck('academicTerm')->filter()->unique('id')->values();
        $selectedTerm = $terms->firstWhere('is_active', true) ?? $terms->first();
        $today = CarbonImmutable::now('Asia/Jakarta');
        $defaultMonth = $month ?: $today->format('Y-m');
        if (! $month && $selectedTerm) {
            if ($selectedTerm->starts_at && $defaultMonth < $selectedTerm->starts_at->format('Y-m')) {
                $defaultMonth = $selectedTerm->starts_at->format('Y-m');
            } elseif ($selectedTerm->ends_at && $defaultMonth > $selectedTerm->ends_at->format('Y-m')) {
                $defaultMonth = $selectedTerm->starts_at?->format('Y-m') ?? $selectedTerm->ends_at->format('Y-m');
            }
        }

        return [
            'admin' => $adminContext,
            'terms' => $terms->map(fn (AcademicTerm $term): array => [
                'id' => (string) $term->id,
                'label' => trim(($term->academicYear?->name ?? '').' · '.$term->name),
                'start' => $term->starts_at?->toDateString(),
                'end' => $term->ends_at?->toDateString(),
            ])->all(),
            'classes' => $classes->map(fn (ClassroomTerm $class): array => [
                'id' => (string) $class->id, 'term_id' => (string) $class->academic_term_id, 'name' => $class->name,
            ])->all(),
            'initial' => [
                'academic_term_id' => (string) ($selectedTerm?->id ?? ''),
                'classroom_term_id' => $adminContext ? 'all' : (string) ($classes->first()?->id ?? ''),
                'period_mode' => 'month', 'month' => $defaultMonth,
                'start_date' => max($defaultMonth.'-01', $selectedTerm?->starts_at?->toDateString() ?? $defaultMonth.'-01'),
                'end_date' => min(CarbonImmutable::parse($defaultMonth.'-01')->endOfMonth()->toDateString(), $selectedTerm?->ends_at?->toDateString() ?? '9999-12-31'),
            ],
        ];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function build(User $user, array $input, bool $adminContext = false): array
    {
        $validated = Validator::make($input, [
            'academic_term_id' => ['required', 'integer'],
            'classroom_term_id' => ['required', 'string', 'regex:/^(all|[1-9][0-9]*)$/'],
            'period_mode' => ['required', Rule::in(['month', 'range'])],
            'month' => ['exclude_unless:period_mode,month', 'required', 'date_format:Y-m'],
            'start_date' => ['exclude_unless:period_mode,range', 'required', 'date_format:Y-m-d'],
            'end_date' => ['exclude_unless:period_mode,range', 'required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ], [
            'required' => ':attribute wajib diisi.',
            'integer' => ':attribute tidak valid.',
            'regex' => 'Pilihan kelas tidak valid.',
            'in' => 'Pilihan periode laporan tidak valid.',
            'date_format' => 'Format :attribute tidak valid.',
            'end_date.after_or_equal' => 'Tanggal akhir harus sama dengan atau setelah tanggal awal.',
        ], [
            'academic_term_id' => 'semester', 'classroom_term_id' => 'kelas',
            'month' => 'bulan', 'start_date' => 'tanggal awal', 'end_date' => 'tanggal akhir',
            'period_mode' => 'periode laporan',
        ])->validate();

        $allowedClasses = $this->classroomsFor($user, $adminContext);
        $selection = $validated['classroom_term_id'];
        abort_if($selection === 'all' && ! $adminContext, 403);
        if ($selection !== 'all') {
            $selectedClass = (clone $allowedClasses)->whereKey((int) $selection)->first();
            abort_unless($selectedClass, 403);
            if ((int) $selectedClass->academic_term_id !== (int) $validated['academic_term_id']) {
                throw ValidationException::withMessages(['classroom_term_id' => 'Kelas tidak berada dalam semester yang dipilih.']);
            }
        }

        $classes = $allowedClasses->where('academic_term_id', $validated['academic_term_id'])
            ->when($selection !== 'all', fn ($query) => $query->whereKey((int) $selection))
            ->with(['academicTerm.academicYear.school', 'homeroomAssignments.teacher'])
            ->orderBy('name')->orderBy('id')->get();
        if ($classes->isEmpty()) {
            throw ValidationException::withMessages(['academic_term_id' => 'Tidak ada kelas yang dapat dilaporkan pada semester ini.']);
        }

        $term = $classes->first()->academicTerm;
        [$start, $end] = $this->period($term, $validated);
        $today = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
        $holidays = SchoolHoliday::query()->where('academic_term_id', $term->id)
            ->whereDate('holiday_date', '>=', $start->startOfMonth()->toDateString())
            ->whereDate('holiday_date', '<=', $end->endOfMonth()->toDateString())
            ->get()->keyBy(fn ($holiday) => $holiday->holiday_date->toDateString());
        $months = $this->calendar($term, $start, $end, $today, $holidays);
        $enrollments = ClassEnrollment::query()->whereIn('classroom_term_id', $classes->modelKeys())
            ->where('academic_term_id', $term->id)
            ->with(['student' => fn ($query) => $query->withTrashed()->with('departures')])
            ->orderBy('roll_number')->orderBy('student_id')->get()->groupBy('classroom_term_id');
        $attendances = StudentAttendance::query()->where('academic_term_id', $term->id)
            ->whereIn('classroom_term_id', $classes->modelKeys())
            ->whereDate('attendance_date', '>=', $start->toDateString())
            ->whereDate('attendance_date', '<=', $end->toDateString())
            ->get()->keyBy(fn ($attendance) => $attendance->class_enrollment_id.'|'.$attendance->attendance_date->toDateString());

        $classReports = $classes->map(function (ClassroomTerm $class) use ($enrollments, $attendances, $months, $start, $end): array {
            $students = ($enrollments->get($class->id) ?? collect())->values()->map(
                fn (ClassEnrollment $enrollment, int $index) => $this->student($enrollment, $index + 1, $months, $attendances)
            )->all();
            $homerooms = $class->homeroomAssignments->filter(fn ($assignment) => (! $assignment->starts_at || $assignment->starts_at->toDateString() <= $end->toDateString())
                && (! $assignment->ends_at || $assignment->ends_at->toDateString() >= $start->toDateString())
            )->pluck('teacher.name')->filter()->unique()->implode(', ');

            return [
                'id' => $class->id, 'name' => $class->name,
                'school' => $class->academicTerm->academicYear?->school?->name ?? 'Ruang GQ',
                'academic_year' => $class->academicTerm->academicYear?->name ?? '—',
                'semester' => $class->academicTerm->name,
                'homeroom' => $homerooms ?: '—', 'months' => $months, 'students' => $students,
                'effective_days' => array_sum(array_column($months, 'selected_effective_days')),
                'elapsed_days' => array_sum(array_column($months, 'elapsed_effective_days')),
            ];
        })->all();

        return [
            'academic_term_id' => $term->id, 'all_classes' => $selection === 'all',
            'period' => [
                'mode' => $validated['period_mode'], 'start' => $start->toDateString(), 'end' => $end->toDateString(),
                'label' => $validated['period_mode'] === 'month' ? $start->locale('id')->translatedFormat('F Y')
                    : $start->locale('id')->translatedFormat('d F Y').' – '.$end->locale('id')->translatedFormat('d F Y'),
                'as_of' => $today->toDateString(),
            ],
            'generated_at' => CarbonImmutable::now('Asia/Jakarta')->locale('id')->translatedFormat('d F Y, H:i').' WIB',
            'classes' => $classReports, 'legend' => self::LEGEND, 'assumption' => self::ASSUMPTION, 'formula' => self::FORMULA,
        ];
    }

    /** @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    public function preview(array $report): array
    {
        return [
            'period' => $report['period'], 'generated_at' => $report['generated_at'],
            'classes' => array_map(fn (array $class): array => [
                'name' => $class['name'], 'students' => count($class['students']),
                'effective_days' => $class['effective_days'], 'elapsed_days' => $class['elapsed_days'],
                'months' => array_map(fn (array $month): array => [
                    'label' => $month['label'], 'full_effective_days' => $month['full_effective_days'],
                    'selected_effective_days' => $month['selected_effective_days'], 'elapsed_effective_days' => $month['elapsed_effective_days'],
                ], $class['months']),
            ], $report['classes']),
            'assumption' => $report['assumption'], 'formula' => $report['formula'],
        ];
    }

    private function classroomsFor(User $user, bool $adminContext): Builder
    {
        $query = ClassroomTerm::query();
        if ($adminContext) {
            abort_unless($user->hasRole('admin'), 403);

            return $query;
        }

        abort_unless($user->hasRole('guru') && $user->teacher, 403);
        $today = CarbonImmutable::now('Asia/Jakarta')->toDateString();

        return $query->whereHas('homeroomAssignments', fn ($assignments) => $assignments
            ->where('teacher_id', $user->teacher->id)
            ->where(fn ($dates) => $dates->whereNull('starts_at')->orWhereDate('starts_at', '<=', $today))
            ->where(fn ($dates) => $dates->whereNull('ends_at')->orWhereDate('ends_at', '>=', $today)));
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function period(AcademicTerm $term, array $filters): array
    {
        if ($filters['period_mode'] === 'month') {
            $start = CarbonImmutable::createFromFormat('!Y-m', $filters['month'], 'Asia/Jakarta')->startOfMonth();
            $end = $start->endOfMonth()->startOfDay();
            if ($term->starts_at) {
                $start = max($start, CarbonImmutable::parse($term->starts_at->toDateString(), 'Asia/Jakarta'));
            }
            if ($term->ends_at) {
                $end = min($end, CarbonImmutable::parse($term->ends_at->toDateString(), 'Asia/Jakarta'));
            }
            if ($start->greaterThan($end)) {
                throw ValidationException::withMessages(['month' => 'Bulan berada di luar semester yang dipilih.']);
            }
        } else {
            $start = CarbonImmutable::parse($filters['start_date'], 'Asia/Jakarta');
            $end = CarbonImmutable::parse($filters['end_date'], 'Asia/Jakarta');
            if (($term->starts_at && $start->toDateString() < $term->starts_at->toDateString())
                || ($term->ends_at && $end->toDateString() > $term->ends_at->toDateString())) {
                throw ValidationException::withMessages(['start_date' => 'Rentang tanggal harus berada dalam semester yang dipilih.']);
            }
        }

        return [$start->startOfDay(), $end->startOfDay()];
    }

    /** @return array<int, array<string, mixed>> */
    private function calendar(AcademicTerm $term, CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $today, Collection $holidays): array
    {
        $months = [];
        foreach (CarbonPeriod::create($start->startOfMonth(), '1 month', $end->startOfMonth()) as $month) {
            $month = CarbonImmutable::parse($month->toDateString(), 'Asia/Jakarta');
            $days = [];
            foreach (CarbonPeriod::create($month, $month->endOfMonth()) as $date) {
                $date = CarbonImmutable::parse($date->toDateString(), 'Asia/Jakarta');
                $key = $date->toDateString();
                $insideTerm = (! $term->starts_at || $key >= $term->starts_at->toDateString())
                    && (! $term->ends_at || $key <= $term->ends_at->toDateString());
                $effective = $insideTerm && ! $date->isWeekend() && ! $holidays->has($key);
                $selected = $key >= $start->toDateString() && $key <= $end->toDateString();
                $days[] = [
                    'date' => $key, 'number' => (int) $date->format('j'), 'weekday' => $date->locale('id')->translatedFormat('D'),
                    'effective' => $effective, 'selected' => $selected, 'elapsed' => $key <= $today->toDateString(),
                    'holiday' => $holidays->get($key)?->title ?? ($date->isWeekend() ? 'Akhir pekan' : null),
                ];
            }
            $months[] = [
                'key' => $month->format('Y-m'), 'label' => $month->locale('id')->translatedFormat('F Y'), 'days' => $days,
                'full_effective_days' => count(array_filter($days, fn ($day) => $day['effective'])),
                'selected_effective_days' => count(array_filter($days, fn ($day) => $day['effective'] && $day['selected'])),
                'elapsed_effective_days' => count(array_filter($days, fn ($day) => $day['effective'] && $day['selected'] && $day['elapsed'])),
            ];
        }

        return $months;
    }

    /** @return array<string, mixed> */
    private function student(ClassEnrollment $enrollment, int $number, array $months, Collection $attendances): array
    {
        $student = $enrollment->student;
        $departures = $student?->departures ?? collect();
        $latestDeparture = $departures->sortByDesc('effective_date')->first();
        $counts = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0, 'L' => 0];
        $assumed = 0;
        $daily = [];
        foreach ($months as $month) {
            foreach ($month['days'] as $day) {
                $date = $day['date'];
                $code = '—';
                if ($day['selected'] && $day['effective'] && $day['elapsed']) {
                    $attendance = $attendances->get($enrollment->id.'|'.$date);
                    if ($attendance && array_key_exists($attendance->status, StudentAttendance::statusOptions())) {
                        $code = StudentAttendance::codeFromStatus($attendance->status);
                    } else {
                        $afterDeparture = $departures->contains(fn ($departure) => $departure->effective_date->toDateString() <= $date
                            && ($enrollment->status !== 'active' || ! $departure->restored_at || $date < $departure->restored_at->setTimezone('Asia/Jakarta')->toDateString()));
                        if (! $attendance && ! $afterDeparture && $student) {
                            $code = 'H*';
                            $assumed++;
                        }
                    }
                    if ($code === 'H*') {
                        $counts['H']++;
                    } elseif (isset($counts[$code])) {
                        $counts[$code]++;
                    }
                } elseif ($day['selected'] && ! $day['effective'] && $day['holiday']) {
                    $code = 'L';
                }
                $daily[$date] = $code;
            }
        }
        $eligible = $counts['H'] + $counts['S'] + $counts['I'] + $counts['A'];
        $ratio = $eligible > 0 ? $counts['H'] / $eligible : null;
        $archived = ! $student || $student->trashed() || $student->status !== 'active' || $enrollment->status !== 'active';

        return [
            'number' => $number, 'enrollment_id' => $enrollment->id, 'student_id' => $enrollment->student_id,
            'nis' => (string) ($student?->nis ?? ''), 'name' => $student?->name ?? 'Profil tidak tersedia',
            'archived' => $archived, 'status_label' => $archived ? 'Arsip / tidak aktif' : 'Aktif',
            'departure_date' => $latestDeparture?->effective_date?->toDateString(),
            'counts' => $counts, 'assumed_present' => $assumed, 'eligible_days' => $eligible,
            'percentage' => $ratio, 'percentage_label' => $ratio === null ? '—' : number_format($ratio * 100, 1, ',', '.').'%',
            'daily' => $daily,
        ];
    }
}
