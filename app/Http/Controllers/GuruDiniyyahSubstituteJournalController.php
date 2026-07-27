<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClassroomTerm;
use App\Models\ClassSession;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\StudentAttendance;
use App\Models\ClassEnrollment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;

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
    public function index(Request $request)
    {
        $teacher = Auth::user()->teacher;
        if (!$teacher) {
            abort(403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        }

        // Muat SEMUA assignment diniyyah aktif (semua guru), bukan hanya milik guru ini.
        $allAssignments = DiniyyahTeacherAssignment::with(['classSubject.subject', 'classSubject.classroomTerm.classroom', 'teacher'])
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()->toDateString());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString());
            })
            ->get();

        // Daftar kelas yang punya assignment diniyyah aktif (bisa digantikan).
        $classes = $allAssignments->pluck('classSubject.classroomTerm')->filter()->unique('id')->values();

        $selectedClassroomTermId = $request->query('classroom_term_id');
        $selectedDate = $request->query('date', date('Y-m-d'));

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

        $classSessions = ClassSession::orderBy('starts_at')->get();

        return view('guru.diniyyah-substitute-journals.index', compact(
            'classes',
            'selectedClassroomTermId',
            'selectedDate',
            'students',
            'dailyAbsences',
            'classAssignments',
            'existingJournals',
            'teacher',
            'classSessions'
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
        if (!$teacher) {
            abort(403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        }

        $assignment = DiniyyahTeacherAssignment::with('classSubject')->findOrFail($validated['diniyyah_teacher_assignment_id']);

        // Tugas mengajar harus milik classroom_term yang dipilih.
        $assignmentClassroomTermId = $assignment->classSubject->classroom_term_id;
        if ((int) $assignmentClassroomTermId !== (int) $validated['classroom_term_id']) {
            abort(403, 'Tugas mengajar tidak sesuai dengan kelas yang dipilih.');
        }

        // Tidak boleh menggantikan diri sendiri — gunakan menu Jurnal Kelas biasa.
        if ($assignment->teacher_id === $teacher->id) {
            return redirect()->route('guru.diniyyah-substitute-journals.index', [
                'classroom_term_id' => $validated['classroom_term_id'],
                'date' => $validated['date'],
            ])->withInput()->with('error', 'Anda tidak bisa menggantikan diri sendiri. Gunakan menu Jurnal Kelas biasa untuk kelas/mapel Anda sendiri.');
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
            ->where('session_hour', $validated['session_hour'])
            ->exists();

        if ($exists) {
            return redirect()->route('guru.diniyyah-substitute-journals.index', [
                'classroom_term_id' => $validated['classroom_term_id'],
                'date' => $validated['date'],
            ])->withInput()->with('error', 'Slot jurnal untuk kelas, tanggal, dan jam sesi ini sudah terisi (oleh guru asli atau pengganti lain).');
        }

        try {
            $journal = DiniyyahClassJournal::create([
                'diniyyah_teacher_assignment_id' => $validated['diniyyah_teacher_assignment_id'],
                'substitute_teacher_id' => $teacher->id,
                'date' => $validated['date'],
                'session_hour' => $validated['session_hour'],
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
        if (!$teacher) {
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
}