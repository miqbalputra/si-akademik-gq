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

        $totalDays = \App\Models\DiniyyahStudentAttendance::where('diniyyah_class_subject_id', $classSubjectId)
            ->max('meeting_number') ?: 0;
            
        if ($totalDays === 0) return;
        
        $query = \App\Models\DiniyyahStudentAttendance::where('diniyyah_class_subject_id', $classSubjectId)
            ->selectRaw('class_enrollment_id, count(*) as total_hadir')
            ->where('status', \App\Models\DiniyyahStudentAttendance::STATUS_PRESENT)
            ->groupBy('class_enrollment_id');
            
        if ($enrollment) {
            $query->where('class_enrollment_id', $enrollment->id);
            $enrollments = collect([$enrollment->id]);
        } else {
            $enrollments = ClassEnrollment::where('classroom_term_id', $assessmentSet->classSubject->classroom_term_id)
                ->where('status', 'active')
                ->pluck('id');
        }
        
        $attendances = $query->pluck('total_hadir', 'class_enrollment_id');
            
        $upsertData = [];
        $now = now();
        foreach ($enrollments as $enrollmentId) {
            $hadir = $attendances->get($enrollmentId, 0);
            $score = round(($hadir / $totalDays) * 100, 2);
            
            $upsertData[] = [
                'diniyyah_assessment_set_id' => $assessmentSet->id,
                'diniyyah_score_component_id' => $attendanceComponent->id,
                'class_enrollment_id' => $enrollmentId,
                'score' => $score,
                'status' => 'draft',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($upsertData)) {
            \App\Models\DiniyyahScore::upsert(
                $upsertData,
                ['diniyyah_assessment_set_id', 'diniyyah_score_component_id', 'class_enrollment_id'],
                ['score', 'status', 'updated_at']
            );
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
