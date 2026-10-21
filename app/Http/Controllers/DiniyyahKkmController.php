<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahKkmRevision;
use App\Models\DiniyyahKkmScope;
use App\Services\DiniyyahKkmAccess;
use App\Services\DiniyyahKkmCalculator;
use App\Services\DiniyyahKkmExportService;
use App\Services\DiniyyahKkmResolver;
use App\Services\DiniyyahKkmWorkflow;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class DiniyyahKkmController extends Controller
{
    public function __construct(private DiniyyahKkmAccess $access, private DiniyyahKkmResolver $resolver) {}

    private function prefix(Request $request): string
    {
        return $request->routeIs('admin.kkm.*') ? 'admin.kkm.' : ($request->routeIs('kabag.kkm.*') ? 'kabag.kkm.' : 'guru.kkm.');
    }

    private function scopes(Request $request)
    {
        $filters = $request->validate(['academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'], 'mustawa' => ['nullable', 'integer', 'between:1,6'], 'status' => ['nullable', 'in:draft,submitted,needs_revision,approved,unassigned']]);

        return DiniyyahKkmScope::with(['subject', 'academicTerm.academicYear', 'activeRevision', 'workingRevision', 'assignedTeacher'])
            ->when($filters['academic_term_id'] ?? null, fn ($q, $id) => $q->where('academic_term_id', $id))
            ->when($filters['mustawa'] ?? null, fn ($q, $level) => $q->where('mustawa', $level))
            ->orderByDesc('academic_term_id')->orderBy('mustawa')->orderBy('subject_id')->get()
            ->filter(fn ($scope) => $this->access->views($request->user(), $scope))
            ->filter(function ($scope) use ($filters, $request) {
                $status = $filters['status'] ?? null;
                if (! $status) {
                    return true;
                }
                if ($status === 'unassigned') {
                    return ! $scope->assigned_teacher_id;
                }
                // Do not expose a pending revision's existence to read-only teachers.
                $visible = $this->access->manages($request->user()) || $this->access->writes($request->user(), $scope);

                return ($visible ? ($scope->workingRevision ?? $scope->activeRevision) : $scope->activeRevision)?->status === $status;
            })->values();
    }

    public function index(Request $request)
    {
        $all = $this->scopes($request);
        $scopes = new LengthAwarePaginator($all->forPage(max(1, (int) $request->input('page', 1)), 20)->values(), $all->count(), 20, max(1, (int) $request->input('page', 1)), ['path' => $request->url(), 'query' => $request->query()]);
        $manager = $this->access->manages($request->user());
        $options = [];
        if ($manager) {
            foreach (DiniyyahClassSubject::with(['subject', 'classroomTerm.classroom', 'classroomTerm.academicTerm.academicYear'])->where('is_active', true)->get() as $subject) {
                $mustawa = DiniyyahKkmResolver::mustawa($subject->classroomTerm?->classroom?->level_name);
                if (! $mustawa) {
                    continue;
                }
                $scope = new DiniyyahKkmScope(['academic_term_id' => $subject->classroomTerm->academic_term_id, 'subject_id' => $subject->subject_id, 'mustawa' => $mustawa]);
                $key = $scope->academic_term_id.':'.$scope->subject_id.':'.$mustawa;
                if (isset($options[$key])) {
                    continue;
                }
                $options[$key] = ['id' => $subject->id, 'label' => $scope->label, 'teachers' => $this->access->eligibleTeachers($scope)->orderBy('name')->get(['id', 'name'])->toArray()];
            }
        }

        return view('kkm.index', ['scopes' => $scopes, 'terms' => AcademicTerm::with('academicYear')->latest('id')->get(), 'manager' => $manager, 'prefix' => $this->prefix($request), 'assignmentOptions' => array_values($options), 'access' => $this->access]);
    }

    public function assign(Request $request, DiniyyahKkmWorkflow $workflow)
    {
        abort_unless($this->access->manages($request->user()), 403);
        $values = $request->validate(['class_subject_id' => ['required', 'integer', 'exists:diniyyah_class_subjects,id'], 'teacher_id' => ['required', 'integer', 'exists:teachers,id']]);
        $scope = $workflow->assign(DiniyyahClassSubject::findOrFail($values['class_subject_id']), (int) $values['teacher_id'], $request->user());

        return redirect()->route($this->prefix($request).'show', $scope)->with('status', 'Guru penyusun KKM berhasil ditetapkan.');
    }

    public function show(Request $request, DiniyyahKkmScope $scope)
    {
        abort_unless($this->access->views($request->user(), $scope), 403);
        $scope->load(['subject', 'academicTerm.academicYear', 'activeRevision', 'workingRevision', 'assignedTeacher']);
        $writer = $this->access->writes($request->user(), $scope);
        $manager = $this->access->manages($request->user());
        $revisions = $scope->revisions()->with(['submitter', 'reviewer'])->orderByDesc('version')->get()->filter(fn ($revision) => $this->access->viewsRevision($request->user(), $revision));
        $revision = $writer || $manager ? ($scope->workingRevision ?? $scope->activeRevision) : $scope->activeRevision;

        return view('kkm.show', ['scope' => $scope, 'revision' => $revision, 'revisions' => $revisions, 'writer' => $writer, 'manager' => $manager, 'prefix' => $this->prefix($request), 'frozen' => $this->resolver->frozen($scope), 'criteriaLabels' => DiniyyahKkmCalculator::CRITERIA]);
    }

    public function start(Request $request, DiniyyahKkmScope $scope, DiniyyahKkmWorkflow $workflow)
    {
        $workflow->startRevision($scope, $request->user());

        return redirect()->route($this->prefix($request).'show', $scope)->with('status', 'Draf KKM siap diisi.');
    }

    public function save(Request $request, DiniyyahKkmRevision $revision, DiniyyahKkmWorkflow $workflow)
    {
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:0'], 'action' => ['required', 'in:save,submit'], 'criteria' => ['nullable', 'array']]);
        $workflow->save($revision, $data['criteria'] ?? [], $request->user(), (int) $data['lock_version'], $data['action'] === 'submit');

        return redirect()->route($this->prefix($request).'show', $revision->scope)->with('status', $data['action'] === 'submit' ? 'KKM diajukan untuk ACC.' : 'Draf disimpan.');
    }

    public function review(Request $request, DiniyyahKkmRevision $revision, DiniyyahKkmWorkflow $workflow)
    {
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:0'], 'decision' => ['required', 'in:approve,revise'], 'notes' => ['nullable', 'string', 'max:4000']]);
        $workflow->review($revision, $request->user(), (int) $data['lock_version'], $data['decision'] === 'approve', $data['notes'] ?? null);

        return redirect()->route($this->prefix($request).'show', $revision->scope)->with('status', 'Keputusan tersimpan.');
    }

    public function export(Request $request, DiniyyahKkmRevision $revision, string $format, DiniyyahKkmExportService $exports)
    {
        abort_unless($this->access->viewsRevision($request->user(), $revision), 403);

        return $exports->download(collect([$revision]), $format);
    }

    public function recap(Request $request, string $format, DiniyyahKkmExportService $exports)
    {
        $revisions = $this->scopes($request)->map(function ($scope) use ($request) {
            return $this->access->manages($request->user()) || $this->access->writes($request->user(), $scope)
                ? ($scope->workingRevision ?? $scope->activeRevision) : $scope->activeRevision;
        })->filter()->values();

        return $exports->download($revisions, $format);
    }
}
