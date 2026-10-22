<?php

namespace App\Services;

use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahKkmRevision;
use App\Models\DiniyyahKkmScope;
use Illuminate\Support\Facades\DB;

class DiniyyahSchoolKkm
{
    public function __construct(private DiniyyahKkmResolver $resolver, private DiniyyahKkmWorkflow $workflow, private DiniyyahKkmAccess $access, private NotificationDispatcher $notifications) {}

    public function initializeExisting(?int $termId = null): void
    {
        DiniyyahClassSubject::with(['subject', 'classroomTerm.academicTerm', 'classroomTerm.classroom'])
            ->where('is_active', true)->whereHas('classroomTerm.academicTerm', fn ($q) => $q->where('kkm_workflow_enabled', true))
            ->when($termId, fn ($q) => $q->whereHas('classroomTerm', fn ($q) => $q->where('academic_term_id', $termId)))
            ->chunkById(100, function ($subjects): void {
                foreach ($subjects as $subject) {
                    if ($subject->subject?->schoolKkm() !== null) {
                        $this->ensure($subject);
                    }
                }
            });
    }

    public function ensure(DiniyyahClassSubject $subject): ?DiniyyahKkmRevision
    {
        $kkm = $subject->subject?->schoolKkm();
        if ($kkm === null || ! $this->resolver->required($subject) || ! DiniyyahKkmResolver::mustawa($subject->classroomTerm?->classroom?->level_name)) {
            return null;
        }

        return DB::transaction(function () use ($subject, $kkm) {
            $subject->classroomTerm->academicTerm()->lockForUpdate()->firstOrFail();
            $existing = $this->resolver->scopeFor($subject);
            if (! $existing && $this->resolver->frozen(new DiniyyahKkmScope(['academic_term_id' => $subject->classroomTerm->academic_term_id, 'subject_id' => $subject->subject_id, 'mustawa' => DiniyyahKkmResolver::mustawa($subject->classroomTerm->classroom?->level_name)]))) {
                return null;
            }
            $scope = $this->resolver->scopeFor($subject, true);
            $scope = DiniyyahKkmScope::whereKey($scope->id)->lockForUpdate()->firstOrFail();
            $active = $scope->activeRevision;
            if (($active?->is_school_fixed && $active->final_kkm === $kkm) || $this->resolver->frozen($scope)) {
                return $active; // Published/locked history is never rewritten by a policy rollout.
            }
            $working = $scope->workingRevision;
            if ($working) {
                $working->update(['status' => 'cancelled_by_policy', 'lock_version' => $working->lock_version + 1]);
            }
            if ($active) {
                $active->update(['status' => 'superseded']);
            }
            $revision = $scope->revisions()->create([
                'version' => (int) $scope->revisions()->max('version') + 1, 'status' => 'school_fixed',
                'criteria' => ['school_policy' => 'fixed_70_v1'], 'average' => null, 'final_kkm' => $kkm,
                'reviewed_at' => now(), 'review_notes' => 'KKM tetap ditetapkan Sekolah/Kabag Diniyyah. Tidak melalui perhitungan unsur, pengajuan, atau ACC guru.',
            ]);
            $scope->update(['active_revision_id' => $revision->id, 'working_revision_id' => null]);
            $this->workflow->synchronize($scope, $revision);
            activity('kkm')->performedOn($scope)->event('school_fixed')->withProperties([
                'policy' => 'fixed_70_v1', 'revision_id' => $revision->id, 'previous_active_revision_id' => $active?->id,
                'previous_working_revision_id' => $working?->id, 'kkm' => $kkm,
            ])->log('KKM tetap Sekolah/Kabag Diniyyah');
            foreach ($this->access->eligibleTeachers($scope)->get() as $teacher) {
                $this->notifications->dispatchToUsers([$teacher->user_id], 'KKM ditetapkan sekolah', $scope->label.' ditetapkan '.$kkm.' oleh Sekolah/Kabag Diniyyah dan tidak dapat diajukan atau diubah.', 'kkm_approved', route('guru.kkm.show', $scope), 'success');
            }

            return $revision;
        }, 3);
    }
}
