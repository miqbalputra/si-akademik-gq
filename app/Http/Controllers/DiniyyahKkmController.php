<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahKkmRevision;
use App\Models\DiniyyahKkmScope;
use App\Services\DiniyyahKkmAccess;
use App\Services\DiniyyahKkmAssignmentRoster;
use App\Services\DiniyyahKkmBulkAssignment;
use App\Services\DiniyyahKkmCalculator;
use App\Services\DiniyyahKkmExportService;
use App\Services\DiniyyahKkmResolver;
use App\Services\DiniyyahKkmWorkflow;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DiniyyahKkmController extends Controller
{
    public function __construct(private DiniyyahKkmAccess $access, private DiniyyahKkmResolver $resolver, private DiniyyahKkmAssignmentRoster $roster) {}

    private function prefix(Request $request): string
    {
        return $request->routeIs('admin.kkm.*') ? 'admin.kkm.' : ($request->routeIs('kabag.kkm.*') ? 'kabag.kkm.' : 'guru.kkm.');
    }

    private function scopes(Request $request, ?Collection $teachingScopes = null)
    {
        $filters = $request->validate(['academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'], 'mustawa' => ['nullable', 'integer', 'between:1,6'], 'status' => ['nullable', 'in:draft,submitted,needs_revision,approved,unassigned,school_fixed']]);
        if ($request->routeIs('guru.kkm.*')) {
            $teachingScopes ??= $this->roster->teachingScopes($request->user());
        }

        return DiniyyahKkmScope::with(['subject', 'academicTerm.academicYear', 'activeRevision', 'workingRevision', 'assignedTeacher'])
            ->when($teachingScopes !== null, function ($query) use ($teachingScopes) {
                if ($teachingScopes->isEmpty()) {
                    $query->whereIn('id', []);
                } else {
                    $query->where(function ($query) use ($teachingScopes) {
                        foreach ($teachingScopes as $scope) {
                            $query->orWhere(fn ($query) => $query->where($scope));
                        }
                    });
                }
            })
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
                    return ! $scope->assigned_teacher_id && ! $scope->activeRevision?->is_school_fixed && $scope->subject?->schoolKkm() === null;
                }
                // Do not expose a pending revision's existence to read-only teachers.
                $visible = $this->access->manages($request->user()) || $this->access->writes($request->user(), $scope);

                return ($visible ? ($scope->workingRevision ?? $scope->activeRevision) : $scope->activeRevision)?->status === $status;
            })->values();
    }

    public function index(Request $request)
    {
        $teacherPortal = $request->routeIs('guru.kkm.*');
        $teachingScopes = $teacherPortal ? $this->roster->teachingScopes($request->user()) : null;
        $all = $this->scopes($request, $teachingScopes);
        $scopes = new LengthAwarePaginator($all->forPage(max(1, (int) $request->input('page', 1)), 20)->values(), $all->count(), 20, max(1, (int) $request->input('page', 1)), ['path' => $request->url(), 'query' => $request->query()]);
        $manager = ! $teacherPortal && $this->access->manages($request->user());
        $terms = AcademicTerm::with('academicYear')->when($teacherPortal, fn ($q) => $q->whereIn('id', $teachingScopes->pluck('academic_term_id')))->latest('id')->get();
        $mustawaOptions = $teacherPortal
            ? $teachingScopes->when($request->integer('academic_term_id'), fn ($scopes, $termId) => $scopes->where('academic_term_id', $termId))->pluck('mustawa')->unique()->sort()->values()->all()
            : range(1, 6);
        $rosterTerm = $terms->firstWhere('id', $request->integer('academic_term_id')) ?? $terms->firstWhere('is_active', true) ?? $terms->first();
        $roster = $manager && $rosterTerm ? $this->roster->forTerm($rosterTerm, $request->integer('mustawa') ?: null) : ['rows' => [], 'issues' => []];
        if ($request->input('status') === 'unassigned') {
            $roster['rows'] = array_values(array_filter($roster['rows'], fn ($r) => ! $r['assigned_teacher_id'] && ! $r['school_managed']));
        }

        return view('kkm.index', ['scopes' => $scopes, 'terms' => $terms, 'mustawaOptions' => $mustawaOptions, 'teacherPortal' => $teacherPortal, 'manager' => $manager, 'prefix' => $this->prefix($request), 'roster' => $roster, 'rosterTerm' => $rosterTerm, 'access' => $this->access]);
    }

    public function bulkPreview(Request $request, DiniyyahKkmBulkAssignment $bulk)
    {
        abort_unless($this->access->manages($request->user()), 403);
        $filters = $request->validate([
            'academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
            'mustawa' => ['nullable', 'integer', 'between:1,6'],
            'subject_id' => ['nullable', 'integer', 'exists:diniyyah_subjects,id'],
            'gender' => ['nullable', 'in:male,female'],
        ]);
        $terms = AcademicTerm::with('academicYear')->latest('id')->get();
        $term = $terms->firstWhere('id', (int) ($filters['academic_term_id'] ?? 0)) ?? $terms->firstWhere('is_active', true) ?? $terms->first();
        $roster = $term ? $this->roster->forTerm($term) : ['rows' => [], 'issues' => []];
        $subjects = collect($roster['rows'])->unique('subject_id')->sortBy('subject')->values();
        $roster['rows'] = array_values(array_filter($roster['rows'], fn ($r) => (empty($filters['mustawa']) || $r['mustawa'] === (int) $filters['mustawa'])
            && (empty($filters['subject_id']) || $r['subject_id'] === (int) $filters['subject_id'])
        ));

        return view('kkm.bulk', [
            'terms' => $terms, 'term' => $term, 'subjects' => $subjects, 'roster' => $roster,
            'gender' => $filters['gender'] ?? 'female', 'prefix' => $this->prefix($request),
            'previewToken' => $term ? $bulk->previewToken($term, $roster['rows'], $request->user()) : '',
        ]);
    }

    public function bulkApply(Request $request, DiniyyahKkmBulkAssignment $bulk)
    {
        abort_unless($this->access->manages($request->user()), 403);
        $data = $request->validate([
            'academic_term_id' => ['required', 'integer', 'exists:academic_terms,id'], 'preview_token' => ['required', 'string'],
            'selected' => ['required', 'array', 'min:1', 'max:500'], 'selected.*' => ['required', 'integer', 'distinct'],
            'teachers' => ['required', 'array'], 'teachers.*' => ['nullable', 'integer'],
            'genders' => ['required', 'array'], 'genders.*' => ['nullable', 'in:male,female'],
        ], [
            'selected.required' => 'Pilih setidaknya satu mapel–mustawa yang akan ditetapkan.',
            'selected.min' => 'Pilih setidaknya satu mapel–mustawa yang akan ditetapkan.',
            'preview_token.required' => 'Tampilkan pratinjau penugasan sebelum menerapkan pilihan.',
            'teachers.required' => 'Pilih guru penyusun untuk baris yang disertakan.',
            'teachers.*.integer' => 'Pilihan guru penyusun tidak valid.',
            'genders.required' => 'Pilih gender guru untuk baris yang disertakan.',
        ]);
        $entries = array_map(fn ($id) => [
            'class_subject_id' => (int) $id, 'teacher_id' => (int) ($data['teachers'][$id] ?? 0), 'gender' => $data['genders'][$id] ?? null,
        ], $data['selected']);
        $result = $bulk->apply((int) $data['academic_term_id'], $entries, $data['preview_token'], $request->user());

        return redirect()->route($this->prefix($request).'index', ['academic_term_id' => $data['academic_term_id']])
            ->with('status', $result['assigned'].' penyusun KKM berhasil ditetapkan. '.$result['unchanged'].' tugas sudah sesuai dan tidak diubah.');
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
