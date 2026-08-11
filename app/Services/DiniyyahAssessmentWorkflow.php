<?php

namespace App\Services;

use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahScoreValidation;
use App\Models\User;
use App\Services\NotificationDispatcher;

class DiniyyahAssessmentWorkflow
{
    public function submit(DiniyyahAssessmentSet $assessmentSet): void
    {
        $assessmentSet->update(['status' => 'submitted']);
        $assessmentSet->scores()->where('status', 'draft')->update(['status' => 'submitted']);

        $this->notifySubmitted($assessmentSet);
    }

    public function approve(DiniyyahAssessmentSet $assessmentSet, User $validator, ?string $notes = null): DiniyyahScoreValidation
    {
        $assessmentSet->update(['status' => 'validated']);
        $assessmentSet->scores()->whereIn('status', ['draft', 'submitted'])->update(['status' => 'validated']);

        $validation = DiniyyahScoreValidation::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'validated_by' => $validator->id,
            'status' => 'approved',
            'validated_at' => now(),
            'notes' => $notes,
        ]);

        $this->notifyApproved($assessmentSet, $validator);

        return $validation;
    }

    public function requestRevision(DiniyyahAssessmentSet $assessmentSet, User $validator, ?string $notes = null): DiniyyahScoreValidation
    {
        $assessmentSet->update(['status' => 'needs_revision']);

        $validation = DiniyyahScoreValidation::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'validated_by' => $validator->id,
            'status' => 'needs_revision',
            'validated_at' => now(),
            'notes' => $notes,
        ]);

        $this->notifyNeedsRevision($assessmentSet, $validator, $notes);

        return $validation;
    }

    // ── Notifikasi ────────────────────────────────────────────────────────

    private function notifySubmitted(DiniyyahAssessmentSet $set): void
    {
        $mapel = $set->classSubject?->subject?->name ?? 'mapel';
        $kelas = $set->classSubject?->classroomTerm?->name ?? 'kelas';

        app(NotificationDispatcher::class)->dispatchToRole(
            'kabag_diniyyah',
            "Nilai {$mapel} menunggu validasi",
            "Nilai {$mapel} kelas {$kelas} disubmit oleh guru pengampu dan menunggu validasi Anda.",
            'assessment_submitted',
            route('diniyyah.monitoring.index'),
            'info',
        );
    }

    private function notifyApproved(DiniyyahAssessmentSet $set, User $validator): void
    {
        $mapel = $set->classSubject?->subject?->name ?? 'mapel';
        $kelas = $set->classSubject?->classroomTerm?->name ?? 'kelas';
        $validatorName = $validator->name ?? 'Kabag Diniyyah';

        app(NotificationDispatcher::class)->dispatchToSubjectTeachers(
            $set->id,
            "Nilai {$mapel} divalidasi",
            "Nilai {$mapel} kelas {$kelas} telah divalidasi oleh {$validatorName}.",
            'assessment_approved',
            route('guru.diniyyah-scores.edit', $set),
            'success',
        );
    }

    private function notifyNeedsRevision(DiniyyahAssessmentSet $set, User $validator, ?string $notes): void
    {
        $mapel = $set->classSubject?->subject?->name ?? 'mapel';
        $kelas = $set->classSubject?->classroomTerm?->name ?? 'kelas';
        $validatorName = $validator->name ?? 'Kabag Diniyyah';
        $body = "Nilai {$mapel} kelas {$kelas} perlu direvisi oleh {$validatorName}.";
        if ($notes) {
            $body .= " Catatan: {$notes}";
        }

        app(NotificationDispatcher::class)->dispatchToSubjectTeachers(
            $set->id,
            "Nilai {$mapel} perlu revisi",
            $body,
            'assessment_needs_revision',
            route('guru.diniyyah-scores.edit', $set),
            'warning',
        );
    }
}
