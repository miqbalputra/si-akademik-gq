<?php

namespace App\Services;

use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahScoreValidation;
use App\Models\User;

class DiniyyahAssessmentWorkflow
{
    public function submit(DiniyyahAssessmentSet $assessmentSet): void
    {
        $assessmentSet->update(['status' => 'submitted']);
        $assessmentSet->scores()->where('status', 'draft')->update(['status' => 'submitted']);
    }

    public function approve(DiniyyahAssessmentSet $assessmentSet, User $validator, ?string $notes = null): DiniyyahScoreValidation
    {
        $assessmentSet->update(['status' => 'validated']);
        $assessmentSet->scores()->whereIn('status', ['draft', 'submitted'])->update(['status' => 'validated']);

        return DiniyyahScoreValidation::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'validated_by' => $validator->id,
            'status' => 'approved',
            'validated_at' => now(),
            'notes' => $notes,
        ]);
    }

    public function requestRevision(DiniyyahAssessmentSet $assessmentSet, User $validator, ?string $notes = null): DiniyyahScoreValidation
    {
        $assessmentSet->update(['status' => 'needs_revision']);

        return DiniyyahScoreValidation::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'validated_by' => $validator->id,
            'status' => 'needs_revision',
            'validated_at' => now(),
            'notes' => $notes,
        ]);
    }
}
