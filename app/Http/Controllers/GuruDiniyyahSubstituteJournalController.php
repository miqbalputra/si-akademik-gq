<?php

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Services\TafsirScheduleGroupingService;
use App\Support\SessionTimetable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Menu "Jurnal Guru Pengganti": semua guru (akun terhubung ke Teacher) dapat
 * mengisi jurnal KBM diniyyah saat menggantikan guru asli yang berhalangan.
 *
 * Penyimpanan: kolom diniyyah_teacher_assignment_id TETAP menunjuk assignment
 * guru asli (yang digantikan), dan kolom substitute_teacher_id menunjuk guru
 * pengganti yang mengajar. Dengan demikian jurnal pengganti:
 *  - mengisi slot jadwal asli (unik index assignment+date+session tetap berlaku),
 *  - muncul di daftar jurnal guru asli dengan tanda "digantikan oleh ...",
 *  - JP-nya dihitung ke pengganti (lihat DiniyyahClassJournal::effectiveTeacher()).
 */
class GuruDiniyyahSubstituteJournalController extends Controller
{
    public function __construct(private readonly TafsirScheduleGroupingService $tafsirScheduleGroupingService) {}

    public function index(Request $request)
    {
        $teacher = Auth::user()->teacher;
        if (! $teacher) {
            abort(403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        }

        // Seluruh daftar dan validasi mengacu ke tanggal jurnal yang dipilih,
        // bukan tanggal saat halaman dibuka. Dengan begitu penugasan historis
        // yang aktif pada tanggal tersebut tetap dapat ditangani dengan benar.
        $selectedDate = $request->query('date', Carbon::now('Asia/Jakarta')->toDateString());

        // Guru tanpa jadwal sendiri mendapat mode pengganti khusus. Ia tidak
        // dibatasi oleh jadwal miliknya, tetapi tetap dibatasi oleh gender kelas.
        $fallbackGenderGroup = ! $this->hasActiveScheduleFor($teacher, $selectedDate)
            ? $this->teacherGenderGroup($teacher)
            : null;
        $isSchedulelessSubstitute = $fallbackGenderGroup !== null;

        // Muat SEMUA assignment diniyyah aktif (semua guru), bukan hanya milik guru ini.
        $allAssignments = DiniyyahTeacherAssignment::with(['classSubject.subject', 'classSubject.classroomTerm.classroom', 'teacher', 'schedules.classSession'])
            ->whereHas('classSubject', function ($query): void {
                $query->where('is_active', true)
                    ->whereHas('classroomTerm', function ($query): void {
                        $query->where('status', 'active')
                            ->whereHas('classroom', fn ($query) => $query->where('is_active', true));
                    });
            })
            ->where(function ($query) use ($selectedDate) {
                $query->whereNull('starts_at')->orWhereDate('starts_at', '<=', $selectedDate);
            })
            ->where(function ($query) use ($selectedDate) {
                $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', $selectedDate);
            })
            ->get();
        $allAssignments->each(fn ($assignment) => $assignment->schedules
            ->each(fn ($schedule) => $schedule->setRelation('teacherAssignment', $assignment)));
        $allSchedules = $allAssignments->flatMap(fn ($assignment) => $assignment->schedules)->values();

        // Daftar kelas yang punya assignment diniyyah aktif (bisa digantikan).
        $classes = $allAssignments->pluck('classSubject.classroomTerm')->filter()->unique('id')->values();
        if ($fallbackGenderGroup !== null) {
            $classes = $classes
                ->filter(fn ($classroomTerm) => $this->classroomTermMatchesGender($classroomTerm, $fallbackGenderGroup))
                ->values();
        }

        $selectedClassroomTermId = $request->query('classroom_term_id');
        if ($fallbackGenderGroup !== null && $selectedClassroomTermId
            && ! $classes->contains(fn ($classroomTerm) => (int) $classroomTerm->id === (int) $selectedClassroomTermId)) {
            $selectedClassroomTermId = null;
        }
        $simultaneousScheduleIds = $this->tafsirScheduleGroupingService
            ->simultaneousGroupsForDate($allSchedules, $selectedDate)
            ->flatMap(fn (array $group) => $group['schedules']->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->all();

        $students = collect();
        $dailyAbsences = [];
        $existingJournals = collect();
        $classAssignments = collect();

        if ($selectedClassroomTermId) {
            // Daftar guru asli yang bisa digantikan untuk kelas ini, kecualikan diri sendiri.
            $classAssignments = $allAssignments->filter(function ($assignment) use ($selectedClassroomTermId, $teacher) {
                return $assignment->classSubject->classroom_term_id == $selectedClassroomTermId
                    && $assignment->teacher_id !== $teacher->id;
            })->values();

            $students = ClassEnrollment::with('student')
                ->where('classroom_term_id', $selectedClassroomTermId)
                ->where('status', 'active')
                ->get();

            $attendances = StudentAttendance::where('classroom_term_id', $selectedClassroomTermId)
                ->where('attendance_date', $selectedDate)
                ->get();

            foreach ($attendances as $attendance) {
                if (in_array($attendance->status, ['sick', 'permission', 'absent'])) {
                    $dailyAbsences[$attendance->class_enrollment_id] = $attendance->status;
                }
            }

            // Seluruh jurnal kelas+tanggal ini (termasuk jurnal reguler maupun pengganti)
            // agar pengganti melihat log lengkap dan terhindar dari double-submit.
            $existingJournals = DiniyyahClassJournal::with([
                'teacherAssignment.teacher',
                'substituteTeacher',
                'teacherAssignment.classSubject.subject',
                'absences.classEnrollment.student',
            ])
                ->whereDate('date', $selectedDate)
                ->whereHas('teacherAssignment.classSubject', function ($query) use ($selectedClassroomTermId) {
                    $query->where('classroom_term_id', $selectedClassroomTermId);
                })
                ->orderBy('session_hour', 'asc')
                ->get();
        }

        // Matrix slot sesi per (gender kelas yang digantikan, hari tanggal terpilih).
        $sessionSlots = collect();
        $scheduledSlots = collect();
        $selectedTerm = null;
        $hasScheduleOnDay = false;
        if ($selectedClassroomTermId) {
            $selectedTerm = ClassroomTerm::with('classroom')->find($selectedClassroomTermId);
            if ($selectedTerm) {
                $dayOfWeek = SessionTimetable::dayOfWeekIso($selectedDate);
                $sessionSlots = SessionTimetable::slotsFor($selectedTerm->classroom_id, $dayOfWeek);

                // Guru yang memiliki jadwal hanya dapat menggantikan slot jadwal
                // guru asli. Guru tanpa jadwal mendapat seluruh kombinasi mapel
                // assignment aktif dan sesi timetable kelas pada tanggal ini.
                $filledKeys = $existingJournals
                    ->map(fn ($journal) => $journal->teacherAssignment->id.'|'.$journal->session_hour)
                    ->all();
                if ($fallbackGenderGroup !== null) {
                    foreach ($classAssignments as $assignment) {
                        foreach ($sessionSlots as $slot) {
                            if ($slot->is_break) {
                                continue;
                            }

                            // Jika assignment memiliki slot Tafsir serentak yang
                            // diketahui, tetap arahkan ke menu Tafsir khusus.
                            $matchingSchedule = $assignment->schedules->first(fn ($schedule) => (int) $schedule->day_of_week === $dayOfWeek
                                && (string) ($schedule->classSession?->session_name ?? '') === (string) $slot->session_name
                            );
                            if ($matchingSchedule && in_array((int) $matchingSchedule->id, $simultaneousScheduleIds, true)) {
                                continue;
                            }

                            $scheduledSlots->push((object) [
                                'assignment_id' => $assignment->id,
                                'session_name' => $slot->session_name,
                                'subject_name' => $assignment->classSubject->subject->name,
                                'teacher_name' => $assignment->teacher?->name,
                                'starts_at' => $slot->starts_at,
                                'ends_at' => $slot->ends_at,
                                'filled' => in_array($assignment->id.'|'.$slot->session_name, $filledKeys, true),
                            ]);
                        }
                    }
                } else {
                    foreach ($classAssignments as $assignment) {
                        foreach ($assignment->schedules as $schedule) {
                            if ((int) $schedule->day_of_week !== $dayOfWeek) {
                                continue;
                            }
                            if (in_array((int) $schedule->id, $simultaneousScheduleIds, true)) {
                                continue;
                            }
                            $sessionName = $schedule->classSession?->session_name;
                            if (! $sessionName) {
                                continue;
                            }
                            $slot = $sessionSlots->firstWhere('session_name', $sessionName);
                            $scheduledSlots->push((object) [
                                'assignment_id' => $assignment->id,
                                'session_name' => $sessionName,
                                'subject_name' => $assignment->classSubject->subject->name,
                                'teacher_name' => $assignment->teacher?->name,
                                'starts_at' => $slot?->starts_at,
                                'ends_at' => $slot?->ends_at,
                                'filled' => in_array($assignment->id.'|'.$sessionName, $filledKeys, true)
                                    || $existingJournals->contains(fn ($journal) => $this->tafsirScheduleGroupingService->journalMatchesSchedule($journal, $schedule)),
                            ]);
                        }
                    }
                }
                $hasScheduleOnDay = $scheduledSlots->isNotEmpty();
                $scheduledSlots = $scheduledSlots
                    ->sortBy(fn ($slot) => $slot->starts_at ?? '99:99')
                    ->values();

                $existingJournals = $existingJournals->sortBy(function ($journal) use ($sessionSlots) {
                    return $sessionSlots->firstWhere('session_name', $journal->session_hour)?->starts_at ?? '99:99';
                })->values();
            }
        }

        return view('guru.diniyyah-substitute-journals.index', compact(
            'classes',
            'selectedClassroomTermId',
            'selectedDate',
            'students',
            'dailyAbsences',
            'classAssignments',
            'existingJournals',
            'teacher',
            'sessionSlots',
            'scheduledSlots',
            'selectedTerm',
            'hasScheduleOnDay',
            'isSchedulelessSubstitute'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'diniyyah_teacher_assignment_id' => 'required|exists:diniyyah_teacher_assignments,id',
            'date' => 'required|date',
            'session_hour' => 'required|string',
            'material' => 'required|string',
            'absences' => 'nullable|array',
            'absences.*' => 'in:sick,permission,absent,skipped',
            'classroom_term_id' => 'required|exists:classroom_terms,id',
        ]);

        $teacher = Auth::user()->teacher;
        if (! $teacher) {
            abort(403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        }

        $assignment = DiniyyahTeacherAssignment::with('classSubject.classroomTerm.classroom')->findOrFail($validated['diniyyah_teacher_assignment_id']);

        // Tugas mengajar harus milik classroom_term yang dipilih.
        $assignmentClassroomTermId = $assignment->classSubject->classroom_term_id;
        if ((int) $assignmentClassroomTermId !== (int) $validated['classroom_term_id']) {
            abort(403, 'Tugas mengajar tidak sesuai dengan kelas yang dipilih.');
        }

        if (! $this->assignmentIsActiveOn($assignment, $validated['date'])) {
            abort(403, 'Tugas mengajar sudah tidak aktif pada tanggal yang dipilih.');
        }
        abort_unless(
            $assignment->classSubject->is_active
                && $assignment->classSubject->classroomTerm->status === 'active'
                && $assignment->classSubject->classroomTerm->classroom->is_active,
            403,
            'Mapel atau kelas yang dipilih sudah tidak aktif.'
        );

        // Tidak boleh menggantikan diri sendiri — gunakan menu Jurnal Kelas biasa.
        if ($assignment->teacher_id === $teacher->id) {
            return redirect()->route('guru.diniyyah-substitute-journals.index', [
                'classroom_term_id' => $validated['classroom_term_id'],
                'date' => $validated['date'],
            ])->withInput()->with('error', 'Anda tidak bisa menggantikan diri sendiri. Gunakan menu Jurnal Kelas biasa untuk kelas/mapel Anda sendiri.');
        }

        $dayOfWeek = SessionTimetable::dayOfWeekIso($validated['date']);
        $fallbackGenderGroup = ! $this->hasActiveScheduleFor($teacher, $validated['date'])
            ? $this->teacherGenderGroup($teacher)
            : null;

        if ($fallbackGenderGroup !== null) {
            abort_unless(
                $this->classroomTermMatchesGender($assignment->classSubject->classroomTerm, $fallbackGenderGroup),
                403,
                'Kelas yang dipilih tidak sesuai gender Anda.'
            );

            $classroom = $assignment->classSubject->classroomTerm->classroom;
            $slot = SessionTimetable::slotsFor($classroom->id, $dayOfWeek)
                ->first(fn ($slot) => (string) $slot->session_name === (string) $validated['session_hour'] && ! $slot->is_break);
            abort_unless($slot !== null, 403, 'Sesi yang dipilih tidak tersedia pada timetable kelas di tanggal tersebut.');
        } else {
            // Guru yang memiliki jadwal tetap mengikuti pembatasan jadwal guru
            // asli. Assignment tanpa jadwal tetap permissive untuk kompatibilitas
            // data legacy dan test lama.
            $assignmentHasSchedules = DiniyyahTeachingSchedule::query()
                ->where('diniyyah_teacher_assignment_id', $assignment->id)
                ->exists();
            if ($assignmentHasSchedules) {
                $scheduled = DiniyyahTeachingSchedule::query()
                    ->where('diniyyah_teacher_assignment_id', $assignment->id)
                    ->where('day_of_week', $dayOfWeek)
                    ->whereHas('classSession', fn ($q) => $q->where('session_name', $validated['session_hour']))
                    ->exists();
                if (! $scheduled) {
                    return redirect()->route('guru.diniyyah-substitute-journals.index', [
                        'classroom_term_id' => $validated['classroom_term_id'],
                        'date' => $validated['date'],
                    ])->withInput()->with('error', 'Sesi/mapel ini tidak sesuai jadwal guru asli di kelas & hari ini.');
                }
            }
        }

        $allSchedules = DiniyyahTeachingSchedule::with([
            'teacherAssignment.classSubject.subject',
            'teacherAssignment.classSubject.classroomTerm.classroom',
            'classSession',
        ])->get();
        $scheduledTafsir = $allSchedules->first(fn ($schedule) => (int) $schedule->diniyyah_teacher_assignment_id === (int) $assignment->id
            && (int) $schedule->day_of_week === $dayOfWeek
            && (string) ($schedule->classSession?->session_name ?? '') === $validated['session_hour']
        );
        if ($scheduledTafsir && $this->tafsirScheduleGroupingService->isSimultaneousSchedule($allSchedules, $scheduledTafsir, $validated['date'])) {
            return redirect()->route('guru.diniyyah-substitute-tafsir-journals.index', ['date' => $validated['date']])
                ->withInput()->with('error', 'Sesi Tafsir ini berlangsung serentak. Isi melalui menu Pengganti Tafsir agar seluruh kelas pada sesi tersebut tercatat bersama.');
        }

        // Hanya terima absensi untuk enrollment aktif di classroom_term ini.
        $validEnrollmentIds = ClassEnrollment::query()
            ->where('classroom_term_id', $validated['classroom_term_id'])
            ->where('status', 'active')
            ->pluck('id')
            ->all();

        $absences = collect($validated['absences'] ?? [])
            ->filter(fn ($status, $enrollmentId) => in_array((int) $enrollmentId, $validEnrollmentIds, true));

        // Cek double slot: assignment asli + tanggal + jam sesi (unik). Bisa terisi
        // oleh guru asli (jurnal reguler) atau pengganti lain.
        $exists = DiniyyahClassJournal::where('diniyyah_teacher_assignment_id', $validated['diniyyah_teacher_assignment_id'])
            ->where('date', $validated['date'])
            ->get()
            ->contains(fn ($journal) => $scheduledTafsir
                ? $this->tafsirScheduleGroupingService->journalMatchesSchedule($journal, $scheduledTafsir)
                : (string) $journal->session_hour === $validated['session_hour']);

        if ($exists) {
            return redirect()->route('guru.diniyyah-substitute-journals.index', [
                'classroom_term_id' => $validated['classroom_term_id'],
                'date' => $validated['date'],
            ])->withInput()->with('error', 'Slot jurnal untuk kelas, tanggal, dan jam sesi ini sudah terisi (oleh guru asli atau pengganti lain).');
        }

        try {
            // Snapshot jam mulai/selesai sesi dari matrix (gender kelas + hari tanggal).
            // null bila matrix belum di-seed / tidak ada sesi — tidak menolak penyimpanan.
            $time = SessionTimetable::resolve(
                $assignment->classSubject->classroomTerm->classroom_id,
                SessionTimetable::dayOfWeekIso($validated['date']),
                $validated['session_hour'],
            );

            $journal = DiniyyahClassJournal::create([
                'diniyyah_teacher_assignment_id' => $validated['diniyyah_teacher_assignment_id'],
                'substitute_teacher_id' => $teacher->id,
                'date' => $validated['date'],
                'session_hour' => $validated['session_hour'],
                'session_starts_at' => $time['starts_at'] ?? null,
                'session_ends_at' => $time['ends_at'] ?? null,
                'material' => $validated['material'],
                'jp_count' => 1,
            ]);

            foreach ($absences as $enrollmentId => $status) {
                $journal->absences()->create([
                    'class_enrollment_id' => $enrollmentId,
                    'status' => $status,
                ]);
            }
        } catch (QueryException $e) {
            // Backstop race kondisi double-submit yang lolos pengecekan exists():
            // unique index (diniyyah_teacher_assignment_id, date, session_hour).
            if ($this->isDuplicateKeyException($e)) {
                return redirect()->route('guru.diniyyah-substitute-journals.index', [
                    'classroom_term_id' => $validated['classroom_term_id'],
                    'date' => $validated['date'],
                ])->withInput()->with('error', 'Slot jurnal untuk kelas, tanggal, dan jam sesi ini sudah terisi (oleh guru asli atau pengganti lain).');
            }

            throw $e;
        }

        return redirect()->route('guru.diniyyah-substitute-journals.index', [
            'classroom_term_id' => $validated['classroom_term_id'],
            'date' => $validated['date'],
        ])->with('success', 'Jurnal pengganti jam ke-'.$validated['session_hour'].' berhasil disimpan. JP tercatat ke Anda.');
    }

    public function destroy(DiniyyahClassJournal $diniyyah_journal)
    {
        $teacher = Auth::user()->teacher;
        if (! $teacher) {
            abort(403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        }

        // Hanya pengganti yang mengisi jurnal ini yang boleh menghapusnya.
        abort_unless(
            $diniyyah_journal->substitute_teacher_id === $teacher->id,
            403,
            'Jurnal ini hanya dapat dihapus oleh guru pengganti yang mengisinya.'
        );

        $classroomTermId = $diniyyah_journal->teacherAssignment->classSubject->classroom_term_id;
        $date = $diniyyah_journal->date->format('Y-m-d');

        $diniyyah_journal->delete();

        return redirect()->route('guru.diniyyah-substitute-journals.index', [
            'classroom_term_id' => $classroomTermId,
            'date' => $date,
        ])->with('success', 'Jurnal pengganti berhasil dihapus.');
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

    private function assignmentIsActiveOn(DiniyyahTeacherAssignment $assignment, string $date): bool
    {
        $startsAt = $assignment->starts_at?->toDateString();
        $endsAt = $assignment->ends_at?->toDateString();

        return ($startsAt === null || $startsAt <= $date)
            && ($endsAt === null || $endsAt >= $date);
    }
}
