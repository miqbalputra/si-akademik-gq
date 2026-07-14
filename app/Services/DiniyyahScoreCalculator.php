<?php

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\DiniyyahAssessmentResult;
use App\Models\DiniyyahAssessmentSet;
use Illuminate\Support\Collection;

class DiniyyahScoreCalculator
{
    public function syncAttendanceScores(DiniyyahAssessmentSet $assessmentSet, ?ClassEnrollment $enrollment = null): void
    {
        $assessmentSet->loadMissing('components', 'classSubject');
        $attendanceComponent = $assessmentSet->components->firstWhere('code', 'keaktifan_presensi');
        if (! $attendanceComponent) return;

        $classSubjectId = $assessmentSet->diniyyah_class_subject_id;

        $assignmentIds = \App\Models\DiniyyahTeacherAssignment::where('diniyyah_class_subject_id', $classSubjectId)->pluck('id');
        $journalIds = \App\Models\DiniyyahClassJournal::whereIn('diniyyah_teacher_assignment_id', $assignmentIds)->pluck('id');

        $totalDays = $journalIds->count();

        if ($totalDays === 0) return;

        $query = \App\Models\DiniyyahClassJournalAbsence::whereIn('diniyyah_class_journal_id', $journalIds)
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

        if (!empty($upsertData)) {
            \App\Models\DiniyyahScore::upsert(
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
        $assessmentSetIds = \App\Models\DiniyyahAssessmentSet::query()
            ->where('diniyyah_class_subject_id', $classSubjectId)
            ->pluck('id');

        foreach ($assessmentSetIds as $assessmentSetId) {
            $this->syncAttendanceScores(DiniyyahAssessmentSet::find($assessmentSetId));
        }
    }
    public function calculateAssessmentSet(DiniyyahAssessmentSet $assessmentSet): int
    {
        $assessmentSet->loadMissing('classSubject');

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
                'is_passed' => $payload['is_complete']
                    && $assessmentSet->kkm !== null
                    && $payload['final_score'] !== null
                    && $payload['final_score'] >= (float) $assessmentSet->kkm,
                'calculated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], $payload);
        }

        if (!empty($upsertResults)) {
            DiniyyahAssessmentResult::upsert(
                $upsertResults,
                ['diniyyah_assessment_set_id', 'class_enrollment_id'],
                ['daily_raw_score', 'exam_raw_score', 'daily_weighted_score', 'exam_weighted_score', 'final_score', 'kkm', 'is_complete', 'is_passed', 'calculated_at', 'updated_at']
            );
        }

        return $enrollments->count();
    }

    public function calculate(DiniyyahAssessmentSet $assessmentSet, ClassEnrollment $enrollment): DiniyyahAssessmentResult
    {
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
                'is_passed' => $payload['is_complete']
                    && $assessmentSet->kkm !== null
                    && $payload['final_score'] !== null
                    && $payload['final_score'] >= (float) $assessmentSet->kkm,
                'calculated_at' => now(),
            ],
        );
    }

    /**
     * @param  Collection<int, \App\Models\DiniyyahScore>  $scores
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

        $dailyRaw = $dailyScores->isNotEmpty() ? round($dailyScores->avg(), 2) : null;
        $examRaw = $examScores->isNotEmpty() ? round($examScores->avg(), 2) : null;
        $dailyWeight = $assessmentSet->daily_weight ?? 40;
        $examWeight = $assessmentSet->exam_weight ?? 60;

        $dailyWeighted = $dailyRaw !== null ? round($dailyRaw * $dailyWeight / 100, 2) : null;
        $examWeighted = $examRaw !== null ? round($examRaw * $examWeight / 100, 2) : null;
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
     * @param  Collection<int, \App\Models\DiniyyahScore>  $scores
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
}
