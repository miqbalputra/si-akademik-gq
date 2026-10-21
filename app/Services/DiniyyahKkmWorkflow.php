<?php

namespace App\Services;

use App\Models\DiniyyahAssessmentResult;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahKkmRevision;
use App\Models\DiniyyahKkmScope;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\ReportCard;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiniyyahKkmWorkflow
{
    public function __construct(private DiniyyahKkmResolver $resolver, private DiniyyahKkmAccess $access, private DiniyyahKkmCalculator $calculator, private NotificationDispatcher $notifications) {}

    public function assign(DiniyyahClassSubject $subject, int $teacherId, User $actor): DiniyyahKkmScope
    {
        abort_unless($this->access->manages($actor), 403);

        return DB::transaction(function () use ($subject, $teacherId, $actor) {
            $term = $subject->classroomTerm->academicTerm()->lockForUpdate()->firstOrFail();
            $scope = $this->resolver->scopeFor($subject, true);
            if (! $scope) {
                $this->invalid('Jenjang kelas harus diisi Mustawa 1–6 atau M1–M6.');
            }
            $scope = DiniyyahKkmScope::whereKey($scope->id)->lockForUpdate()->firstOrFail();
            $this->resolver->ensureOpen($scope);
            if (! $this->access->eligibleTeachers($scope)->whereKey($teacherId)->exists()) {
                $this->invalid('Pilih guru aktif yang memiliki akun guru dan penugasan mengajar mapel/mustawa pada semester ini.');
            }
            if (! $term->kkm_workflow_enabled) {
                if (DiniyyahLedgerSnapshot::where('academic_term_id', $term->id)->whereIn('status', ['locked', 'published'])->exists()
                    || ReportCard::where('academic_term_id', $term->id)->whereIn('status', ['locked', 'published'])->exists()) {
                    $this->invalid('Semester historis memiliki dokumen terkunci. Gunakan semester yang masih terbuka.');
                }
                $term->forceFill(['kkm_workflow_enabled' => true])->save();
                $allIds = DiniyyahClassSubject::whereHas('classroomTerm', fn ($q) => $q->where('academic_term_id', $term->id))->pluck('id');
                DiniyyahClassSubject::whereIn('id', $allIds)->update(['kkm' => null, 'kkm_revision_id' => null]);
                $setIds = DiniyyahAssessmentSet::whereIn('diniyyah_class_subject_id', $allIds)->pluck('id');
                DiniyyahAssessmentSet::whereIn('id', $setIds)->update(['kkm' => null, 'kkm_revision_id' => null]);
                DiniyyahAssessmentResult::whereIn('diniyyah_assessment_set_id', $setIds)->update(['kkm' => null, 'kkm_revision_id' => null, 'is_passed' => false]);
            }
            $oldTeacher = $scope->assigned_teacher_id;
            if ($oldTeacher === $teacherId) {
                return $scope;
            }
            $scope->assigned_teacher_id = $teacherId;
            if ($scope->workingRevision) {
                $scope->workingRevision->update(['status' => 'draft', 'submitted_by' => null, 'submitted_at' => null, 'reviewed_by' => null, 'reviewed_at' => null, 'review_notes' => null, 'lock_version' => $scope->workingRevision->lock_version + 1]);
            }
            $scope->save();
            $this->audit($scope, $actor, 'assigned', ['old_teacher_id' => $oldTeacher, 'teacher_id' => $teacherId]);
            $this->notify($scope, [$scope->assignedTeacher->user_id], 'Penugasan penyusun KKM', 'Anda ditugaskan menyusun KKM '.$scope->label.'.', 'kkm_assigned');
            if ($oldTeacher) {
                $oldUserId = Teacher::find($oldTeacher)?->user_id;
                $this->notify($scope, [$oldUserId], 'Penugasan KKM dialihkan', 'Penugasan penyusun KKM '.$scope->label.' telah dialihkan.', 'kkm_assigned');
            }

            return $scope->fresh();
        }, 3);
    }

    public function startRevision(DiniyyahKkmScope $scope, User $actor): DiniyyahKkmRevision
    {
        return $this->locked($scope, function ($scope) use ($actor) {
            abort_unless($this->access->writes($actor, $scope), 403);
            if ($scope->working_revision_id) {
                $this->invalid('Masih ada versi berjalan. Buka versi tersebut.');
            }
            $revision = $scope->revisions()->create([
                'version' => (int) $scope->revisions()->max('version') + 1,
            ] + $this->calculator->calculate($scope->activeRevision?->criteria ?? []));
            $scope->update(['working_revision_id' => $revision->id]);
            $this->audit($scope, $actor, 'draft_created', ['revision_id' => $revision->id]);

            return $revision;
        });
    }

    public function save(DiniyyahKkmRevision $revision, array $input, User $actor, int $expectedVersion, bool $submit = false): DiniyyahKkmRevision
    {
        return $this->locked($revision->scope, function ($scope) use ($revision, $input, $actor, $expectedVersion, $submit) {
            abort_unless($this->access->writes($actor, $scope), 403);
            $revision = $this->current($scope, $revision, $expectedVersion);
            if (! in_array($revision->status, ['draft', 'needs_revision'], true)) {
                $this->invalid('Pengajuan sedang ditinjau dan tidak dapat diubah.');
            }
            $before = $revision->toArray();
            $payload = $this->calculator->calculate($input, $submit);
            $revision->fill($payload);
            $revision->lock_version++;
            if ($submit) {
                $revision->fill(['status' => 'submitted', 'submitted_by' => $actor->id, 'submitted_at' => now(), 'reviewed_by' => null, 'reviewed_at' => null, 'review_notes' => null]);
            }
            $revision->save();
            $this->audit($scope, $actor, $submit ? 'submitted' : 'draft_saved', ['before' => $before, 'after' => $revision->toArray()]);
            if ($submit) {
                $reviewers = User::role(['admin', 'kabag_diniyyah'])->whereKeyNot($actor->id)->pluck('id')->all();
                $this->notify($scope, $reviewers, 'KKM menunggu ACC', $scope->label.' versi '.$revision->version.' menunggu persetujuan.', 'kkm_submitted');
            }

            return $revision;
        });
    }

    public function review(DiniyyahKkmRevision $revision, User $actor, int $expectedVersion, bool $approve, ?string $notes = null): void
    {
        abort_unless($this->access->manages($actor), 403);
        $this->locked($revision->scope, function ($scope) use ($revision, $actor, $expectedVersion, $approve, $notes): void {
            $revision = $this->current($scope, $revision, $expectedVersion);
            if ($revision->status !== 'submitted') {
                $this->invalid('Hanya pengajuan menunggu ACC yang dapat ditinjau.');
            }
            if ($revision->submitted_by === $actor->id || $scope->assignedTeacher?->user_id === $actor->id) {
                $this->invalid('Pengaju tidak boleh menyetujui atau meninjau pengajuannya sendiri.');
            }
            if (! $approve && trim($notes ?? '') === '') {
                $this->invalid('Alasan permintaan revisi wajib diisi.');
            }
            if ($approve && ! $this->access->eligibleTeachers($scope)->whereKey($scope->assigned_teacher_id)->exists()) {
                $this->invalid('Guru penyusun tidak lagi memenuhi penugasan. Tetapkan ulang penyusun.');
            }
            $payload = $this->calculator->calculate($revision->criteria ?? [], $approve);
            $revision->fill($payload + ['status' => $approve ? 'approved' : 'needs_revision', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_notes' => $notes, 'lock_version' => $revision->lock_version + 1])->save();
            if ($approve) {
                if ($scope->activeRevision) {
                    $scope->activeRevision->update(['status' => 'superseded']);
                }
                $scope->update(['active_revision_id' => $revision->id, 'working_revision_id' => null]);
                $this->synchronize($scope, $revision);
            }
            $this->audit($scope, $actor, $approve ? 'approved' : 'revision_requested', ['revision' => $revision->toArray()]);
            $teachers = $approve ? $this->access->eligibleTeachers($scope)->pluck('user_id')->all() : [$scope->assignedTeacher?->user_id];
            $this->notify($scope, $teachers, $approve ? 'KKM telah disetujui' : 'KKM perlu revisi', $scope->label.' versi '.$revision->version.'. '.($notes ?? ''), $approve ? 'kkm_approved' : 'kkm_needs_revision');
        });
    }

    private function synchronize(DiniyyahKkmScope $scope, DiniyyahKkmRevision $revision): void
    {
        $subjects = $this->resolver->classSubjects($scope);
        $ids = (clone $subjects)->pluck('id');
        $classes = (clone $subjects)->pluck('classroom_term_id');
        $values = ['kkm' => $revision->final_kkm, 'kkm_revision_id' => $revision->id];
        $subjects->update($values);
        $sets = DiniyyahAssessmentSet::whereIn('diniyyah_class_subject_id', $ids);
        $setIds = (clone $sets)->pluck('id');
        (clone $sets)->where('status', 'validated')->update(['status' => 'submitted']);
        $sets->update($values);
        DiniyyahAssessmentResult::whereIn('diniyyah_assessment_set_id', $setIds)->update($values + [
            'is_passed' => DB::raw('CASE WHEN is_complete = true AND final_score IS NOT NULL AND final_score >= '.(int) $revision->final_kkm.' THEN true ELSE false END'),
        ]);
        foreach (DiniyyahLedgerSnapshot::whereIn('classroom_term_id', $classes)->whereNotIn('status', ['locked', 'published'])->get() as $snapshot) {
            $data = $snapshot->snapshot_data ?? [];
            $data['kkm_stale'] = true;
            $data['issues'][] = ['level' => 'blocking', 'code' => 'kkm_changed', 'message' => 'KKM berubah. Generate ulang leger setelah nilai divalidasi ulang.'];
            $data['summary']['blocking_issues'] = ($data['summary']['blocking_issues'] ?? 0) + 1;
            $snapshot->update(['status' => 'draft', 'snapshot_data' => $data, 'validated_at' => null, 'validated_by' => null]);
        }
    }

    private function current(DiniyyahKkmScope $scope, DiniyyahKkmRevision $revision, int $expected): DiniyyahKkmRevision
    {
        $revision = DiniyyahKkmRevision::whereKey($revision->id)->lockForUpdate()->firstOrFail();
        if ($scope->working_revision_id !== $revision->id || $revision->lock_version !== $expected) {
            $this->invalid('Data telah berubah. Muat ulang halaman sebelum melanjutkan.');
        }

        return $revision;
    }

    private function locked(DiniyyahKkmScope $scope, callable $callback): mixed
    {
        return DB::transaction(function () use ($scope, $callback) {
            $scope = DiniyyahKkmScope::whereKey($scope->id)->lockForUpdate()->firstOrFail();
            $this->resolver->ensureOpen($scope);

            return $callback($scope);
        }, 3);
    }

    private function audit(DiniyyahKkmScope $scope, User $actor, string $action, array $properties): void
    {
        activity('kkm')->performedOn($scope)->causedBy($actor)->event($action)->withProperties($properties)->log('KKM '.$action);
    }

    private function notify(DiniyyahKkmScope $scope, array $users, string $title, string $body, string $type): void
    {
        foreach (User::whereIn('id', array_filter($users))->get() as $user) {
            $prefix = $this->access->manages($user) && ! $user->hasRole('guru') ? 'admin.kkm.' : 'guru.kkm.';
            if ($user->hasRole('kabag_diniyyah') && ! $user->hasRole('guru')) {
                $prefix = 'kabag.kkm.';
            }
            $this->notifications->dispatchToUsers([$user->id], $title, $body, $type, route($prefix.'show', $scope), $type === 'kkm_approved' ? 'success' : 'info');
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['kkm' => $message]);
    }
}
