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

class GuruDiniyyahJournalController extends Controller
{
    public function index(Request $request)
    {
        $teacher = Auth::user()->teacher;
        if (!$teacher) {
            abort(403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        }
        
        // Active assignments for this teacher
        $assignments = DiniyyahTeacherAssignment::with(['classSubject.subject', 'classSubject.classroomTerm.classroom'])
            ->where('teacher_id', $teacher->id)
            ->get();
            
        // Group by classroom_term_id to get unique classes
        $classes = $assignments->pluck('classSubject.classroomTerm')->unique('id');
            
        $selectedClassroomTermId = $request->query('classroom_term_id');
        $selectedDate = $request->query('date', date('Y-m-d'));
        
        $students = collect();
        $dailyAbsences = [];
        $existingJournals = collect();
        $classAssignments = collect();
        
        if ($selectedClassroomTermId) {
            $classAssignments = $assignments->filter(function($assignment) use ($selectedClassroomTermId) {
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
            
            // Fetch existing journals for THIS class and THIS date, by ALL teachers, so they can see the whole log
            $existingJournals = DiniyyahClassJournal::with(['teacherAssignment.teacher', 'teacherAssignment.classSubject.subject', 'absences.classEnrollment.student'])
                ->whereDate('date', $selectedDate)
                ->whereHas('teacherAssignment.classSubject', function($query) use ($selectedClassroomTermId) {
                    $query->where('classroom_term_id', $selectedClassroomTermId);
                })
                ->orderBy('session_hour', 'asc')
                ->get();
        }
        
        $classSessions = ClassSession::orderBy('starts_at')->get();

        return view('guru.diniyyah-journals.index', compact(
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
        if ($assignment->teacher_id !== $teacher->id) {
            abort(403);
        }

        // Pastikan tugas mengajar benar-benar milik classroom_term yang dipilih —
        // cegah guru mengisi jurnal untuk kelas lain via parameter yang dipalsukan.
        $assignmentClassroomTermId = $assignment->classSubject->classroom_term_id;
        if ((int) $assignmentClassroomTermId !== (int) $validated['classroom_term_id']) {
            abort(403, 'Tugas mengajar tidak sesuai dengan kelas yang dipilih.');
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
                'date' => $validated['date']
            ])->withInput()->with('error', 'Jurnal untuk kelas, tanggal, dan jam sesi ini sudah pernah diisi.');
        }

        $journal = DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $validated['diniyyah_teacher_assignment_id'],
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

        return redirect()->route('guru.diniyyah-journals.index', [
            'classroom_term_id' => $validated['classroom_term_id'],
            'date' => $validated['date']
        ])->with('success', 'Jurnal jam ke-'.$validated['session_hour'].' berhasil disimpan.');
    }
    
    public function destroy(DiniyyahClassJournal $diniyyah_journal)
    {
        $teacher = Auth::user()->teacher;
        if ($diniyyah_journal->teacherAssignment->teacher_id !== $teacher->id) {
            abort(403);
        }
        
        $classroomTermId = $diniyyah_journal->teacherAssignment->classSubject->classroom_term_id;
        $date = $diniyyah_journal->date->format('Y-m-d');
        
        $diniyyah_journal->delete();
        
        return redirect()->route('guru.diniyyah-journals.index', [
            'classroom_term_id' => $classroomTermId,
            'date' => $date
        ])->with('success', 'Jurnal berhasil dihapus.');
    }
}
