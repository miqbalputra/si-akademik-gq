<?php

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
use App\Models\ClassroomTerm;
use App\Models\Student;
use App\Models\TasmiRecord;
use App\Services\TasmiService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class GuruTasmiController extends Controller
{
    public function __construct(private readonly TasmiService $tasmiService) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $teacher = $user->teacher;
        abort_unless($teacher, 403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        abort_unless($user->isTasmiExaminer(), 403, 'Anda tidak ditugaskan sebagai PJ Tasmi\'.');

        $assignment = $this->tasmiService->activeExaminerAssignment($teacher);
        $classroomTerms = $this->tasmiService->eligibleClassroomTerms($teacher);
        $genderScope = $this->tasmiService->expectedGenderScope($teacher);
        $academicTerm = $assignment?->academicTerm;

        // Riwayat tasmi' yang diinput oleh PJ ini (sebagai examiner) pada periode aktif.
        $recentRecords = TasmiRecord::query()
            ->with(['student', 'classroomTerm.classroom'])
            ->where('examiner_teacher_id', $teacher->id)
            ->when($assignment, fn ($q) => $q->where('academic_term_id', $assignment->academic_term_id))
            ->latest('exam_date')
            ->latest('id')
            ->take(10)
            ->get();

        return view('guru.tasmi.index', [
            'teacher' => $teacher,
            'assignment' => $assignment,
            'academicTerm' => $academicTerm,
            'classroomTerms' => $classroomTerms,
            'genderScope' => $genderScope,
            'recentRecords' => $recentRecords,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $teacher = $user->teacher;
        abort_unless($teacher, 403);
        abort_unless($user->isTasmiExaminer(), 403, 'Anda tidak ditugaskan sebagai PJ Tasmi\'.');

        $assignment = $this->tasmiService->activeExaminerAssignment($teacher);
        $classroomTerms = $this->tasmiService->eligibleClassroomTerms($teacher);

        $classroomTermId = $request->query('classroom_term_id');
        $selectedClassroomTerm = $classroomTerms->firstWhere('id', $classroomTermId);

        // Daftar santri aktif di kelas yang dipilih, urut by roll_number.
        $students = collect();
        if ($selectedClassroomTerm) {
            $students = ClassEnrollment::query()
                ->with('student')
                ->where('classroom_term_id', $selectedClassroomTerm->id)
                ->where('status', 'active')
                ->orderBy('roll_number')
                ->orderBy('student_id')
                ->get()
                ->map(fn (ClassEnrollment $e) => $e->student);
        }

        $studentId = $request->query('student_id');

        return view('guru.tasmi.create', [
            'teacher' => $teacher,
            'assignment' => $assignment,
            'classroomTerms' => $classroomTerms,
            'selectedClassroomTerm' => $selectedClassroomTerm,
            'students' => $students,
            'selectedStudent' => $students->firstWhere('id', $studentId),
            'genderScope' => $this->tasmiService->expectedGenderScope($teacher),
            'examTypeOptions' => TasmiRecord::examTypeOptions(),
            'predicateOptions' => TasmiRecord::predicateOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $teacher = $user->teacher;
        abort_unless($teacher, 403);
        abort_unless($user->isTasmiExaminer(), 403, 'Anda tidak ditugaskan sebagai PJ Tasmi\'.');

        $assignment = $this->tasmiService->activeExaminerAssignment($teacher);
        abort_unless($assignment, 403, 'Penugasan PJ Tasmi\' tidak ditemukan untuk periode aktif.');

        $validated = $this->validateStore($request);

        // Verifikasi kelas sesuai gender guru.
        $eligibleClassroomTerms = $this->tasmiService->eligibleClassroomTerms($teacher);
        $classroomTerm = $eligibleClassroomTerms->firstWhere('id', $validated['classroom_term_id']);
        abort_unless($classroomTerm, 403, 'Kelas yang dipilih tidak sesuai gender Anda (ustadz hanya menguji ikhwan, ustadzah hanya menguji akhwat).');

        // Verifikasi santri terdaftar aktif di kelas tersebut.
        $enrollment = ClassEnrollment::query()
            ->where('classroom_term_id', $classroomTerm->id)
            ->where('student_id', $validated['student_id'])
            ->where('status', 'active')
            ->first();
        abort_unless($enrollment, 403, 'Santri tidak terdaftar aktif di kelas yang dipilih.');

        // Validasi rentang juz sesuai tipe ujian.
        $this->assertValidJuzRange($validated);

        try {
            $record = TasmiRecord::create([
                'academic_term_id' => $assignment->academic_term_id,
                'classroom_term_id' => $validated['classroom_term_id'],
                'class_enrollment_id' => $enrollment->id,
                'student_id' => $validated['student_id'],
                'examiner_teacher_id' => $teacher->id,
                'tasmi_examiner_assignment_id' => $assignment->id,
                'exam_type' => $validated['exam_type'],
                'juz_start' => $validated['juz_start'],
                'juz_end' => $validated['juz_end'],
                'exam_day_label' => $validated['exam_day_label'] ?? null,
                'exam_date' => $validated['exam_date'],
                'hijri_date' => $validated['hijri_date'] ?? null,
                'predicate' => $validated['predicate'],
                'notes' => $validated['notes'] ?? null,
                'input_by' => $user->id,
                'input_at' => now(),
                'last_updated_by' => $user->id,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->isDuplicateKeyException($e)) {
                return back()->withInput()
                    ->withErrors(['exam_date' => 'Record tasmi\' untuk santri ini pada tanggal dan tipe ujian yang sama sudah ada. Gunakan menu edit bila ingin memperbarui.']);
            }
            throw $e;
        }

        return redirect()
            ->route('guru.tasmi.records', $record)
            ->with('status', "Data tasmi' {$record->student->name} berhasil disimpan.");
    }

    public function records(Request $request): View
    {
        $user = $request->user();
        $teacher = $user->teacher;
        abort_unless($teacher, 403);
        abort_unless($user->isTasmiExaminer(), 403, 'Anda tidak ditugaskan sebagai PJ Tasmi\'.');

        $assignment = $this->tasmiService->activeExaminerAssignment($teacher);

        $query = TasmiRecord::query()
            ->with(['student', 'classroomTerm.classroom', 'examinerTeacher'])
            ->where('examiner_teacher_id', $teacher->id)
            ->when($assignment, fn ($q) => $q->where('academic_term_id', $assignment->academic_term_id));

        // Filter sederhana.
        if ($search = $request->query('search')) {
            $query->whereHas('student', fn ($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('nis', 'ilike', "%{$search}%"));
        }
        if ($examType = $request->query('exam_type')) {
            $query->where('exam_type', $examType);
        }
        if ($predicate = $request->query('predicate')) {
            $query->where('predicate', $predicate);
        }
        if ($dateFrom = $request->query('date_from')) {
            $query->where('exam_date', '>=', $dateFrom);
        }
        if ($dateUntil = $request->query('date_until')) {
            $query->where('exam_date', '<=', $dateUntil);
        }

        $records = $query->latest('exam_date')->latest('id')->paginate(20)->withQueryString();

        return view('guru.tasmi.records', [
            'teacher' => $teacher,
            'records' => $records,
            'examTypeOptions' => TasmiRecord::examTypeOptions(),
            'predicateOptions' => TasmiRecord::predicateOptions(),
            'filters' => $request->only(['search', 'exam_type', 'predicate', 'date_from', 'date_until']),
        ]);
    }

    public function edit(Request $request, TasmiRecord $tasmi_record): View
    {
        $user = $request->user();
        $teacher = $user->teacher;
        abort_unless($teacher, 403);
        abort_unless($user->isTasmiExaminer(), 403);

        $isOwner = $tasmi_record->examiner_teacher_id === $teacher->id;
        $isAdminOrKabag = $user->hasAnyRole(['admin', 'kabag_tahfidz']);
        abort_unless($isOwner || $isAdminOrKabag, 403, 'Anda hanya bisa mengedit record tasmi\' yang Anda input sendiri.');

        $tasmi_record->load(['student', 'classroomTerm.classroom', 'academicTerm']);

        return view('guru.tasmi.edit', [
            'record' => $tasmi_record,
            'examTypeOptions' => TasmiRecord::examTypeOptions(),
            'predicateOptions' => TasmiRecord::predicateOptions(),
        ]);
    }

    public function update(Request $request, TasmiRecord $tasmi_record): RedirectResponse
    {
        $user = $request->user();
        $teacher = $user->teacher;
        abort_unless($teacher, 403);
        abort_unless($user->isTasmiExaminer(), 403);

        $isOwner = $tasmi_record->examiner_teacher_id === $teacher->id;
        $isAdminOrKabag = $user->hasAnyRole(['admin', 'kabag_tahfidz']);
        abort_unless($isOwner || $isAdminOrKabag, 403, 'Anda hanya bisa mengedit record tasmi\' yang Anda input sendiri.');

        $validated = $this->validateUpdate($request, $tasmi_record);
        $this->assertValidJuzRange($validated);

        $tasmi_record->fill([
            'exam_type' => $validated['exam_type'],
            'juz_start' => $validated['juz_start'],
            'juz_end' => $validated['juz_end'],
            'exam_day_label' => $validated['exam_day_label'] ?? null,
            'exam_date' => $validated['exam_date'],
            'hijri_date' => $validated['hijri_date'] ?? null,
            'predicate' => $validated['predicate'],
            'notes' => $validated['notes'] ?? null,
            'last_updated_by' => $user->id,
        ])->save();

        return redirect()
            ->route('guru.tasmi.edit', $tasmi_record)
            ->with('status', 'Data tasmi\' berhasil diperbarui. Perubahan tercatat di audit log.');
    }

    public function destroy(Request $request, TasmiRecord $tasmi_record): RedirectResponse
    {
        $user = $request->user();
        $teacher = $user->teacher;
        abort_unless($teacher, 403);
        abort_unless($user->isTasmiExaminer(), 403);

        $isOwner = $tasmi_record->examiner_teacher_id === $teacher->id;
        $isAdminOrKabag = $user->hasAnyRole(['admin', 'kabag_tahfidz']);
        abort_unless($isOwner || $isAdminOrKabag, 403, 'Anda hanya bisa menghapus record tasmi\' yang Anda input sendiri.');

        $tasmi_record->delete();

        return redirect()
            ->route('guru.tasmi.records')
            ->with('status', 'Record tasmi\' dihapus (soft delete). Tercatat di audit log.');
    }

    private function validateStore(Request $request): array
    {
        return $request->validate([
            'classroom_term_id' => ['required', 'exists:classroom_terms,id'],
            'student_id' => ['required', 'exists:students,id'],
            'exam_type' => ['required', Rule::in(array_keys(TasmiRecord::examTypeOptions()))],
            'juz_start' => ['required', 'integer', 'min:1', 'max:30'],
            'juz_end' => ['required', 'integer', 'min:1', 'max:30'],
            'exam_day_label' => ['nullable', 'string', 'max:50'],
            'exam_date' => ['required', 'date'],
            'hijri_date' => ['nullable', 'string', 'max:50'],
            'predicate' => ['required', Rule::in(array_keys(TasmiRecord::predicateOptions()))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function validateUpdate(Request $request, TasmiRecord $record): array
    {
        return $request->validate([
            'exam_type' => ['required', Rule::in(array_keys(TasmiRecord::examTypeOptions()))],
            'juz_start' => ['required', 'integer', 'min:1', 'max:30'],
            'juz_end' => ['required', 'integer', 'min:1', 'max:30'],
            'exam_day_label' => ['nullable', 'string', 'max:50'],
            'exam_date' => ['required', 'date'],
            'hijri_date' => ['nullable', 'string', 'max:50'],
            'predicate' => ['required', Rule::in(array_keys(TasmiRecord::predicateOptions()))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * Validasi rentang juz sesuai tipe ujian:
     * - 1_juz: juz_start harus sama dengan juz_end.
     * - 5_juz: rentang harus tepat 5 juz (juz_end - juz_start + 1 = 5).
     */
    private function assertValidJuzRange(array $data): void
    {
        if ($data['exam_type'] === TasmiRecord::EXAM_TYPE_ONE_JUZ) {
            if ((int) $data['juz_start'] !== (int) $data['juz_end']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'juz_end' => 'Untuk Tasmi\' 1 Juz, juz awal dan juz akhir harus sama.',
                ]);
            }
        }

        if ($data['exam_type'] === TasmiRecord::EXAM_TYPE_FIVE_JUZ) {
            $range = (int) $data['juz_end'] - (int) $data['juz_start'] + 1;
            if ($range !== 5) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'juz_end' => 'Untuk Tasmi\' 5 Juz, rentang dari juz awal sampai juz akhir harus tepat 5 juz.',
                ]);
            }
        }
    }

    private function isDuplicateKeyException(\Illuminate\Database\QueryException $e): bool
    {
        // PostgreSQL unique violation (23505) atau SQLite integrity violation.
        return $e->getCode() === '23505' || str_contains((string) $e->getMessage(), 'Unique violation') || str_contains((string) $e->getMessage(), 'UNIQUE constraint failed');
    }
}