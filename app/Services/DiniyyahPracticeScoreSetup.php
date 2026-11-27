<?php

namespace App\Services;

use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\ReportCard;
use Illuminate\Support\Facades\DB;

class DiniyyahPracticeScoreSetup
{
    /** Return false when a legacy set must keep its method to preserve existing assessment data. */
    public function synchronize(DiniyyahAssessmentSet $set): bool
    {
        if (! $this->needsDirectMethod($set)) {
            return true;
        }

        return DB::transaction(function () use ($set): bool {
            ClassroomTerm::whereKey($set->classSubject->classroom_term_id)->lockForUpdate()->firstOrFail();
            DiniyyahAssessmentSet::whereKey($set->id)->lockForUpdate()->firstOrFail();
            $set->refresh();
            $classSubject = $set->classSubject()->lockForUpdate()->firstOrFail();
            $set->setRelation('classSubject', $classSubject);
            if (! $this->needsDirectMethod($set)) {
                return true;
            }

            if (! in_array($set->status, ['draft', 'active', 'needs_revision'], true)
                || DiniyyahLedgerSnapshot::where('classroom_term_id', $classSubject->classroom_term_id)->whereIn('status', ['locked', 'published'])->exists()
                || ReportCard::where('classroom_term_id', $classSubject->classroom_term_id)->whereIn('status', ['locked', 'published'])->exists()
                || $set->validations()->exists()
                || $this->hasRecordedGrade($set)) {
                return false;
            }

            $components = $set->components()->get();
            $final = $components->firstWhere('code', 'nilai_akhir');
            if ($components->where('component_group', 'final')->count() !== ($final ? 1 : 0)
                || ($final && ($final->component_group !== 'final' || ! $final->is_required))) {
                return false;
            }

            // Keep legacy component IDs and scores for history. Only the method and the new input are needed.
            DiniyyahAssessmentSet::whereKey($set->id)->update(['assessment_method' => 'direct_final']);
            $set->refresh();
            if (! $final) {
                app(DiniyyahAssessmentComponentBuilder::class)->createDefaults($set);
                $set->unsetRelation('components');
            }

            return true;
        }, 3);
    }

    private function needsDirectMethod(DiniyyahAssessmentSet $set): bool
    {
        return in_array($set->assessment_method, ['weighted', 'practical'], true)
            && $set->classSubject?->assessment_method === 'direct_final'
            && $set->classSubject->subject?->isPraktikIbadah();
    }

    private function hasRecordedGrade(DiniyyahAssessmentSet $set): bool
    {
        return $set->scores()->withTrashed()->where(function ($scores) {
            $scores->whereNotNull('deleted_at')
                ->orWhereNotNull('notes')
                ->orWhere('status', '!=', 'draft')
                ->orWhere(function ($recorded) {
                    $recorded->whereNotNull('score')->where(function ($manual) {
                        $manual->whereNotNull('input_by')->orWhereNotNull('input_at')
                            ->orWhereDoesntHave('component', fn ($component) => $component->where('code', 'keaktifan_presensi'));
                    });
                });
        })->exists() || $set->results()->where(function ($results) {
            $results->whereNotNull('final_score')->orWhereNotNull('exam_raw_score')
                ->orWhereNotNull('locked_at')->orWhere('is_complete', true);
        })->exists();
    }
}
