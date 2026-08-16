<?php

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\StudentAttendance;
use App\Support\SessionTimetable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class GuruDiniyyahJournalController extends Controller
{
    public function index(Request $request)
    {
        $teacher = Auth::user()->teacher;
        if (! $teacher) {
            abort(403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        }

        // Active assignments for this teacher. eager-load `schedules.classSession` agar
        // pengecekan jadwal (hari & sesi) di kelas terpilih tidak menambah query (N+1).
        $assignments = DiniyyahTeacherAssignment::with(['classSubject.subject', 'classSubject.classroomTerm.classroom', 'schedules.classSession'])
            ->where('teacher_id', $teacher->id)
            ->get();

        // Group by classroom_term_id to get unique classes
        $classes = $assignments->pluck('classSubject.classroomTerm')->unique('id');

        $selectedClassroomTermId = $request->query('classroom_term_id');
        // Default "hari ini" dalam WIB — app tz=UTC, jadi date('Y-m-d') bisa meleset
        // ke kemarin di larut malam WIB. Lihat memori app-timezone-utc-vs-wib.
        $selectedDate = $request->query('date', Carbon::now('Asia/Jakarta')->toDateString());

        $students = collect();
        $dailyAbsences = [];
        $existingJournals = collect();
        $classAssignments = collect();

        if ($selectedClassroomTermId) {
            $classAssignments = $assignments->filter(function ($assignment) use ($selectedClassroomTermId) {
                return $assignment->classSubject->classroom_term_id == $selectedClassroomTermId;
            });

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

            // Fetch existing journals for THIS class and THIS date, by ALL teachers, so they can see the whole log.
            // Termasuk jurnal pengganti (substituteTeacher) agar guru asli melihat tanda "digantikan oleh ...".
            $existingJournals = DiniyyahClassJournal::with(['teacherAssignment.teacher', 'substituteTeacher', 'teacherAssignment.classSubject.subject', 'absences.classEnrollment.student'])
                ->whereDate('date', $selectedDate)
                ->whereHas('teacherAssignment.classSubject', function ($query) use ($selectedClassroomTermId) {
                    $query->where('classroom_term_id', $selectedClassroomTermId);
                })
                ->orderBy('session_hour', 'asc')
                ->get();
        }

        // Matrix slot sesi per (gender kelas, hari tanggal terpilih) — mengakomodasi
        // perbedaan jam Ikhwan vs Akhwat (Senin) serta Kamis (Tafsir) & Jum'at.
        $sessionSlots = collect();
        $scheduledSlots = collect();
        $selectedTerm = null;
        $hasScheduleOnDay = false;
        if ($selectedClassroomTermId) {
            $selectedTerm = ClassroomTerm::with('classroom')->find($selectedClassroomTermId);
            if ($selectedTerm) {
                $dayOfWeek = SessionTimetable::dayOfWeekIso($selectedDate);
                $sessionSlots = SessionTimetable::slotsFor($selectedTerm->classroom_id, $dayOfWeek);

                // Apakah guru (asli) dijadwalkan mengajar kelas terpilih pada hari
                // tanggal terpilih? Cek lewat relasi eager-loaded `schedules` pada
                // assignment guru di kelas ini. Dipakai view untuk mematikan form
                // bila tanggal terpilih bukan hari mengajar guru di kelas itu.
                $hasScheduleOnDay = $classAssignments->contains(
                    fn ($assignment) => $assignment->schedules->contains('day_of_week', $dayOfWeek)
                );

                // Slot jadwal guru di kelas & hari ini: tiap baris DiniyyahTeachingSchedule
                // (assignment guru di kelas ini, day_of_week = hari tanggal) → satu slot
                // (assignment + sesi). Form hanya menawarkan slot ini supaya guru tak bisa
                // isi sesi/mapel di luar jadwal (mencegah tumpang-tindih sesi dgn guru lain).
                // `filled` = sudah ada jurnal (oleh guru sendiri/pengganti) → disabled di UI.
                $filledKeys = $existingJournals
                    ->map(fn ($journal) => $journal->teacherAssignment->id.'|'.$journal->session_hour)
                    ->all();
                foreach ($classAssignments as $assignment) {
                    // Tafsir diisi lewat menu "Jurnal Tafsir" (serentak, session_hour='tafsir'),
                    // bukan form reguler. Skip di sini supaya form tidak menawarkan slot Tafsir
                    // yang (karena perbedaan session_name vs 'tafsir') terlihat belum terisi dan
                    // bisa memicu guru membuat jurnal Tafsir ganda. Tabel riwayat jurnal tetap
                    // menampilkan jurnal Tafsir yang sudah ada (read-only).
                    if ($this->isTafsirSubject($assignment->classSubject->subject ?? null)) {
                        continue;
                    }
                    foreach ($assignment->schedules as $schedule) {
                        if ((int) $schedule->day_of_week !== $dayOfWeek) {
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
                            'starts_at' => $slot?->starts_at,
                            'ends_at' => $slot?->ends_at,
                            'filled' => in_array($assignment->id.'|'.$sessionName, $filledKeys, true),
                        ]);
                    }
                }
                $scheduledSlots = $scheduledSlots
                    ->sortBy(fn ($slot) => $slot->starts_at ?? '99:99')
                    ->values();

                // Urutkan jurnal yang ada by jam mulai sesi (bukan by session_hour string)
                // supaya Tafsir di Kamis tampil pertama.
                $existingJournals = $existingJournals->sortBy(function ($journal) use ($sessionSlots) {
                    return $sessionSlots->firstWhere('session_name', $journal->session_hour)?->starts_at ?? '99:99';
                })->values();
            }
        }

        // Tautan dari pengingat jurnal dapat mengarahkan guru tepat ke sesi
        // yang tertunggak. Nilai query hanya dipakai untuk memilih UI; store()
        // tetap menegakkan kepemilikan assignment dan jadwal di server.
        $requestedAssignmentId = $request->integer('assignment_id');
        $requestedSessionHour = (string) $request->query('session_hour', '');
        $selectedScheduleSlot = $scheduledSlots->first(function ($slot) use ($requestedAssignmentId, $requestedSessionHour): bool {
            return $requestedAssignmentId > 0
                && (int) $slot->assignment_id === $requestedAssignmentId
                && (string) $slot->session_name === $requestedSessionHour
                && ! $slot->filled;
        });

        return view('guru.diniyyah-journals.index', compact(
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
            'selectedScheduleSlot'
        ));
    }

    /**
     * Halaman khusus "Riwayat Jurnal Saya" — seluruh jurnal yang guru isi sebagai
     * guru asli (bukan pengganti), di semua kelas, tersusun per tanggal. Dipisah
     * dari halaman input supaya guru fokus mengisi jurnal, riwayat dibuka on-demand.
     */
    public function riwayat()
    {
        $teacher = Auth::user()->teacher;
        if (! $teacher) {
            abort(403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        }

        $myJournals = DiniyyahClassJournal::with([
            'teacherAssignment.classSubject.subject',
            'teacherAssignment.classSubject.classroomTerm.classroom',
            'absences.classEnrollment.student',
        ])
            ->whereHas('teacherAssignment', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->whereNull('substitute_teacher_id')
            ->orderByDesc('date')
            ->orderBy('session_starts_at')
            ->get();

        return view('guru.diniyyah-journals.riwayat', compact('myJournals', 'teacher'));
    }

    /**
     * Form edit jurnal yang sudah terisi (materi + presensi santri).
     * Hanya pemilik assignment asli (bukan jurnal pengganti) yang bisa edit.
     */
    public function edit(DiniyyahClassJournal $diniyyah_journal)
    {
        $teacher = Auth::user()->teacher;
        if (! $teacher) {
            abort(403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        }
        abort_unless($diniyyah_journal->substitute_teacher_id === null, 403, 'Jurnal pengganti tidak dapat diedit dari menu ini.');
        abort_unless($diniyyah_journal->teacherAssignment->teacher_id === $teacher->id, 403);

        $diniyyah_journal->load([
            'teacherAssignment.classSubject.subject',
            'teacherAssignment.classSubject.classroomTerm.classroom',
            'absences.classEnrollment.student',
        ]);

        $classroomTerm = $diniyyah_journal->teacherAssignment->classSubject->classroomTerm;
        $dateString = $diniyyah_journal->date->format('Y-m-d');

        $students = ClassEnrollment::with('student')
            ->where('classroom_term_id', $classroomTerm->id)
            ->where('status', 'active')
            ->get();

        $dailyAbsences = [];
        $attendances = StudentAttendance::where('classroom_term_id', $classroomTerm->id)
            ->where('attendance_date', $dateString)
            ->get();
        foreach ($attendances as $attendance) {
            if (in_array($attendance->status, ['sick', 'permission', 'absent'])) {
                $dailyAbsences[$attendance->class_enrollment_id] = $attendance->status;
            }
        }

        $existingAbsences = $diniyyah_journal->absences->pluck('status', 'class_enrollment_id')->all();

        $sessionLabel = SessionTimetable::label($diniyyah_journal->session_hour);
        $sessionTime = SessionTimetable::resolve(
            $classroomTerm->classroom_id,
            SessionTimetable::dayOfWeekIso($dateString),
            $diniyyah_journal->session_hour,
        );
        // Utamakan snapshot jam yang tersimpan di jurnal; fallback ke matrix.
        $sessionTime = [
            'starts_at' => $diniyyah_journal->session_starts_at ?? ($sessionTime['starts_at'] ?? null),
            'ends_at' => $diniyyah_journal->session_ends_at ?? ($sessionTime['ends_at'] ?? null),
        ];

        $journal = $diniyyah_journal;

        return view('guru.diniyyah-journals.edit', compact(
            'journal',
            'classroomTerm',
            'students',
            'dailyAbsences',
            'existingAbsences',
            'sessionLabel',
            'sessionTime',
            'teacher'
        ));
    }

    /**
     * Simpan perubahan materi + presensi jurnal. date/session/assignment immutable.
     */
    public function update(Request $request, DiniyyahClassJournal $diniyyah_journal)
    {
        $validated = $request->validate([
            'material' => 'required|string',
            'absences' => 'nullable|array',
            'absences.*' => 'in:sick,permission,absent,skipped',
        ]);

        $teacher = Auth::user()->teacher;
        if (! $teacher) {
            abort(403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        }
        abort_unless($diniyyah_journal->substitute_teacher_id === null, 403, 'Jurnal pengganti tidak dapat diedit dari menu ini.');
        abort_unless($diniyyah_journal->teacherAssignment->teacher_id === $teacher->id, 403);

        $classroomTerm = $diniyyah_journal->teacherAssignment->classSubject->classroomTerm;

        $validEnrollmentIds = ClassEnrollment::query()
            ->where('classroom_term_id', $classroomTerm->id)
            ->where('status', 'active')
            ->pluck('id')
            ->all();

        $absences = collect($validated['absences'] ?? [])
            ->filter(fn ($status, $enrollmentId) => in_array((int) $enrollmentId, $validEnrollmentIds, true));

        $diniyyah_journal->material = $validated['material'];
        $diniyyah_journal->save();

        // Sync presensi: hapus semua absensi jurnal lalu buat ulang sesuai state form.
        // Form mengirim hidden input untuk daily-locked (status harian) + checkbox 'skipped'
        // untuk manual — replikasi state form, sama seperti create.
        $diniyyah_journal->absences()->delete();
        foreach ($absences as $enrollmentId => $status) {
            $diniyyah_journal->absences()->create([
                'class_enrollment_id' => $enrollmentId,
                'status' => $status,
            ]);
        }

        return redirect()->route('guru.diniyyah-journals.index', [
            'classroom_term_id' => $classroomTerm->id,
            'date' => $diniyyah_journal->date->format('Y-m-d'),
        ])->with('success', 'Jurnal berhasil diperbarui.');
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
        if ($assignment->teacher_id !== $teacher->id) {
            abort(403);
        }

        // Pastikan tugas mengajar benar-benar milik classroom_term yang dipilih —
        // cegah guru mengisi jurnal untuk kelas lain via parameter yang dipalsukan.
        $assignmentClassroomTermId = $assignment->classSubject->classroom_term_id;
        if ((int) $assignmentClassroomTermId !== (int) $validated['classroom_term_id']) {
            abort(403, 'Tugas mengajar tidak sesuai dengan kelas yang dipilih.');
        }

        // Penegakan jadwal (enforce-if-assignment-has-schedules): bila assignment
        // punya ≥1 baris DiniyyahTeachingSchedule (kasus prod, data lengkap), kombinasi
        // (assignment, hari, sesi) harus cocok salah satu baris jadwal — jika tidak,
        // tolak. Mencegah guru mengisi sesi/mapel di luar jadwalnya (tumpang-tindih
        // sesi dgn guru lain). Bila assignment belum punya jadwal (data legacy / test
        // lama) → permissive, perilaku sama seperti sebelumnya.
        $dayOfWeek = SessionTimetable::dayOfWeekIso($validated['date']);
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
                return redirect()->route('guru.diniyyah-journals.index', [
                    'classroom_term_id' => $validated['classroom_term_id'],
                    'date' => $validated['date'],
                ])->withInput()->with('error', 'Sesi/mapel ini tidak sesuai jadwal mengajar Anda di kelas & hari ini.');
            }
        }

        // Hanya terima absensi untuk enrollment yang AKTIF di classroom_term ini.
        // Kunci (enrollment id) divalidasi terhadap daftar ini agar guru tidak bisa
        // menambah catatan absensi untuk siswa kelas/term lain.
        $validEnrollmentIds = ClassEnrollment::query()
            ->where('classroom_term_id', $validated['classroom_term_id'])
            ->where('status', 'active')
            ->pluck('id')
            ->all();

        $absences = collect($validated['absences'] ?? [])
            ->filter(fn ($status, $enrollmentId) => in_array((int) $enrollmentId, $validEnrollmentIds, true));

        // Cek double journaling
        $exists = DiniyyahClassJournal::where('diniyyah_teacher_assignment_id', $validated['diniyyah_teacher_assignment_id'])
            ->where('date', $validated['date'])
            ->where('session_hour', $validated['session_hour'])
            ->exists();

        if ($exists) {
            return redirect()->route('guru.diniyyah-journals.index', [
                'classroom_term_id' => $validated['classroom_term_id'],
                'date' => $validated['date'],
            ])->withInput()->with('error', 'Jurnal untuk kelas, tanggal, dan jam sesi ini sudah pernah diisi.');
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
            // Backstop untuk race kondisi double-submit yang lolos pengecekan exists()
            // di atas: unique index (diniyyah_teacher_assignment_id, date, session_hour).
            if ($this->isDuplicateKeyException($e)) {
                return redirect()->route('guru.diniyyah-journals.index', [
                    'classroom_term_id' => $validated['classroom_term_id'],
                    'date' => $validated['date'],
                ])->withInput()->with('error', 'Jurnal untuk kelas, tanggal, dan jam sesi ini sudah pernah diisi.');
            }

            throw $e;
        }

        return redirect()->route('guru.diniyyah-journals.index', [
            'classroom_term_id' => $validated['classroom_term_id'],
            'date' => $validated['date'],
        ])->with('success', 'Jurnal jam ke-'.$validated['session_hour'].' berhasil disimpan.');
    }

    public function destroy(DiniyyahClassJournal $diniyyah_journal)
    {
        $teacher = Auth::user()->teacher;
        if (! $teacher) {
            abort(403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        }
        // Jurnal pengganti hanya boleh dihapus oleh pengganti yang mengisinya
        // (lewat menu Jurnal Guru Pengganti), bukan oleh guru asli di sini.
        if ($diniyyah_journal->substitute_teacher_id !== null) {
            abort(403, 'Jurnal ini diisi oleh guru pengganti. Hanya pengganti yang dapat menghapusnya melalui menu Jurnal Guru Pengganti.');
        }
        if ($diniyyah_journal->teacherAssignment->teacher_id !== $teacher->id) {
            abort(403);
        }

        $classroomTermId = $diniyyah_journal->teacherAssignment->classSubject->classroom_term_id;
        $date = $diniyyah_journal->date->format('Y-m-d');

        $diniyyah_journal->delete();

        return redirect()->route('guru.diniyyah-journals.index', [
            'classroom_term_id' => $classroomTermId,
            'date' => $date,
        ])->with('success', 'Jurnal berhasil dihapus.');
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

    /**
     * Apakah subject ini Tafsir (code 'tafsir' atau nama mengandung 'Tafsir').
     * Identifikasi sama dengan GuruDiniyyahTafsirJournalController::tafsirAssignmentsFor.
     * Dipakai untuk mengecualikan Tafsir dari form reguler (diisi via menu serentak).
     */
    private function isTafsirSubject($subject): bool
    {
        if (! $subject) {
            return false;
        }

        return strtolower($subject->code) === SessionTimetable::SESSION_TAFSIR
            || str_contains(strtolower($subject->name), 'tafsir');
    }
}
