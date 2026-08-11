<?php

namespace App\Http\Controllers;

use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\SchoolEvent;
use App\Models\SchoolHoliday;
use App\Models\TahfidzHalaqah;
use App\Models\TasmiRecord;
use App\Services\DiniyyahAssessmentComponentBuilder;
use App\Services\Exports\GuruPerformaXlsxExporter;
use App\Services\GuruPerformaService;
use App\Services\TasmiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GuruDashboardController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasRole('guru'), 403);

        $teacher = $request->user()->teacher;

        // Auto-create assessment sets for assignments that don't have one yet
        if ($teacher) {
            $assignments = DiniyyahTeacherAssignment::with('classSubject.subject')
                ->where('teacher_id', $teacher->id)
                ->where(function ($query) {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>=', $this->wibToday());
                })
                ->get();

            $builder = new DiniyyahAssessmentComponentBuilder;

            foreach ($assignments as $assignment) {
                $classSubject = $assignment->classSubject;
                if ($classSubject) {
                    $exists = DiniyyahAssessmentSet::where('diniyyah_class_subject_id', $classSubject->id)->exists();
                    if (! $exists) {
                        $newSet = DiniyyahAssessmentSet::create([
                            'diniyyah_class_subject_id' => $classSubject->id,
                            'title' => 'Penilaian '.$classSubject->subject?->name,
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
                        $query->whereNull('ends_at')->orWhere('ends_at', '>=', $this->wibToday());
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
                        $query->whereNull('ends_at')->orWhere('ends_at', '>=', $this->wibToday());
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
            ->with('classSubject.subject')
            ->where('teacher_id', $teacher?->id ?? 0)
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $this->wibToday());
            })
            ->get();

        // 3c. Apakah guru punya penugasan Tafsir → tampilkan tombol "Jurnal Tafsir".
        $hasTafsirAssignment = $diniyyahAssignments->contains(function ($assignment): bool {
            $subject = $assignment->classSubject?->subject;
            if (! $subject) {
                return false;
            }

            return strtolower($subject->code) === 'tafsir'
                || str_contains(strtolower($subject->name), 'tafsir');
        });

        // 3d. Kartu performa jurnal mengajar bulan ini (atau bulan dari query).
        // Default "bulan berjalan" memakai WIB — app tz=UTC.
        $performaMonth = (int) $request->input('month', (int) Carbon::now('Asia/Jakarta')->format('n'));
        $performaYear = (int) $request->input('year', (int) Carbon::now('Asia/Jakarta')->format('Y'));
        $performa = ($teacher && $diniyyahAssignments->isNotEmpty())
            ? app(GuruPerformaService::class)->calculate($teacher, $performaMonth, $performaYear)
            : null;
        $performaMonthOptions = $this->buildMonthOptions();

        // 3e. Data PJ Tasmi' — guru yang ditugaskan sebagai penguji ujian tasmi'.
        // Ustadz (gender=male) hanya melihat kelas ikhwan, ustadzah (female) hanya akhwat.
        $tasmiService = app(TasmiService::class);
        $tasmiExaminerAssignment = $teacher ? $tasmiService->activeExaminerAssignment($teacher) : null;
        $tasmiEligibleClassrooms = $teacher ? $tasmiService->eligibleClassroomTerms($teacher) : collect();
        $tasmiGenderScope = $teacher ? $tasmiService->expectedGenderScope($teacher) : null;
        // Jumlah record tasmi' yang sudah diinput guru ini pada periode aktif (untuk badge statistik).
        $tasmiRecordsCount = $tasmiExaminerAssignment
            ? TasmiRecord::where('examiner_teacher_id', $teacher?->id)
                ->where('academic_term_id', $tasmiExaminerAssignment->academic_term_id)
                ->count()
            : 0;

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
            'hasTafsirAssignment',
            'upcomingAlerts',
            'performa',
            'performaMonth',
            'performaYear',
            'performaMonthOptions',
            'tasmiExaminerAssignment',
            'tasmiEligibleClassrooms',
            'tasmiGenderScope',
            'tasmiRecordsCount'
        ));
    }

    /**
     * Halaman detail "Performa Saya": 3 angka + daftar slot kosong yang bisa
     * langsung diklik untuk mengisi jurnal.
     */
    public function performa(Request $request): View
    {
        abort_unless($request->user()->hasRole('guru'), 403);
        $teacher = $request->user()->teacher;
        abort_unless($teacher, 403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');

        $month = (int) $request->input('month', (int) Carbon::now('Asia/Jakarta')->format('n'));
        $year = (int) $request->input('year', (int) Carbon::now('Asia/Jakarta')->format('Y'));

        $performa = app(GuruPerformaService::class)->calculate($teacher, $month, $year);
        $monthOptions = $this->buildMonthOptions();

        return view('guru.performa', compact('teacher', 'performa', 'month', 'year', 'monthOptions'));
    }

    public function performaExport(
        Request $request,
        string $format,
        GuruPerformaXlsxExporter $xlsxExporter,
    ) {
        abort_unless($request->user()->hasRole('guru'), 403);
        $teacher = $request->user()->teacher;
        abort_unless($teacher, 403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        $month = (int) $request->input('month', (int) Carbon::now('Asia/Jakarta')->format('n'));
        $year = (int) $request->input('year', (int) Carbon::now('Asia/Jakarta')->format('Y'));
        $performa = app(GuruPerformaService::class)->calculate($teacher, $month, $year);
        $fileStem = Str::slug('performa-'.$teacher->name.'-'.$performa['month_label']);

        if ($format === 'xlsx') {
            $content = $xlsxExporter->export($performa, $teacher);

            return response($content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$fileStem.'.xlsx"',
                'Content-Length' => strlen($content),
            ]);
        }

        return Pdf::loadView('reports.guru-performa', compact('teacher', 'performa'))
            ->setPaper('a3', 'landscape')
            ->download($fileStem.'.pdf');
    }

    /**
     * Daftar 12 bulan terakhir (current & past, tanpa future) untuk dropdown
     * pilih bulan. Dipakai dashboard & halaman performa.
     *
     * @return Collection<int, array{value: array{month: int, year: int}, label: string}>
     */
    private function buildMonthOptions(): Collection
    {
        $now = Carbon::now('Asia/Jakarta');
        $options = collect();
        for ($i = 0; $i < 12; $i++) {
            $date = $now->copy()->subMonths($i);
            $options->push([
                'value' => ['month' => (int) $date->format('n'), 'year' => (int) $date->format('Y')],
                'label' => $date->locale('id')->translatedFormat('F Y'),
            ]);
        }

        return $options;
    }

    /** @param Collection<int, SchoolEvent> $events
     * @param  Collection<int, SchoolHoliday>  $holidays
     * @return Collection<int, array<string, mixed>>
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

    private function countdownLabel(CarbonInterface $date): string
    {
        $days = today()->diffInDays($date, false);

        return match (true) {
            $days < 0 => 'Sudah lewat',
            $days === 0 => 'Hari ini',
            $days === 1 => 'Besok',
            default => $days.' hari lagi',
        };
    }

    /**
     * Tanggal "hari ini" dalam WIB (Asia/Jakarta) untuk jendela penugasan.
     * App timezone = UTC; "hari ini" dari sudut pandang user = WIB.
     */
    private function wibToday(): string
    {
        return Carbon::now('Asia/Jakarta')->toDateString();
    }
}
