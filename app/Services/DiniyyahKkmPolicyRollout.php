<?php

namespace App\Services;

use App\Models\DiniyyahKkmRevision;
use App\Models\DiniyyahKkmScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DiniyyahKkmPolicyRollout
{
    public function __construct(private DiniyyahKkmCalculator $calculator, private DiniyyahKkmResolver $resolver, private DiniyyahKkmWorkflow $workflow) {}

    /** Apply once per eligible version; never rewrite an approved version's values. */
    public function initializeExisting(): array
    {
        $totals = ['adjusted_active' => 0, 'updated_working' => 0, 'skipped_frozen' => 0, 'skipped_invalid' => 0];
        DiniyyahKkmScope::whereHas('academicTerm', fn ($q) => $q->where('kkm_workflow_enabled', true)
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('ends_at', '>=', today()->toDateString())))
            ->chunkById(100, function ($scopes) use (&$totals): void {
                foreach ($scopes as $scope) {
                    foreach ($this->apply($scope) as $key => $count) {
                        $totals[$key] += $count;
                    }
                }
            });

        return $totals;
    }

    public function apply(DiniyyahKkmScope $scope): array
    {
        return DB::transaction(function () use ($scope): array {
            $counts = ['adjusted_active' => 0, 'updated_working' => 0, 'skipped_frozen' => 0, 'skipped_invalid' => 0];
            $term = $scope->academicTerm()->lockForUpdate()->firstOrFail();
            $scope = DiniyyahKkmScope::whereKey($scope->id)->lockForUpdate()->firstOrFail();
            if (! $term->kkm_workflow_enabled || (! $term->is_active && (! $term->ends_at || $term->ends_at->lt(today())))) {
                return $counts;
            }
            if ($scope->subject?->schoolKkm() !== null || $scope->activeRevision?->is_school_fixed) {
                return $counts;
            }
            if ($this->resolver->frozen($scope)) {
                $counts['skipped_frozen']++;

                return $counts;
            }

            $working = $scope->workingRevision()->lockForUpdate()->first();
            if ($working && in_array($working->status, ['draft', 'needs_revision', 'submitted'], true) && ! $working->uses_banded_kkm) {
                $payload = $this->calculate($working, $working->status === 'submitted');
                if ($payload === null) {
                    $counts['skipped_invalid']++;
                } else {
                    $before = $working->toArray();
                    $working->fill($payload + ['lock_version' => $working->lock_version + 1])->save();
                    $this->audit($scope, ['working_revision_id' => $working->id, 'before' => $before, 'after' => $working->toArray()]);
                    $counts['updated_working']++;
                }
            }

            $active = $scope->activeRevision()->lockForUpdate()->first();
            if (! $active || $active->status !== 'approved' || $active->uses_banded_kkm) {
                return $counts;
            }
            $payload = $this->calculate($active, true);
            if ($payload === null) {
                $counts['skipped_invalid']++;

                return $counts;
            }
            if ($active->final_kkm === $payload['final_kkm']) {
                return $counts; // No change to the official threshold or its consumers is needed.
            }

            $payload['criteria']['policy_source_revision_id'] = $active->id;
            $revision = $scope->revisions()->create($payload + [
                'version' => (int) $scope->revisions()->max('version') + 1,
                'status' => 'approved',
                'submitted_by' => $active->submitted_by, 'submitted_at' => $active->submitted_at,
                'reviewed_at' => now(),
                'review_notes' => 'Penyesuaian aturan KKM sekolah: rata-rata < 70 → 65; 70 sampai < 75 → 70; ≥ 75 → 75. Menggunakan isian versi '.$active->version.' yang sudah disetujui; nilai komponen dan alasan tetap disimpan pada riwayat.',
            ]);
            $active->update(['status' => 'superseded']);
            $scope->update(['active_revision_id' => $revision->id]);
            $this->workflow->synchronize($scope, $revision);
            $this->audit($scope, ['previous_active_revision_id' => $active->id, 'revision_id' => $revision->id, 'old_kkm' => $active->final_kkm, 'kkm' => $revision->final_kkm]);
            $counts['adjusted_active']++;

            return $counts;
        }, 3);
    }

    private function calculate(DiniyyahKkmRevision $revision, bool $complete): ?array
    {
        try {
            return $this->calculator->calculate($revision->criteria ?? [], $complete);
        } catch (ValidationException $exception) {
            Log::warning('Penyesuaian aturan KKM dilewati: isian versi tidak valid.', ['revision_id' => $revision->id, 'errors' => $exception->errors()]);

            return null;
        }
    }

    private function audit(DiniyyahKkmScope $scope, array $properties): void
    {
        activity('kkm')->performedOn($scope)->event('calculation_policy_applied')
            ->withProperties(['policy' => DiniyyahKkmCalculator::POLICY] + $properties)->log('Penyesuaian aturan KKM sekolah');
    }
}
