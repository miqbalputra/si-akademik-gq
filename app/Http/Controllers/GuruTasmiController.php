<?php

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
use App\Models\ClassroomTerm;
use App\Models\Student;
use App\Models\TasmiExaminerAssignment;
use App\Models\TasmiRecord;
use App\Models\Teacher;
use App\Services\TasmiReportDownloadService;
use App\Services\TasmiReportService;
use App\Services\TasmiService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class GuruTasmiController extends Controller
{
    public function __construct(
        private readonly TasmiService $tasmiService,
        private readonly TasmiReportService $reportService,
        private readonly TasmiReportDownloadService $downloadService,
    ) {}

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

        // Pemilihan kelas dilakukan lewat GET agar dropdown tidak pernah
        // mengirim form penyimpanan Tasmi' yang belum lengkap. Fallback old()
        // mempertahankan kelas dan daftar santri bila validasi simpan gagal.
        $classroomTermId = $request->query('classroom_term_id', $request->old('classroom_term_id'));
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

        $studentId = $request->query('student_id', $request->old('student_id'));

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
        $teacher = $this->reporterTeacher($request);
        $filters = $this->validatedReportFilters($request);
        $baseQuery = $this->reportService->forExaminer($teacher);
        $options = $this->reportService->options($baseQuery);
        $report = $this->reportService->paginate($this->reportService->applyFilters($baseQuery, $filters));

        return view('guru.tasmi.report', [
            'report' => $report,
            'options' => $options,
            'filters' => $filters,
            'scope' => 'examiner',
            'pageTitle' => "Riwayat & Laporan Tasmi' Saya",
            'pageDescription' => 'Seluruh riwayat hasil Tasmi\' yang Anda input, dari semua semester.',
            'backUrl' => route('guru.tasmi.index'),
            'backLabel' => "Dashboard Tasmi'",
            'exportRoute' => 'guru.tasmi.export',
            'resetRoute' => 'guru.tasmi.records',
            'canEdit' => true,
        ]);
    }

    public function export(Request $request, string $format)
    {
        $teacher = $this->reporterTeacher($request);
        $filters = $this->validatedReportFilters($request);
        $baseQuery = $this->reportService->forExaminer($teacher);
        $options = $this->reportService->options($baseQuery);
        $report = $this->reportService->exportReport(
            $this->reportService->applyFilters($baseQuery, $filters),
            $filters,
            $options,
        );
        $report['filter_labels']['PJ Tasmi\''] = $teacher->name;

        return $this->downloadService->download($report, $format, "Laporan Tasmi' - {$teacher->name}", 'examiner');
    }

    public function edit(Request $request, TasmiRecord $tasmi_record): View
    {
        $user = $request->user();
        $teacher = $user->teacher;
        abort_unless($teacher, 403);
        abort_unless($user->isTasmiExaminer(), 403);

        $isOwner = $tasmi_record->examiner_teacher_id === $teacher->id;
        abort_unless($isOwner, 403, 'Anda hanya bisa mengedit record tasmi\' yang Anda input sendiri.');

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
        abort_unless($isOwner, 403, 'Anda hanya bisa mengedit record tasmi\' yang Anda input sendiri.');

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
        abort_unless($isOwner, 403, 'Anda hanya bisa menghapus record tasmi\' yang Anda input sendiri.');

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
        ], [
            'classroom_term_id.required' => 'Pilih kelas terlebih dahulu.',
            'student_id.required' => 'Pilih nama santri terlebih dahulu.',
            'exam_type.required' => 'Pilih jenis ujian Tasmi\'.',
            'juz_start.required' => 'Pilih juz awal.',
            'juz_end.required' => 'Pilih juz akhir.',
            'exam_date.required' => 'Pilih tanggal ujian.',
            'predicate.required' => 'Pilih predikat hasil Tasmi\'.',
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

    private function reporterTeacher(Request $request): Teacher
    {
        $user = $request->user();
        $teacher = $user?->teacher;
        abort_unless($user?->hasRole('guru') && $teacher, 403, 'Akun Anda belum terhubung dengan data Guru.');

        $hasTasmiHistory = TasmiRecord::query()->where('examiner_teacher_id', $teacher->id)->exists()
            || TasmiExaminerAssignment::query()->where('teacher_id', $teacher->id)->exists();
        abort_unless($hasTasmiHistory, 403, 'Anda tidak memiliki riwayat penugasan PJ Tasmi\'.');

        return $teacher;
    }

    /** @return array<string, mixed> */
    private function validatedReportFilters(Request $request): array
    {
        return $request->validate([
            'academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
            'classroom_term_id' => ['nullable', 'integer', 'exists:classroom_terms,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'exam_type' => ['nullable', Rule::in(array_keys(TasmiRecord::examTypeOptions()))],
            'juz' => ['nullable', 'integer', 'min:1', 'max:30'],
            'predicate' => ['nullable', Rule::in(array_keys(TasmiRecord::predicateOptions()))],
            'date_from' => ['nullable', 'date'],
            'date_until' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);
    }
}
