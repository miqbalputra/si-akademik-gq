<?php

namespace App\Services;

use App\Models\TahfidzHalaqah;
use App\Models\TahfidzHalaqahMember;
use App\Models\TahfidzMonthlyRecap;
use App\Models\TahfidzSemesterRecap;
use App\Models\TahfidzWeek;
use App\Models\TahfidzWeeklyScore;
use Illuminate\Support\Collection;

class TahfidzScoreCalculator
{
    public function __construct(
        private readonly TahfidzSabaqParser $parser,
    ) {}

    /**
     * Recalculate monthly recaps for a member across all months.
     */
    public function recalculateMonthlyRecaps(TahfidzHalaqahMember $member): void
    {
        $scores = $member->weeklyScores()
            ->with('week')
            ->get();

        $byMonth = $scores->groupBy(fn (TahfidzWeeklyScore $score) => $score->week?->month_number ?? 0)
            ->sortKeys();

        $existingRecaps = TahfidzMonthlyRecap::where('tahfidz_halaqah_member_id', $member->id)
            ->get()
            ->keyBy('month_number');

        $cumulativeBaris = 0;
        $upserts = [];
        $termId = $member->halaqah?->academic_term_id ?? TahfidzHalaqah::find($member->tahfidz_halaqah_id)?->academic_term_id;
        $now = now();

        foreach ($byMonth as $monthNumber => $monthScores) {
            if ($monthNumber === 0) continue;

            $week = $monthScores->first()?->week;
            $monthLabel = $week?->month_label ?? $this->monthLabel($monthNumber);

            $numericScores = $monthScores->filter(fn (TahfidzWeeklyScore $s) => $s->score !== null && $s->score > 0);
            $averageScore = $numericScores->isNotEmpty() ? round($numericScores->avg('score'), 2) : null;
            $totalBaris = $monthScores->sum(fn (TahfidzWeeklyScore $s) => $s->sabaq_baris ?? 0);

            $cumulativeBaris += $totalBaris;
            $existing = $existingRecaps->get($monthNumber);

            $upserts[] = [
                'tahfidz_halaqah_member_id' => $member->id,
                'month_number' => $monthNumber,
                'tahfidz_halaqah_id' => $member->tahfidz_halaqah_id,
                'student_id' => $member->student_id,
                'academic_term_id' => $termId,
                'month_label' => $monthLabel,
                'sabaq_monthly' => $this->parser->formatFromBaris($totalBaris),
                'sabaq_monthly_baris' => $totalBaris,
                'average_score' => $averageScore,
                'total_hafalan' => $this->parser->formatFromBaris($cumulativeBaris),
                'manzil_submitted' => $existing?->manzil_submitted,
                'manzil_score' => $existing?->manzil_score,
                'notes' => $existing?->notes,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($upserts)) {
            TahfidzMonthlyRecap::upsert(
                $upserts,
                ['tahfidz_halaqah_member_id', 'month_number'],
                ['tahfidz_halaqah_id', 'student_id', 'academic_term_id', 'month_label', 'sabaq_monthly', 'sabaq_monthly_baris', 'average_score', 'total_hafalan', 'manzil_submitted', 'manzil_score', 'notes', 'updated_at']
            );
        }
    }

    /**
     * Calculate or update a single monthly recap.
     *
     * @param  Collection<int, TahfidzWeeklyScore>  $monthScores
     */
    public function calculateMonthlyRecap(TahfidzHalaqahMember $member, int $monthNumber, Collection $monthScores): TahfidzMonthlyRecap
    {
        $week = $monthScores->first()?->week;
        $monthLabel = $week?->month_label ?? $this->monthLabel($monthNumber);
        $termId = $member->halaqah?->academic_term_id ?? TahfidzHalaqah::find($member->tahfidz_halaqah_id)?->academic_term_id;

        // Calculate average score (only numeric scores, skip libur/murajaah)
        $numericScores = $monthScores->filter(fn (TahfidzWeeklyScore $s) => $s->score !== null && $s->score > 0);
        $averageScore = $numericScores->isNotEmpty() ? round($numericScores->avg('score'), 2) : null;

        // Calculate total baris
        $totalBaris = $monthScores->sum(fn (TahfidzWeeklyScore $s) => $s->sabaq_baris ?? 0);

        // Preserve manzil data from existing recap
        $existing = TahfidzMonthlyRecap::where('tahfidz_halaqah_member_id', $member->id)
            ->where('month_number', $monthNumber)
            ->first();

        // Calculate cumulative total hafalan
        $cumulativeBaris = TahfidzMonthlyRecap::where('tahfidz_halaqah_member_id', $member->id)
            ->where('month_number', '<=', $monthNumber)
            ->sum('sabaq_monthly_baris');
        $cumulativeBaris = $cumulativeBaris - ($existing?->sabaq_monthly_baris ?? 0) + $totalBaris;

        return TahfidzMonthlyRecap::updateOrCreate(
            [
                'tahfidz_halaqah_member_id' => $member->id,
                'month_number' => $monthNumber,
            ],
            [
                'tahfidz_halaqah_id' => $member->tahfidz_halaqah_id,
                'student_id' => $member->student_id,
                'academic_term_id' => $termId,
                'month_label' => $monthLabel,
                'sabaq_monthly' => $this->parser->formatFromBaris($totalBaris),
                'sabaq_monthly_baris' => $totalBaris,
                'average_score' => $averageScore,
                'total_hafalan' => $this->parser->formatFromBaris($cumulativeBaris),
                'manzil_submitted' => $existing?->manzil_submitted,
                'manzil_score' => $existing?->manzil_score,
                'notes' => $existing?->notes,
            ]
        );
    }

    /**
     * Calculate semester recap for a member.
     */
    public function calculateSemesterRecap(TahfidzHalaqahMember $member): TahfidzSemesterRecap
    {
        $recaps = $member->monthlyRecaps()->orderBy('month_number')->get();
        $termId = $member->halaqah?->academic_term_id ?? TahfidzHalaqah::find($member->tahfidz_halaqah_id)?->academic_term_id;

        $monthlyAverages = $recaps->filter(fn (TahfidzMonthlyRecap $r) => $r->average_score !== null);
        $sabaqSemester = $monthlyAverages->isNotEmpty() ? round($monthlyAverages->avg('average_score'), 2) : null;

        $manzilScores = $recaps->filter(fn (TahfidzMonthlyRecap $r) => $r->manzil_score !== null);
        $manzilAverage = $manzilScores->isNotEmpty() ? round($manzilScores->avg('manzil_score'), 2) : null;

        $existing = TahfidzSemesterRecap::where('tahfidz_halaqah_member_id', $member->id)->first();

        return TahfidzSemesterRecap::updateOrCreate(
            ['tahfidz_halaqah_member_id' => $member->id],
            [
                'tahfidz_halaqah_id' => $member->tahfidz_halaqah_id,
                'student_id' => $member->student_id,
                'academic_term_id' => $termId,
                'sabaq_semester_score' => $sabaqSemester,
                'sabaq_category' => $this->category($sabaqSemester),
                'manzil_average_score' => $manzilAverage,
                'manzil_category' => $this->category($manzilAverage),
                'sabqi_score' => $existing?->sabqi_score,
                'semester_notes' => $existing?->semester_notes,
                'status' => $existing?->status ?? 'draft',
            ]
        );
    }

    /**
     * Determine category from score.
     */
    public function category(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= 91 => 'L',
            $score >= 70 => 'HL',
            $score >= 55 => 'KL',
            default => 'BL',
        };
    }

    private function monthLabel(int $monthNumber): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $months[$monthNumber] ?? "Bulan {$monthNumber}";
    }
}