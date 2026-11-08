<?php

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\DiniyyahAssessmentResult;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassJournalAbsence;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\DiniyyahScore;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\ReportCard;
use Illuminate\Support\Collection;

class DiniyyahScoreCalculator
{
    public function syncAttendanceScores(DiniyyahAssessmentSet $assessmentSet, ?ClassEnrollment $enrollment = null): void
    {
        $assessmentSet->loadMissing('components', 'classSubject');
        if ($this->frozen($assessmentSet)) {
            return;
        }
        $attendanceComponent = $assessmentSet->components->firstWhere('code', 'keaktifan_presensi');
        if (! $attendanceComponent) {
            return;
        }

        $classSubjectId = $assessmentSet->diniyyah_class_subject_id;

        $assignmentIds = DiniyyahTeacherAssignment::where('diniyyah_class_subject_id', $classSubjectId)->pluck('id');
        $journalIds = DiniyyahClassJournal::whereIn('diniyyah_teacher_assignment_id', $assignmentIds)->pluck('id');

        $totalDays = $journalIds->count();

        if ($totalDays === 0) {
            return;
        }

        $query = DiniyyahClassJournalAbsence::whereIn('diniyyah_class_journal_id', $journalIds)
            ->selectRaw('class_enrollment_id, count(*) as total_absen')
            ->whereIn('status', ['sick', 'permission', 'absent', 'skipped'])
            ->groupBy('class_enrollment_id');

        if ($enrollment) {
            $query->where('class_enrollment_id', $enrollment->id);
            $enrollments = collect([$enrollment->id]);
        } else {
            $enrollments = ClassEnrollment::where('classroom_term_id', $assessmentSet->classSubject->classroom_term_id)
                ->where('status', 'active')
                ->pluck('id');
        }

        $absences = $query->pluck('total_absen', 'class_enrollment_id');

        $upsertData = [];
        $now = now();
        foreach ($enrollments as $enrollmentId) {
            $hadir = max(0, $totalDays - $absences->get($enrollmentId, 0));
            $score = round(($hadir / $totalDays) * 100, 2);

            $upsertData[] = [
                'diniyyah_assessment_set_id' => $assessmentSet->id,
                'diniyyah_score_component_id' => $attendanceComponent->id,
                'class_enrollment_id' => $enrollmentId,
                'score' => $score,
                // Hanya untuk baris baru; baris yang sudah ada mempertahankan
                // statusnya (lihat kolom update di bawah) — jangan menurunkan
                // skor yang sudah submitted/validated kembali ke draft.
                'status' => 'draft',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($upsertData)) {
            DiniyyahScore::upsert(
                $upsertData,
                // Cocokkan constraint unik DB: (component_id, enrollment_id).
                // set_id redundan (komponen sudah terikat ke satu set) dan TIDAK
                // menjadi bagian unique index — memakainya sebagai conflict target
                // membuat SQLite/Postgres menolak ("ON CONFLICT clause does not
                // match any PRIMARY KEY or UNIQUE constraint").
                ['diniyyah_score_component_id', 'class_enrollment_id'],
                // Hanya segarkan nilai & updated_at. Status TIDAK di-overwrite,
                // sehingga skor presensi yang sudah submitted/validated tetap.
                ['score', 'updated_at']
            );
        }
    }

    /**
     * Sinkronkan ulang skor presensi (keaktifan_presensi) untuk SEMUA assessment
     * set dari sebuah class subject. Dipanggil observer jurnal/absensi diniyyah
     * setiap kali jurnal atau catatan absensi dibuat/dihapus, agar skor presensi
     * otomatis menyala dari data jurnal guru (presensi harian dari wali kelas +
     * centang bolos oleh guru diniyyah) tanpa harus menunggu recalc manual admin.
     */
    public function syncAttendanceForClassSubject(int $classSubjectId): void
    {
        $assessmentSetIds = DiniyyahAssessmentSet::query()
            ->where('diniyyah_class_subject_id', $classSubjectId)
            ->pluck('id');

        foreach ($assessmentSetIds as $assessmentSetId) {
            $set = DiniyyahAssessmentSet::find($assessmentSetId);
            if ($this->frozen($set)) {
                continue;
            }
            app(DiniyyahKkmResolver::class)->withAssessmentLock($set, function () use ($set): void {
                if ($this->frozen($set)) {
                    return;
                }
                $before = $set->scores()->whereHas('component', fn ($q) => $q->where('code', 'keaktifan_presensi'))->orderBy('class_enrollment_id')->pluck('score', 'class_enrollment_id')->all();
                $this->syncAttendanceScores($set);
                $after = $set->scores()->whereHas('component', fn ($q) => $q->where('code', 'keaktifan_presensi'))->orderBy('class_enrollment_id')->pluck('score', 'class_enrollment_id')->all();
                if ($before !== $after) {
                    if (in_array($set->status, ['submitted', 'validated'], true)) {
                        $set->update(['status' => 'needs_revision']);
                    }
                    $this->calculateAssessmentSetLocked($set);
                }
            });
        }
    }

    public function calculateAssessmentSet(DiniyyahAssessmentSet $assessmentSet): int
    {
        return app(DiniyyahKkmResolver::class)->withAssessmentLock($assessmentSet, fn () => $this->calculateAssessmentSetLocked($assessmentSet));
    }

    private function calculateAssessmentSetLocked(DiniyyahAssessmentSet $assessmentSet): int
    {
        $assessmentSet->loadMissing('classSubject');
        if ($this->frozen($assessmentSet)) {
            throw new \DomainException('Nilai tidak dihitung ulang karena leger atau rapor kelas sudah terkunci.');
        }
        $resolvedKkm = app(DiniyyahKkmResolver::class)->resolve($assessmentSet->classSubject, $assessmentSet->kkm === null ? null : (float) $assessmentSet->kkm);
        $assessmentSet->forceFill($resolvedKkm);

        $enrollments = ClassEnrollment::query()
            ->where('classroom_term_id', $assessmentSet->classSubject->classroom_term_id)
            ->where('status', 'active')
            ->get();

        $this->syncAttendanceScores($assessmentSet);

        // Preload scores to prevent N+1
        $allScores = $assessmentSet->scores()
            ->whereIn('class_enrollment_id', $enrollments->pluck('id'))
            ->get()
            ->groupBy('class_enrollment_id');

        $upsertResults = [];
        $now = now();

        $assessmentSet->loadMissing('components');

        foreach ($enrollments as $enrollment) {
            $scores = $allScores->get($enrollment->id, collect())->keyBy('diniyyah_score_component_id');

            $payload = match ($assessmentSet->assessment_method) {
                'direct_final' => $this->calculateDirectFinal($assessmentSet, $scores),
                'practical', 'weighted' => $this->calculateWeighted($assessmentSet, $scores),
                default => $this->calculateWeighted($assessmentSet, $scores),
            };

            $upsertResults[] = array_merge([
                'diniyyah_assessment_set_id' => $assessmentSet->id,
                'class_enrollment_id' => $enrollment->id,
                'kkm' => $assessmentSet->kkm,
                'kkm_revision_id' => $resolvedKkm['kkm_revision_id'],
                'is_passed' => $payload['is_complete']
                    && $assessmentSet->kkm !== null
                    && $payload['final_score'] !== null
                    && $payload['final_score'] >= (float) $assessmentSet->kkm,
                'calculated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], $payload);
        }

        if (! empty($upsertResults)) {
            DiniyyahAssessmentResult::upsert(
                $upsertResults,
                ['diniyyah_assessment_set_id', 'class_enrollment_id'],
                ['daily_raw_score', 'exam_raw_score', 'daily_weighted_score', 'exam_weighted_score', 'final_score', 'kkm', 'kkm_revision_id', 'is_complete', 'is_passed', 'calculated_at', 'updated_at']
            );
        }

        return $enrollments->count();
    }

    public function calculate(DiniyyahAssessmentSet $assessmentSet, ClassEnrollment $enrollment): DiniyyahAssessmentResult
    {
        return app(DiniyyahKkmResolver::class)->withAssessmentLock($assessmentSet, fn () => $this->calculateLocked($assessmentSet, $enrollment));
    }

    private function calculateLocked(DiniyyahAssessmentSet $assessmentSet, ClassEnrollment $enrollment): DiniyyahAssessmentResult
    {
        if ($this->frozen($assessmentSet)) {
            throw new \DomainException('Nilai tidak dihitung ulang karena leger atau rapor kelas sudah terkunci.');
        }
        $resolvedKkm = app(DiniyyahKkmResolver::class)->resolve($assessmentSet->classSubject, $assessmentSet->kkm === null ? null : (float) $assessmentSet->kkm);
        $assessmentSet->forceFill($resolvedKkm);
        $assessmentSet->loadMissing('components');

        $scores = $assessmentSet->scores()
            ->where('class_enrollment_id', $enrollment->id)
            ->get()
            ->keyBy('diniyyah_score_component_id');

        $payload = match ($assessmentSet->assessment_method) {
            'direct_final' => $this->calculateDirectFinal($assessmentSet, $scores),
            'practical', 'weighted' => $this->calculateWeighted($assessmentSet, $scores),
            default => $this->calculateWeighted($assessmentSet, $scores),
        };

        return DiniyyahAssessmentResult::updateOrCreate(
            [
                'diniyyah_assessment_set_id' => $assessmentSet->id,
                'class_enrollment_id' => $enrollment->id,
            ],
            $payload + [
                'kkm' => $assessmentSet->kkm,
                'kkm_revision_id' => $resolvedKkm['kkm_revision_id'],
                'is_passed' => $payload['is_complete']
                    && $assessmentSet->kkm !== null
                    && $payload['final_score'] !== null
                    && $payload['final_score'] >= (float) $assessmentSet->kkm,
                'calculated_at' => now(),
            ],
        );
    }

    /**
     * @param  Collection<int, DiniyyahScore>  $scores
     * @return array<string, mixed>
     */
    private function calculateWeighted(DiniyyahAssessmentSet $assessmentSet, Collection $scores): array
    {
        $dailyComponents = $assessmentSet->components->where('component_group', 'daily');
        $examComponents = $assessmentSet->components->where('component_group', 'exam');

        $dailyScores = $this->scoresForComponents($dailyComponents, $scores);
        $examScores = $this->scoresForComponents($examComponents, $scores);

        $isComplete = $this->requiredComponentsComplete($dailyComponents, $scores)
            && $this->requiredComponentsComplete($examComponents, $scores);

        $dailyRaw = $dailyScores->isNotEmpty() ? $dailyScores->avg() : null;
        $examRaw = $examScores->isNotEmpty() ? $examScores->avg() : null;
        $dailyWeight = $assessmentSet->daily_weight ?? 40;
        $examWeight = $assessmentSet->exam_weight ?? 60;

        $dailyWeighted = $dailyRaw !== null ? $dailyRaw * $dailyWeight / 100 : null;
        $examWeighted = $examRaw !== null ? $examRaw * $examWeight / 100 : null;
        $finalScore = $isComplete && $dailyWeighted !== null && $examWeighted !== null
            ? round($dailyWeighted + $examWeighted, 2)
            : null;

        return [
            'daily_raw_score' => $dailyRaw,
            'exam_raw_score' => $examRaw,
            'daily_weighted_score' => $dailyWeighted,
            'exam_weighted_score' => $examWeighted,
            'final_score' => $finalScore,
            'is_complete' => $isComplete,
        ];
    }

    /**
     * @param  Collection<int, DiniyyahScore>  $scores
     * @return array<string, mixed>
     */
    private function calculateDirectFinal(DiniyyahAssessmentSet $assessmentSet, Collection $scores): array
    {
        $finalComponents = $assessmentSet->components->where('component_group', 'final');
        $finalScores = $this->scoresForComponents($finalComponents, $scores);
        $isComplete = $this->requiredComponentsComplete($finalComponents, $scores);
        $finalScore = $isComplete && $finalScores->isNotEmpty() ? round($finalScores->avg(), 2) : null;

        return [
            'daily_raw_score' => null,
            'exam_raw_score' => null,
            'daily_weighted_score' => null,
            'exam_weighted_score' => null,
            'final_score' => $finalScore,
            'is_complete' => $isComplete,
        ];
    }

    private function scoresForComponents(Collection $components, Collection $scores): Collection
    {
        return $components
            ->map(fn ($component) => $scores->get($component->id)?->score)
            ->filter(fn ($score) => $score !== null)
            ->map(fn ($score) => (float) $score)
            ->values();
    }

    private function requiredComponentsComplete(Collection $components, Collection $scores): bool
    {
        $requiredComponents = $components->where('is_required', true);

        if ($requiredComponents->isEmpty()) {
            return false;
        }

        return $requiredComponents->every(fn ($component) => $scores->get($component->id)?->score !== null);
    }

    private function frozen(DiniyyahAssessmentSet $set): bool
    {
        return DiniyyahLedgerSnapshot::where('classroom_term_id', $set->classSubject->classroom_term_id)->whereIn('status', ['locked', 'published'])->exists()
            || ReportCard::where('classroom_term_id', $set->classSubject->classroom_term_id)->whereIn('status', ['locked', 'published'])->exists();
    }
}
