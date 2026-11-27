<?php

namespace App\Services;

use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\DiniyyahScoreValidation;
use App\Models\ReportCard;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DiniyyahAssessmentWorkflow
{
    public function submit(DiniyyahAssessmentSet $assessmentSet): void
    {
        app(DiniyyahKkmResolver::class)->withAssessmentLock($assessmentSet, function () use ($assessmentSet): void {
            $this->ensureOpen($assessmentSet, ['draft', 'active', 'needs_revision']);
            app(DiniyyahPracticeScoreSetup::class)->synchronize($assessmentSet);
            $this->ensureExamName($assessmentSet);
            app(DiniyyahScoreCalculator::class)->calculateAssessmentSet($assessmentSet);
            $summary = app(DiniyyahInputProgressService::class)->summary($assessmentSet);
            if ($summary['total_students'] === 0 || $summary['incomplete_students'] > 0) {
                throw ValidationException::withMessages(['scores' => 'Lengkapi nilai semua santri aktif sebelum mengajukan ke Kabag Diniyyah.']);
            }

            $assessmentSet->update(['status' => 'submitted']);
            $assessmentSet->gradingScores()->where('status', 'draft')->update(['status' => 'submitted']);
            $this->notifySubmitted($assessmentSet);
        });
    }

    public function approve(DiniyyahAssessmentSet $assessmentSet, User $validator, ?string $notes = null): DiniyyahScoreValidation
    {
        return app(DiniyyahKkmResolver::class)->withAssessmentLock($assessmentSet, fn () => $this->approveLocked($assessmentSet, $validator, $notes));
    }

    private function approveLocked(DiniyyahAssessmentSet $assessmentSet, User $validator, ?string $notes): DiniyyahScoreValidation
    {
        $this->ensureOpen($assessmentSet, ['submitted']);
        $this->ensureExamName($assessmentSet);
        app(DiniyyahKkmResolver::class)->ensureApproved($assessmentSet);
        $assessmentSet->update(['status' => 'validated']);
        $assessmentSet->gradingScores()->whereIn('status', ['draft', 'submitted'])->update(['status' => 'validated']);

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
        return app(DiniyyahKkmResolver::class)->withAssessmentLock($assessmentSet, function () use ($assessmentSet, $validator, $notes): DiniyyahScoreValidation {
            $this->ensureOpen($assessmentSet, ['submitted', 'validated']);

            return $this->revise($assessmentSet, $validator, $notes);
        });
    }

    private function revise(DiniyyahAssessmentSet $assessmentSet, User $validator, ?string $notes): DiniyyahScoreValidation
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

    private function ensureOpen(DiniyyahAssessmentSet $assessmentSet, array $statuses): void
    {
        $assessmentSet->refresh();
        $classroomId = $assessmentSet->classSubject->classroom_term_id;
        $frozenStatuses = ['locked', 'published'];
        if (DiniyyahLedgerSnapshot::where('classroom_term_id', $classroomId)->whereIn('status', $frozenStatuses)->exists()
            || ReportCard::where('classroom_term_id', $classroomId)->whereIn('status', $frozenStatuses)->exists()) {
            throw ValidationException::withMessages(['scores' => 'Leger atau rapor kelas sudah terkunci.']);
        }
        if (! in_array($assessmentSet->status, $statuses, true)) {
            throw ValidationException::withMessages(['scores' => 'Status penilaian berubah. Muat ulang halaman untuk melanjutkan.']);
        }
    }

    private function ensureExamName(DiniyyahAssessmentSet $assessmentSet): void
    {
        if ($assessmentSet->missingExamName()) {
            throw ValidationException::withMessages(['tested_material' => 'Guru pengampu wajib mengisi nama ujian Praktik Ibadah sebelum nilai diajukan atau divalidasi.']);
        }
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
