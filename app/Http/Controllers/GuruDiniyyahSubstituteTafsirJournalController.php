<?php

namespace App\Http\Controllers;

use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\Teacher;
use App\Services\DiniyyahNoKbmAgendaService;
use App\Services\TafsirScheduleGroupingService;
use App\Support\SessionTimetable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Menu "Jurnal Pengganti Tafsir (Serentak)": pengganti menggantikan guru Tafsir
 * asli untuk beberapa kelas sekaligus pada hari dan rentang waktu yang sama.
 *
 * Skenario: seorang guru Tafsir untuk beberapa kelas berhalangan; seorang
 * pengganti mengisi sesi Tafsir ke sebagian/semua kelas itu. Pengganti centang
 * kelas yang dia gantikan, isi 1 materi → terbentuk 1 jurnal pengganti per
 * kelas yang dicentang.
 *
 * Penyimpanan (sama dengan Jurnal Guru Pengganti biasa): kolom
 * `diniyyah_teacher_assignment_id` TETAP menunjuk assignment guru asli (yang
 * digantikan), dan `substitute_teacher_id` menunjuk pengganti. Dengan demikian:
 *  - mengisi slot jadwal asli (unik index assignment+date+session tetap berlaku),
 *  - muncul di daftar jurnal guru asli dengan tanda "digantikan oleh ...",
 *  - JP-nya dihitung ke pengganti (lihat DiniyyahClassJournal::effectiveTeacher()).
 *
 * Daftar kelas yang bisa digantikan = kelompok schedule Tafsir aktif milik guru
 * lain (bukan milik pengganti sendiri) yang benar-benar serentak.
 */
class GuruDiniyyahSubstituteTafsirJournalController extends Controller
{
    public function __construct(
        private readonly DiniyyahNoKbmAgendaService $noKbmAgendaService,
        private readonly TafsirScheduleGroupingService $tafsirScheduleGroupingService,
    ) {}

