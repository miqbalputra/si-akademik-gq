<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahKkmRevision;
use App\Models\DiniyyahKkmScope;
use App\Models\DiniyyahSubject;
use App\Services\DiniyyahKkmAccess;
use App\Services\DiniyyahKkmAssignmentRoster;
use App\Services\DiniyyahKkmBulkAssignment;
use App\Services\DiniyyahKkmCalculator;
use App\Services\DiniyyahKkmDeletion;
use App\Services\DiniyyahKkmExportService;
use App\Services\DiniyyahKkmResolver;
use App\Services\DiniyyahKkmWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DiniyyahKkmController extends Controller
{
    public function __construct(private DiniyyahKkmAccess $access, private DiniyyahKkmResolver $resolver, private DiniyyahKkmAssignmentRoster $roster) {}

    private function prefix(Request $request): string
    {
        return $request->routeIs('admin.kkm.*') ? 'admin.kkm.' : ($request->routeIs('kabag.kkm.*') ? 'kabag.kkm.' : 'guru.kkm.');
    }

    /** @return array{Builder, Collection} */
    private function scopeQuery(Request $request, ?Collection $teachingScopes = null, bool $filterStatus = true): array
    {
        $filters = $request->validate(['academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'], 'mustawa' => ['nullable', 'integer', 'between:1,6'], 'status' => ['nullable', 'in:draft,submitted,needs_revision,approved,unassigned,school_fixed']]);
        $teacherPortal = $request->routeIs('guru.kkm.*');
        $writerIds = collect();
        $query = DiniyyahKkmScope::query()
            ->when($filters['academic_term_id'] ?? null, fn ($q, $id) => $q->where('academic_term_id', $id))
            ->when($filters['mustawa'] ?? null, fn ($q, $level) => $q->where('mustawa', $level));
        if ($teacherPortal) {
            $teachingScopes ??= $this->roster->teachingScopes($request->user());
            $this->roster->constrainToTeachingScopes($query, $teachingScopes);
            if ($teachingScopes->isNotEmpty()) {
                $writerIds = (clone $query)->where('assigned_teacher_id', $request->user()->teacher->id)
                    ->with(['subject', 'activeRevision'])->get()->filter($this->access->teacherManaged(...))->pluck('id');
            }
            $query->where(fn (Builder $q) => $q->whereIn('id', $writerIds)->orWhereNotNull('active_revision_id'));
        }

        $status = $filterStatus ? ($filters['status'] ?? null) : null;
        if ($status === 'unassigned') {
            $fixedSubjects = DiniyyahSubject::get(['id', 'code', 'name'])->filter(fn ($subject) => $subject->schoolKkm() !== null)->pluck('id');
            $query->whereNull('assigned_teacher_id')->whereNotIn('subject_id', $fixedSubjects)
                ->whereDoesntHave('activeRevision', fn ($q) => $q->where('status', 'school_fixed'));
        } elseif ($status) {
            $matches = fn (Builder $q) => $q->where('status', $status);
            $workingOrActive = fn (Builder $q) => $q->whereHas('workingRevision', $matches)
                ->orWhere(fn (Builder $q) => $q->whereNull('working_revision_id')->whereHas('activeRevision', $matches));
            $query->where(function (Builder $q) use ($teacherPortal, $writerIds, $workingOrActive, $matches): void {
                if ($teacherPortal) {
                    $q->where(fn (Builder $q) => $q->whereIn('id', $writerIds)->where($workingOrActive))
                        ->orWhere(fn (Builder $q) => $q->whereNotIn('id', $writerIds)->whereHas('activeRevision', $matches));
                } else {
                    $q->where($workingOrActive);
                }
            });
        }

        return [$query->with(['subject', 'academicTerm.academicYear', 'activeRevision', 'workingRevision', 'assignedTeacher'])
            ->orderByDesc('academic_term_id')->orderBy('mustawa')->orderBy('subject_id'), $writerIds->flip()];
    }

    public function index(Request $request)
    {
        $teacherPortal = $request->routeIs('guru.kkm.*');
        $teachingScopes = $teacherPortal ? $this->roster->teachingScopes($request->user()) : null;
        $manager = ! $teacherPortal && $this->access->manages($request->user());
        $terms = AcademicTerm::with('academicYear')->when($teacherPortal, fn ($q) => $q->whereIn('id', $teachingScopes->pluck('academic_term_id')))->latest('id')->get();
        $request->validate(['view' => ['nullable', 'in:status,assignments'], 'academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id']]);
        $rosterTerm = $terms->firstWhere('id', $request->integer('academic_term_id')) ?? $terms->firstWhere('is_active', true) ?? $terms->first();
        if ($manager) {
            // Status and assignment views share a single, explicit semester context.
            $request->merge(['academic_term_id' => $rosterTerm?->id]);
        }
        $activeView = $manager && ($request->input('view') === 'assignments' || $request->input('status') === 'unassigned') ? 'assignments' : 'status';
        [$query, $writers] = $this->scopeQuery($request, $teachingScopes);
        $scopes = $query->paginate(20)->withQueryString();
        $mustawaOptions = $teacherPortal
            ? $teachingScopes->when($request->integer('academic_term_id'), fn ($scopes, $termId) => $scopes->where('academic_term_id', $termId))->pluck('mustawa')->unique()->sort()->values()->all()
            : range(1, 6);
        $roster = $manager && $rosterTerm ? $this->roster->forTerm($rosterTerm, $request->integer('mustawa') ?: null) : ['rows' => [], 'issues' => []];
        $summary = [];
        if ($manager) {
            [$summaryQuery] = $this->scopeQuery($request, filterStatus: false);
            $statuses = $summaryQuery->get()->countBy(fn ($scope) => ($scope->workingRevision ?? $scope->activeRevision)?->status ?? 'not_started');
            $summary = [
                'submitted' => $statuses->get('submitted', 0),
                'needs_revision' => $statuses->get('needs_revision', 0),
                'approved' => $statuses->get('approved', 0),
                'unassigned' => collect($roster['rows'])->filter(fn ($row) => ! $row['assigned_teacher_id'] && ! $row['school_managed'])->count(),
            ];
        }
        if ($request->input('status') === 'unassigned') {
            $roster['rows'] = array_values(array_filter($roster['rows'], fn ($r) => ! $r['assigned_teacher_id'] && ! $r['school_managed']));
        }

        return view('kkm.index', ['scopes' => $scopes, 'terms' => $terms, 'mustawaOptions' => $mustawaOptions, 'teacherPortal' => $teacherPortal, 'manager' => $manager, 'prefix' => $this->prefix($request), 'roster' => $roster, 'rosterTerm' => $rosterTerm, 'writers' => $writers, 'activeView' => $activeView, 'summary' => $summary]);
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

        return redirect()->route($this->prefix($request).'index', ['academic_term_id' => $data['academic_term_id'], 'view' => 'assignments'])
            ->with('status', $result['assigned'].' penyusun KKM berhasil ditetapkan. '.$result['unchanged'].' tugas sudah sesuai dan tidak diubah.');
    }

    public function assign(Request $request, DiniyyahKkmWorkflow $workflow)
    {
        abort_unless($this->access->manages($request->user()), 403);
        $values = $request->validate(['class_subject_id' => ['required', 'integer', 'exists:diniyyah_class_subjects,id'], 'teacher_id' => ['required', 'integer', 'exists:teachers,id']]);
        $scope = $workflow->assign(DiniyyahClassSubject::findOrFail($values['class_subject_id']), (int) $values['teacher_id'], $request->user());

        return redirect()->route($this->prefix($request).'show', $scope)->with('status', 'Guru penyusun KKM berhasil ditetapkan.');
    }

    public function show(Request $request, DiniyyahKkmScope $scope, DiniyyahKkmDeletion $deletion)
    {
        $manager = ! $request->routeIs('guru.kkm.*') && $this->access->manages($request->user());
        $scope->load(['subject', 'academicTerm.academicYear', 'activeRevision', 'workingRevision', 'assignedTeacher']);
        abort_unless($this->access->views($request->user(), $scope, $manager), 403);
        $writer = ! $manager && $this->access->writes($request->user(), $scope);
        $reviewer = $manager && $this->access->reviews($request->user()) && $this->access->teacherManaged($scope);
        $revisions = $scope->revisions()->with(['submitter', 'reviewer'])->orderByDesc('version')
            ->when(! $writer && ! $manager, fn ($q) => $q->whereIn('status', DiniyyahKkmAccess::OFFICIAL_STATUSES))->get();
        $revision = $writer || $manager ? ($scope->workingRevision ?? $scope->activeRevision) : $scope->activeRevision;
        $canDelete = $request->routeIs('admin.kkm.*') && $this->access->deletes($request->user()) && $this->access->teacherManaged($scope);

        return view('kkm.show', ['scope' => $scope, 'revision' => $revision, 'revisions' => $revisions, 'writer' => $writer, 'manager' => $manager, 'reviewer' => $reviewer, 'prefix' => $this->prefix($request), 'frozen' => $this->resolver->frozen($scope), 'criteriaLabels' => DiniyyahKkmCalculator::CRITERIA, 'canDelete' => $canDelete, 'deleteToken' => $canDelete ? $deletion->token($scope, $revisions) : null]);
    }

    public function destroy(Request $request, DiniyyahKkmScope $scope, DiniyyahKkmDeletion $deletion)
    {
        abort_unless($this->access->deletes($request->user()), 403);
        $data = $request->validate(['confirmation_token' => ['required', 'string', 'size:64']]);
        $deletion->deleteScope($scope, $request->user(), $data['confirmation_token']);

        return redirect()->route('admin.kkm.show', $scope)->with('status', 'KKM, pengajuan, dan seluruh riwayat berhasil dihapus. Guru penyusun dapat memulai ulang.');
    }

    public function destroyRevision(Request $request, DiniyyahKkmRevision $revision, DiniyyahKkmDeletion $deletion)
    {
        abort_unless($this->access->deletes($request->user()), 403);
        $data = $request->validate(['confirmation_token' => ['required', 'string', 'size:64']]);
        $scopeId = $revision->diniyyah_kkm_scope_id;
        $deletion->deleteRevision($revision, $request->user(), $data['confirmation_token']);

        return redirect()->route('admin.kkm.show', $scopeId)->with('status', 'Versi KKM berhasil dihapus.');
    }

    public function start(Request $request, DiniyyahKkmScope $scope, DiniyyahKkmWorkflow $workflow)
    {
        $workflow->startRevision($scope, $request->user());

        return redirect()->route($this->prefix($request).'show', $scope)->with('status', 'Draf KKM siap diisi.');
    }

    public function save(Request $request, DiniyyahKkmRevision $revision, DiniyyahKkmWorkflow $workflow)
    {
        // Keep validation input tied to this version, including older open forms.
        $request->merge(['revision_id' => $revision->id]);
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
        abort_unless($this->access->viewsRevision($request->user(), $revision, ! $request->routeIs('guru.kkm.*')), 403);

        return $exports->download(collect([$revision]), $format);
    }

    public function recap(Request $request, string $format, DiniyyahKkmExportService $exports)
    {
        [$query, $writers] = $this->scopeQuery($request);
        $manager = ! $request->routeIs('guru.kkm.*');
        $revisions = $query->get()->map(function ($scope) use ($manager, $writers) {
            return $manager || $writers->has($scope->id)
                ? ($scope->workingRevision ?? $scope->activeRevision) : $scope->activeRevision;
        })->filter()->values();

        return $exports->download($revisions, $format);
    }
}
