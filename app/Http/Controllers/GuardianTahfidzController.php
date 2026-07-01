<?php

namespace App\Http\Controllers;

use App\Models\TahfidzHalaqahMember;
use App\Models\TahfidzMonthlyRecap;
use App\Models\TahfidzSemesterRecap;
use App\Models\TahfidzUasResult;
use App\Models\TahfidzWeeklyScore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class GuardianTahfidzController extends Controller
{
    public function index(Request $request): View
    {
        $guardian = $request->user()->guardian;
        abort_unless($guardian, 403);

        $students = $guardian->students()
            ->wherePivot('can_login', true)
            ->orderBy('name')
            ->get();

        $selectedStudentId = (int) $request->query('student', $students->first()?->id ?? 0);
        $selectedStudent = $students->firstWhere('id', $selectedStudentId);

        $weeklyScores = collect();
        $monthlyRecaps = collect();
        $semesterRecap = null;
        $uasResult = null;
        $chartData = [];

        if ($selectedStudent) {
            $members = TahfidzHalaqahMember::where('student_id', $selectedStudent->id)
                ->where('status', 'active')
                ->with('halaqah.academicTerm.academicYear')
                ->get();

            $memberIds = $members->pluck('id');

            $weeklyScores = TahfidzWeeklyScore::whereIn('tahfidz_halaqah_member_id', $memberIds)
                ->with('week')
                ->orderBy('tahfidz_week_id')
                ->get();

            $monthlyRecaps = TahfidzMonthlyRecap::whereIn('tahfidz_halaqah_member_id', $memberIds)
                ->orderBy('month_number')
                ->get();

            $semesterRecap = TahfidzSemesterRecap::whereIn('tahfidz_halaqah_member_id', $memberIds)->latest()->first();

            $uasResult = TahfidzUasResult::where('student_id', $selectedStudent->id)->latest()->first();

            // Chart data
            $chartData = $this->buildChartData($weeklyScores, $monthlyRecaps);
        }

        return view('wali.tahfidz', compact(
            'students', 'selectedStudent', 'weeklyScores', 'monthlyRecaps',
            'semesterRecap', 'uasResult', 'chartData'
        ));
    }

    private function buildChartData($weeklyScores, $monthlyRecaps): array
    {
        // Grafik 1: Nilai pekanan per pekan
        $weeklyChart = $weeklyScores
            ->filter(fn ($s) => $s->score !== null && $s->score > 0)
            ->map(fn ($s) => [
                'label' => $s->week?->date_label ?? 'Pekan '.$s->week?->week_number,
                'value' => (float) $s->score,
            ])
            ->values();

        // Grafik 2: Total baris sabaq per bulan
        $barisChart = $monthlyRecaps->map(fn ($r) => [
            'label' => $r->month_label ?? 'Bulan '.$r->month_number,
            'value' => $r->sabaq_monthly_baris ?? 0,
        ])->values();

        // Grafik 3: Tren nilai rata-rata bulanan
        $monthlyAvgChart = $monthlyRecaps
            ->filter(fn ($r) => $r->average_score !== null)
            ->map(fn ($r) => [
                'label' => $r->month_label ?? 'Bulan '.$r->month_number,
                'value' => (float) $r->average_score,
            ])
            ->values();

        // Grafik 4: Nilai manzil per bulan
        $manzilChart = $monthlyRecaps
            ->filter(fn ($r) => $r->manzil_score !== null)
            ->map(fn ($r) => [
                'label' => $r->month_label ?? 'Bulan '.$r->month_number,
                'value' => (float) $r->manzil_score,
            ])
            ->values();

        return [
            'weekly' => $weeklyChart,
            'baris' => $barisChart,
            'monthly_avg' => $monthlyAvgChart,
            'manzil' => $manzilChart,
        ];
    }
}