    public function index(Request $request)
    {
        $teacher = Auth::user()->teacher;
        if (! $teacher) {
            abort(403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        }

        $selectedDate = $request->query('date', Carbon::now('Asia/Jakarta')->toDateString());
        $fallbackGenderGroup = ! $this->hasActiveScheduleFor($teacher, $selectedDate)
            ? $this->teacherGenderGroup($teacher)
            : null;
        $schedules = $this->othersTafsirSchedulesFor($teacher, $fallbackGenderGroup);
        $agendaEvents = $this->noKbmAgendaService->eventsForRange(
            $schedules
                ->map(fn ($schedule) => $schedule->teacherAssignment?->classSubject?->classroomTerm)
                ->filter()
                ->unique('id')
                ->values(),
            Carbon::parse($selectedDate, 'Asia/Jakarta')->startOfDay(),
            Carbon::parse($selectedDate, 'Asia/Jakarta')->endOfDay(),
        );
        $simultaneousGroups = $this->tafsirScheduleGroupingService
            ->simultaneousGroupsForDate($schedules, $selectedDate)
            ->map(function (array $group) use ($agendaEvents, $selectedDate): array {
                $group['assignments'] = $group['schedules']
                    ->map(fn ($schedule) => $schedule->teacherAssignment)
                    ->filter()
                    ->unique('id')
                    ->values();
                $group['agenda_assignments'] = $group['assignments']
                    ->filter(fn ($assignment) => $this->noKbmAgendaService->forClassroomTerm(
                        $agendaEvents,
                        $assignment->classSubject->classroomTerm,
                        $selectedDate,
                    ) !== null)
                    ->mapWithKeys(fn ($assignment) => [$assignment->id => $this->noKbmAgendaService->forClassroomTerm(
                        $agendaEvents,
                        $assignment->classSubject->classroomTerm,
                        $selectedDate,
                    )]);

                return $group;
            });

        return view('guru.diniyyah-substitute-tafsir-journals.index', [
            'simultaneousGroups' => $simultaneousGroups,
            'selectedDate' => $selectedDate,
            'teacher' => $teacher,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'material' => 'required|string',
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*' => ['integer'],
        ], [
            'assignments.required' => 'Pilih minimal satu kelas yang Anda gantikan.',
            'assignments.min' => 'Pilih minimal satu kelas yang Anda gantikan.',
        ]);

        $teacher = Auth::user()->teacher;
        if (! $teacher) {
            abort(403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        }

        $fallbackGenderGroup = ! $this->hasActiveScheduleFor($teacher, $validated['date'])
            ? $this->teacherGenderGroup($teacher)
            : null;
        $schedules = $this->othersTafsirSchedulesFor($teacher, $fallbackGenderGroup);
        $group = $this->tafsirScheduleGroupingService->groupContainingAssignments(
            $this->tafsirScheduleGroupingService->simultaneousGroupsForDate($schedules, $validated['date']),
            $validated['assignments'],
        );
        if ($group === null) {
            return back()->withInput()->with('error', 'Kelas yang dipilih bukan bagian dari satu sesi Tafsir serentak. Tafsir individual diisi melalui Jurnal Pengganti reguler.');
        }

        $selectedSchedules = $group['schedules']
            ->whereIn('diniyyah_teacher_assignment_id', $validated['assignments'])
            ->values();
        $selectedAssignments = $selectedSchedules
            ->map(fn ($schedule) => $schedule->teacherAssignment)
            ->filter()
            ->unique('id')
            ->values();
        $agendaEvents = $this->noKbmAgendaService->eventsForRange(
            $selectedAssignments->pluck('classSubject.classroomTerm')->filter()->unique('id')->values(),
            Carbon::parse($validated['date'], 'Asia/Jakarta')->startOfDay(),
            Carbon::parse($validated['date'], 'Asia/Jakarta')->endOfDay(),
        );
        $agendaAssignmentIds = $selectedAssignments
            ->filter(fn ($assignment) => $this->noKbmAgendaService->forClassroomTerm(
                $agendaEvents,
                $assignment->classSubject->classroomTerm,
                $validated['date'],
            ) !== null)
            ->pluck('id')
            ->all();

        $created = 0;
        $skipped = 0;
        $agendaSkipped = count($agendaAssignmentIds);

        foreach ($selectedSchedules as $schedule) {
            $assignment = $schedule->teacherAssignment;
            if (in_array((int) $assignment->id, $agendaAssignmentIds, true)) {
                continue;
            }
            $alreadyExists = DiniyyahClassJournal::where('diniyyah_teacher_assignment_id', $assignment->id)
                ->where('date', $validated['date'])
                ->get()
                ->contains(fn ($journal) => $this->tafsirScheduleGroupingService->journalMatchesSchedule($journal, $schedule));

            if ($alreadyExists) {
                $skipped++;

                continue;
            }

            try {
                DiniyyahClassJournal::create([
                    'diniyyah_teacher_assignment_id' => $assignment->id,
                    'substitute_teacher_id' => $teacher->id,
                    'date' => $validated['date'],
                    'session_hour' => SessionTimetable::SESSION_TAFSIR,
                    'session_starts_at' => $group['starts_at'],
                    'session_ends_at' => $group['ends_at'],
                    'material' => $validated['material'],
                    'jp_count' => 1,
                ]);

                $created++;
            } catch (QueryException $e) {
                // Backstop race kondisi: unique index (assignment_id, date, session_hour).
                if ($this->isDuplicateKeyException($e)) {
                    $skipped++;

                    continue;
                }

                throw $e;
            }
        }

        $message = $created.' jurnal pengganti Tafsir berhasil dibuat untuk '.$created.' kelas yang dipilih.';
        if ($skipped > 0) {
            $message .= ' '.$skipped.' kelas sudah ada jurnal Tafsir di tanggal ini (di-skip).';
        }
        if ($agendaSkipped > 0) {
            $message .= ' '.$agendaSkipped.' kelas dibebaskan oleh agenda tanpa KBM.';
        }

        return redirect()->route('guru.diniyyah-substitute-tafsir-journals.index', ['date' => $validated['date']])
            ->with($created > 0 ? 'success' : 'error', $message);
    }

    /** Semua schedule Tafsir milik guru lain dengan relasi yang dibutuhkan. */
    private function othersTafsirSchedulesFor($teacher, ?string $genderGroup = null)
    {
        return DiniyyahTeachingSchedule::with([
            'teacherAssignment.classSubject.subject',
            'teacherAssignment.classSubject.classroomTerm.classroom',
            'teacherAssignment.teacher',
            'classSession',
        ])->whereHas('teacherAssignment', fn ($query) => $query->where('teacher_id', '!=', $teacher->id))
            ->get()
            ->filter(fn ($schedule) => $this->tafsirScheduleGroupingService->isTafsirSchedule($schedule))
            ->filter(fn ($schedule) => $genderGroup === null || $this->classroomTermMatchesGender(
                $schedule->teacherAssignment?->classSubject?->classroomTerm,
                $genderGroup,
            ) && $this->scheduleTargetIsActive($schedule))
            ->values();
    }

    /**
     * Deteksi QueryException pelanggaran unique constraint lintas driver
     * (SQLite error code 19 / MySQL SQLSTATE 23000).
     */
    private function isDuplicateKeyException(QueryException $e): bool
    {
        $sqlstate = $e->errorInfo[0] ?? null;
        $driverCode = $e->errorInfo[1] ?? null;

        return $sqlstate === '23000' || $driverCode === 19;
    }

    private function hasActiveScheduleFor(Teacher $teacher, string $date): bool
    {
        return DiniyyahTeachingSchedule::query()
            ->whereHas('teacherAssignment', function ($query) use ($teacher, $date): void {
                $query->where('teacher_id', $teacher->id)
                    ->where(function ($query) use ($date): void {
                        $query->whereNull('starts_at')->orWhereDate('starts_at', '<=', $date);
                    })
                    ->where(function ($query) use ($date): void {
                        $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', $date);
                    });
            })
            ->exists();
    }

    private function teacherGenderGroup(Teacher $teacher): ?string
    {
        return match (strtolower((string) $teacher->gender)) {
            'male' => 'male',
            'female' => 'female',
            default => null,
        };
    }

    private function classroomTermMatchesGender(?ClassroomTerm $classroomTerm, string $genderGroup): bool
    {
        $classroom = $classroomTerm?->classroom;
        if (! $classroom) {
            return false;
        }

        $classGender = strtolower(trim((string) $classroom->gender_group));
        if ($classGender === '' || $classGender === 'mixed') {
            $parsed = SessionTimetable::parseClassroom($classroom);
            $classGender = match ($parsed[0] ?? null) {
                'ikhwan' => 'male',
                'akhwat' => 'female',
                default => $classGender,
            };
        }

        return $classGender === $genderGroup;
    }

    private function scheduleTargetIsActive(mixed $schedule): bool
    {
        $assignment = $schedule->teacherAssignment ?? null;
        $classSubject = $assignment?->classSubject;
        $classroomTerm = $classSubject?->classroomTerm;

        return $classSubject?->is_active !== false
            && $classroomTerm?->status === 'active'
            && $classroomTerm->classroom?->is_active !== false;
    }
}
