<?php

namespace App\Services;

use App\Models\DiniyyahAssessmentResult;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahKkmRevision;
use App\Models\DiniyyahKkmScope;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\PanelNotification;
use App\Models\ReportCardLine;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiniyyahKkmDeletion
{
    public function __construct(private DiniyyahKkmAccess $access, private DiniyyahKkmResolver $resolver) {}

    /** Fingerprint the exact scope and versions displayed on the confirmation form. */
    public function token(DiniyyahKkmScope $scope, Collection $revisions): string
    {
        return hash('sha256', json_encode([
            $scope->getAttributes(),
            $revisions->sortBy('id')->map(fn ($revision) => $revision->getAttributes())->values()->all(),
        ], JSON_THROW_ON_ERROR));
    }

    public function deleteScope(DiniyyahKkmScope $scope, User $actor, string $token): void
    {
        $this->delete($scope, $actor, $token);
    }

    public function deleteRevision(DiniyyahKkmRevision $revision, User $actor, string $token): void
    {
        $this->delete($revision->scope, $actor, $token, $revision->id);
    }

    private function delete(DiniyyahKkmScope $scope, User $actor, string $token, ?int $revisionId = null): void
    {
        abort_unless($this->access->deletes($actor), 403);
        DB::transaction(function () use ($scope, $actor, $token, $revisionId): void {
            $scope = DiniyyahKkmScope::whereKey($scope->id)->lockForUpdate()->firstOrFail();
            $revisions = $scope->revisions()->orderBy('id')->lockForUpdate()->get();
            if (! hash_equals($this->token($scope, $revisions), $token)) {
                $this->invalid('Data KKM telah berubah. Muat ulang halaman sebelum menghapus.');
            }
            $this->resolver->ensureOpen($scope);
            $selected = $revisionId === null ? $revisions : $revisions->where('id', $revisionId)->values();
            if ($selected->isEmpty()) {
                $this->invalid('Tidak ada pengajuan atau riwayat KKM yang dapat dihapus.');
            }
            if (! $this->access->teacherManaged($scope) || $selected->contains(fn ($revision) => $revision->is_school_fixed)) {
                $this->invalid('KKM ketetapan sekolah tidak dapat dihapus melalui pembersihan pengajuan guru.');
            }
            $ids = $selected->pluck('id')->all();
            if (ReportCardLine::whereIn('kkm_revision_id', $ids)->whereHas('reportCard', fn ($q) => $q->where('status', '!=', 'draft'))->exists()) {
                $this->invalid('Versi KKM masih digunakan rapor terkunci atau terbit dan tidak dapat dihapus.');
            }
            $activeDeleted = in_array($scope->active_revision_id, $ids, true);
            $workingDeleted = in_array($scope->working_revision_id, $ids, true);
            activity('kkm')->performedOn($scope)->causedBy($actor)->event($revisionId === null ? 'history_deleted' : 'revision_deleted')
                ->withProperties(['scope_before' => $scope->getAttributes(), 'deleted_revisions' => $selected->map(fn ($revision) => $revision->getAttributes())->values()->all()])
                ->log($revisionId === null ? 'Admin menghapus KKM dan seluruh pengajuan/riwayat' : 'Admin menghapus satu versi KKM');
            $scope->update([
                'active_revision_id' => $activeDeleted ? null : $scope->active_revision_id,
                'working_revision_id' => $workingDeleted ? null : $scope->working_revision_id,
            ]);
            $this->clearReferences($scope, $ids, $activeDeleted);
            $scope->revisions()->whereIn('id', $ids)->delete();

            $types = [];
            if ($revisionId === null || $activeDeleted) {
                $types[] = 'kkm_approved';
            }
            if ($revisionId === null || $workingDeleted) {
                array_push($types, 'kkm_submitted', 'kkm_needs_revision');
            }
            $links = array_map(fn ($prefix) => route($prefix.'show', $scope), ['admin.kkm.', 'kabag.kkm.', 'guru.kkm.']);
            PanelNotification::whereIn('link_url', $links)->whereIn('notification_type', $types)->whereNull('archived_at')
                ->update(['status' => 'read', 'read_at' => now(), 'archived_at' => now()]);
        }, 3);
    }

    private function clearReferences(DiniyyahKkmScope $scope, array $ids, bool $activeDeleted): void
    {
        $subjects = $this->resolver->classSubjects($scope, true)->get(['id', 'classroom_term_id']);
        $subjectIds = $subjects->pluck('id');
        $values = ['kkm' => null, 'kkm_revision_id' => null];
        $subjectQuery = DiniyyahClassSubject::withTrashed()->where(function ($q) use ($ids, $activeDeleted, $subjectIds): void {
            $q->whereIn('kkm_revision_id', $ids);
            if ($activeDeleted) {
                $q->orWhereIn('id', $subjectIds);
            }
        });
        $subjectQuery->update($values);

        $sets = DiniyyahAssessmentSet::withTrashed()->where(function ($q) use ($ids, $activeDeleted, $subjectIds): void {
            $q->whereIn('kkm_revision_id', $ids);
            if ($activeDeleted) {
                $q->orWhereIn('diniyyah_class_subject_id', $subjectIds);
            }
        });
        $setIds = (clone $sets)->pluck('id');
        (clone $sets)->where('status', 'validated')->update(['status' => 'submitted']);
        $sets->update($values);
        DiniyyahAssessmentResult::where(fn ($q) => $q->whereIn('kkm_revision_id', $ids)->orWhereIn('diniyyah_assessment_set_id', $setIds))
            ->update($values + ['is_passed' => false]);
        ReportCardLine::whereIn('kkm_revision_id', $ids)->whereHas('reportCard', fn ($q) => $q->where('status', 'draft'))
            ->update($values + ['is_passed' => false]);

        foreach (DiniyyahLedgerSnapshot::whereIn('classroom_term_id', $subjects->pluck('classroom_term_id'))->whereNotIn('status', ['locked', 'published'])->get() as $snapshot) {
            $data = $snapshot->snapshot_data ?? [];
            $affected = $activeDeleted;
            foreach ($data['columns'] ?? [] as $key => $column) {
                if (in_array((int) ($column['kkm_revision_id'] ?? 0), $ids, true) || ($activeDeleted && $setIds->contains($column['assessment_set_id'] ?? null))) {
                    $data['columns'][$key]['kkm'] = null;
                    $data['columns'][$key]['kkm_revision_id'] = null;
                    $affected = true;
                }
            }
            if (! $affected) {
                continue;
            }
            $data['kkm_stale'] = true;
            $data['issues'][] = ['level' => 'blocking', 'code' => 'kkm_deleted', 'message' => 'KKM dihapus oleh admin. Susun dan setujui KKM, lalu buat ulang leger.'];
            $data['summary']['blocking_issues'] = ($data['summary']['blocking_issues'] ?? 0) + 1;
            $snapshot->update(['status' => 'draft', 'snapshot_data' => $data, 'validated_at' => null, 'validated_by' => null]);
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['kkm' => $message]);
    }
}
