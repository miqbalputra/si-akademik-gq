<?php

namespace App\Services;

use App\Models\TahfidzUasCategory;
use App\Models\TahfidzUasDay;
use App\Models\TahfidzUasResult;
use App\Models\TahfidzUasScore;
use App\Models\Student;
use Illuminate\Support\Collection;

class TahfidzUasCalculator
{
    /**
     * Calculate UAS result for a student.
     */
    public function calculateForStudent(int $academicTermId, int $studentId): TahfidzUasResult
    {
        $categories = TahfidzUasCategory::where('academic_term_id', $academicTermId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $days = TahfidzUasDay::where('academic_term_id', $academicTermId)
            ->where('is_active', true)
            ->orderBy('day_number')
            ->get();

        $scores = TahfidzUasScore::where('academic_term_id', $academicTermId)
            ->where('student_id', $studentId)
            ->get()
            ->keyBy(fn (TahfidzUasScore $s) => $s->tahfidz_uas_day_id.'-'.$s->tahfidz_uas_category_id);

        $dailyTotals = [];
        $allComplete = true;

        foreach ($days as $day) {
            $dayTotal = 0;
            $dayComplete = true;

            foreach ($categories as $category) {
                $score = $scores->get($day->id.'-'.$category->id);
                $value = $score?->score !== null ? (float) $score->score : null;

                if ($value !== null) {
                    $dayTotal += min($value, $category->max_score);
                } else {
                    $dayComplete = false;
                }
            }

            $dailyTotals[] = $dayComplete ? $dayTotal : null;

            if (! $dayComplete) {
                $allComplete = false;
            }
        }

        $validTotals = array_filter($dailyTotals, fn ($t) => $t !== null);
        $finalScore = count($validTotals) > 0 ? round(array_sum($validTotals) / count($validTotals), 2) : null;

        $existing = TahfidzUasResult::where('academic_term_id', $academicTermId)
            ->where('student_id', $studentId)
            ->first();

        return TahfidzUasResult::updateOrCreate(
            [
                'academic_term_id' => $academicTermId,
                'student_id' => $studentId,
            ],
            [
                'tahfidz_halaqah_id' => $existing?->tahfidz_halaqah_id,
                'juz_tested' => $existing?->juz_tested,
                'daily_totals' => $dailyTotals,
                'final_score' => $finalScore,
                'predicate' => $this->predicate($finalScore),
                'is_complete' => $allComplete && $finalScore !== null,
                'calculated_at' => now(),
                'status' => $existing?->status ?? 'draft',
            ]
        );
    }

    /**
     * Calculate UAS results for all students in a term.
     */
    public function calculateForTerm(int $academicTermId): int
    {
        $studentIds = TahfidzUasScore::where('academic_term_id', $academicTermId)
            ->distinct()
            ->pluck('student_id');

        $count = 0;
        foreach ($studentIds as $studentId) {
            $this->calculateForStudent($academicTermId, $studentId);
            $count++;
        }

        return $count;
    }

    /**
     * Determine predicate from final score.
     */
    public function predicate(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= 90 => 'Mumtaz',
            $score >= 80 => 'Jayyid Jiddan',
            $score >= 70 => 'Jayyid',
            $score >= 60 => 'Maqbul',
            default => 'Daif',
        };
    }
}