<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\ClassEnrollment;
use App\Models\ClassroomTerm;
use App\Models\User;
use App\Services\DiniyyahLedgerAccess;
use App\Services\TahfidzReportSources;
use App\Services\TahfidzReportWorkbook;
use App\Services\TahfidzReportWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TahfidzReportController extends Controller
{
    public function __construct(
        private readonly TahfidzReportWorkflow $workflow,
        private readonly TahfidzReportSources $sources,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'academic_term_id' => ['nullable', 'integer', 'min:1'],
            'classroom_term_id' => ['nullable', 'integer', 'min:1'],
            'enrollment_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $terms = AcademicTerm::with('academicYear')->orderByDesc('is_active')->orderByDesc('id')->get();
        $term = isset($filters['academic_term_id']) ? $terms->find($filters['academic_term_id']) : $terms->first();
        abort_if(isset($filters['academic_term_id']) && ! $term, 404);

        $classrooms = $term ? ClassroomTerm::whereIn('id', $this->workflow->enrollments($request->user(), $term->id)
            ->select('classroom_term_id'))->orderBy('name')->orderBy('id')->get() : collect();
        $classroom = $classrooms->firstWhere('id', $filters['classroom_term_id'] ?? null);
        abort_if(isset($filters['classroom_term_id']) && ! $classroom, 403);
        $enrollments = $classroom ? $this->workflow->enrollments($request->user(), $term->id)->where('classroom_term_id', $classroom->id)
            ->with('student')->orderBy('roll_number')->orderBy('student_id')->get() : collect();
        $selectedEnrollment = $enrollments->firstWhere('id', $filters['enrollment_id'] ?? null);
        abort_if(isset($filters['enrollment_id']) && ! $selectedEnrollment, 403);
        $classSources = $classroom ? $this->sources->load($classroom, $enrollments->pluck('student_id')) : null;
        $states = $classroom ? $enrollments->mapWithKeys(fn ($enrollment) => [
            $enrollment->id => $this->sources->resolve($classSources, $enrollment->student_id),
        ]) : collect();
        $values = $selectedEnrollment ? $this->sources->entryValues($classSources, $selectedEnrollment->student_id) : [];
        $locked = $classroom && $this->workflow->isLocked($classroom);

        return view('kabag.tahfidz.report', compact('terms', 'term', 'classrooms', 'classroom', 'enrollments', 'selectedEnrollment', 'states', 'values', 'locked'));
    }

    public function save(Request $request, ClassEnrollment $enrollment): RedirectResponse
    {
        $data = $request->validate(TahfidzReportWorkflow::valueRules() + [
            'version' => ['required', 'integer', 'min:0'],
            'action' => ['required', 'in:draft,final'],
        ]);
        $final = $data['action'] === 'final';
        $this->workflow->save($request->user(), $enrollment, $data, $final, (int) $data['version']);

        $message = $final ? 'Nilai final disimpan dan menunggu pemeriksaan wali kelas.' : 'Draf berhasil disimpan.';

        return to_route('kabag-tahfidz.report.index', [
            'academic_term_id' => $enrollment->academic_term_id, 'classroom_term_id' => $enrollment->classroom_term_id,
        ])->with('status', $enrollment->student->name.' · '.$message);
    }

    public function template(Request $request, AcademicTerm $term, TahfidzReportWorkbook $workbooks): StreamedResponse
    {
        $classId = $request->validate(['classroom_term_id' => ['nullable', 'integer', 'min:1']])['classroom_term_id'] ?? null;
        $classroom = $classId ? ClassroomTerm::where('academic_term_id', $term->id)->findOrFail($classId) : null;
        $enrollments = $this->workflow->enrollments($request->user(), $term->id)
            ->when($classroom, fn ($query) => $query->where('classroom_term_id', $classroom->id))
            ->with('student', 'classroomTerm')->orderBy('roll_number')->orderBy('student_id')->get()
            ->sortBy(fn ($enrollment) => $enrollment->classroomTerm->name, SORT_NATURAL | SORT_FLAG_CASE);
        $versions = $this->workflow->latestVersions($term->id, $enrollments->pluck('student_id'));
        $term->load('academicYear');
        $workbook = $workbooks->create($term, $classroom, $enrollments, $versions);
        $scope = $classroom ? Str::slug($classroom->name).'-'.$classroom->id : 'semua-kelas';
        $period = Str::slug($term->academicYear?->name.'-'.$term->name);

        return response()->streamDownload(function () use ($workbook) {
            try {
                (new Xlsx($workbook))->save('php://output');
            } finally {
                $workbook->disconnectWorksheets();
            }
        }, "nilai-tahfidz-{$period}-{$scope}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function import(Request $request, AcademicTerm $term, TahfidzReportWorkbook $workbooks): RedirectResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:5120']]);
        [$classId, $rows] = $workbooks->read($data['file']->getPathname(), $term->id);

        $counts = $this->workflow->import($request->user(), $term->id, $classId, $rows);

        return to_route('kabag-tahfidz.report.index', [
            'academic_term_id' => $term->id, 'classroom_term_id' => $classId ?: null,
        ])->with('status', "{$counts['final']} nilai final menunggu pemeriksaan wali kelas dan {$counts['draft']} draf disimpan.");
    }

    public function monitor(Request $request, ClassroomTerm $classroom, DiniyyahLedgerAccess $access): View
    {
        abort_unless($access->allows($request->user(), $classroom->id), 403);
        $classroom->load('academicTerm.academicYear');
        $enrollments = $classroom->enrollments()->where('status', 'active')->forVisibleStudents()
            ->with('student')->orderBy('roll_number')->orderBy('student_id')->get();
        $sources = $this->sources->load($classroom, $enrollments->pluck('student_id'));
        $states = $enrollments->mapWithKeys(fn ($enrollment) => [
            $enrollment->id => $this->sources->resolve($sources, $enrollment->student_id),
        ]);
        $users = User::with('teacher')->whereIn('id', $states->pluck('entry.input_by')->filter()->unique())->get()
            ->mapWithKeys(fn ($user) => [$user->id => $user->teacher?->name ?? $user->name]);
        $locked = $this->workflow->isLocked($classroom);
        $kabagStates = $states->filter(fn ($state) => $state['entry']?->input_role === 'kabag_tahfidz');
        $kabagComplete = $kabagStates->whereIn('status', ['final', 'reviewed'])->count();
        $kabagDrafts = $kabagStates->where('status', 'draft')->count();

        return view('guru.tahfidz-report.monitor', compact('classroom', 'enrollments', 'states', 'users', 'locked', 'kabagComplete', 'kabagDrafts'));
    }

    public function review(Request $request, ClassEnrollment $enrollment): RedirectResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $this->workflow->review($request->user(), $enrollment, (int) $data['version']);

        return back()->with('status', 'Nilai final diperiksa. Segarkan leger agar memakai versi yang sudah diperiksa.');
    }
}
