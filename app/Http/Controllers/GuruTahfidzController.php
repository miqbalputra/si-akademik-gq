<?php

namespace App\Http\Controllers;

use App\Models\TahfidzHalaqah;
use App\Models\TahfidzUasCategory;
use App\Models\TahfidzUasDay;
use App\Models\TahfidzUasScore;
use App\Models\TahfidzWeek;
use App\Models\TahfidzWeeklyScore;
use App\Services\TahfidzSabaqParser;
use App\Services\TahfidzScoreCalculator;
use App\Services\TahfidzUasCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GuruTahfidzController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $teacher = $user->teacher;

        $query = TahfidzHalaqah::query()
            ->with(['academicTerm.academicYear', 'activeMembers.student', 'teacher']);

        if (! $user->hasRole('admin') && ! $user->hasRole('kabag_tahfidz')) {
            $query->where(function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher?->id ?? 0)
                  ->orWhere('assistant_teacher_id', $teacher?->id ?? 0);
            });
        }

        $halaqahs = $query->latest()->get();

        return view('guru.tahfidz.index', compact('halaqahs'));
    }

    public function show(Request $request, TahfidzHalaqah $halaqah): View
    {
        $halaqah->load(['academicTerm.academicYear', 'activeMembers.student', 'teacher']);

        $weeks = TahfidzWeek::where('academic_term_id', $halaqah->academic_term_id)
            ->where('is_active', true)
            ->orderBy('week_number')
            ->get();

        $selectedMonth = (int) $request->query('month', $weeks->first()?->month_number ?? 0);

        $monthWeeks = $weeks->where('month_number', $selectedMonth);
        $members = $halaqah->activeMembers()->with('student')->orderBy('sort_order')->orderBy('student_id')->get();

        $scores = TahfidzWeeklyScore::query()
            ->whereIn('tahfidz_halaqah_member_id', $members->pluck('id'))
            ->whereIn('tahfidz_week_id', $monthWeeks->pluck('id'))
            ->get()
            ->keyBy(fn ($s) => $s->tahfidz_halaqah_member_id.'-'.$s->tahfidz_week_id);

        $availableMonths = $weeks->groupBy('month_number')->map(fn ($w) => [
            'number' => $w->first()->month_number,
            'label' => $w->first()->month_label ?? 'Bulan '.$w->first()->month_number,
        ])->sortBy('number')->values();

        return view('guru.tahfidz.show', compact('halaqah', 'weeks', 'monthWeeks', 'members', 'scores', 'availableMonths', 'selectedMonth'));
    }

    public function update(Request $request, TahfidzHalaqah $halaqah, TahfidzSabaqParser $parser, TahfidzScoreCalculator $calculator): RedirectResponse
    {
        $validated = $request->validate([
            'scores' => ['array'],
            'scores.*' => ['array'],
            'scores.*.*' => ['array'],
            'scores.*.*.surah_ayat' => ['nullable', 'string', 'max:255'],
            'scores.*.*.sabaq_amount' => ['nullable', 'string', 'max:255'],
            'scores.*.*.score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'scores.*.*.category' => ['nullable', 'string'],
            'scores.*.*.notes' => ['nullable', 'string'],
        ]);

        $members = $halaqah->activeMembers()->get()->keyBy('id');

        foreach ($validated['scores'] ?? [] as $memberId => $weeksData) {
            if (! $members->has($memberId)) {
                continue;
            }

            $member = $members->get($memberId);

            foreach ($weeksData as $weekId => $data) {
                $sabaqBaris = $parser->parseToBaris($data['sabaq_amount'] ?? null);

                // Skip if everything is empty
                if (empty($data['surah_ayat']) && empty($data['sabaq_amount']) && empty($data['score']) && empty($data['notes'])) {
                    // We might want to delete if it was previously filled, but for now we skip or create empty
                    // Let's create/update anyway to allow clearing data
                }

                $scoreRecord = TahfidzWeeklyScore::withTrashed()->updateOrCreate(
                    [
                        'tahfidz_halaqah_member_id' => $memberId,
                        'tahfidz_week_id' => $weekId,
                    ],
                    [
                        'tahfidz_halaqah_id' => $halaqah->id,
                        'student_id' => $member->student_id,
                        'surah_ayat' => $data['surah_ayat'] ?? null,
                        'sabaq_amount' => $data['sabaq_amount'] ?? null,
                        'sabaq_baris' => $sabaqBaris,
                        'score' => $data['score'] ?? null,
                        'category' => $data['category'] ?? 'sabaq',
                        'notes' => $data['notes'] ?? null,
                        'input_by' => $request->user()->id,
                        'input_at' => now(),
                        'status' => 'draft',
                        'deleted_at' => null, // Restore if deleted
                    ]
                );
            }
        }

        foreach ($members as $member) {
            $calculator->recalculateMonthlyRecaps($member);
        }

        return redirect()->back()->with('status', 'Data pekanan berhasil disimpan.');
    }

    public function updateSingle(Request $request, TahfidzHalaqah $halaqah, TahfidzSabaqParser $parser, TahfidzScoreCalculator $calculator)
    {
        $validated = $request->validate([
            'week_id' => ['required', 'exists:tahfidz_weeks,id'],
            'member_id' => ['required', 'exists:tahfidz_halaqah_members,id'],
            'surah_ayat' => ['nullable', 'string', 'max:255'],
            'sabaq_amount' => ['nullable', 'string', 'max:255'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'category' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $member = $halaqah->activeMembers()->where('id', $validated['member_id'])->firstOrFail();
        
        $sabaqBaris = $parser->parseToBaris($validated['sabaq_amount'] ?? null);

        if (empty($validated['surah_ayat']) && empty($validated['sabaq_amount']) && empty($validated['score']) && empty($validated['notes'])) {
            TahfidzWeeklyScore::where([
                'tahfidz_halaqah_member_id' => $member->id,
                'tahfidz_week_id' => $validated['week_id'],
            ])->delete();
        } else {
            TahfidzWeeklyScore::withTrashed()->updateOrCreate(
                [
                    'tahfidz_halaqah_member_id' => $member->id,
                    'tahfidz_week_id' => $validated['week_id'],
                ],
                [
                    'tahfidz_halaqah_id' => $halaqah->id,
                    'student_id' => $member->student_id,
                    'surah_ayat' => $validated['surah_ayat'] ?? null,
                    'sabaq_amount' => $validated['sabaq_amount'] ?? null,
                    'sabaq_baris' => $sabaqBaris,
                    'score' => $validated['score'] ?? null,
                    'category' => $validated['category'] ?? 'sabaq',
                    'notes' => $validated['notes'] ?? null,
                    'input_by' => $request->user()->id,
                    'input_at' => now(),
                    'status' => 'draft',
                    'deleted_at' => null, // Restore if deleted
                ]
            );
        }

        $calculator->recalculateMonthlyRecaps($member);

        return response()->json(['success' => true]);
    }

    public function uasIndex(Request $request, TahfidzHalaqah $halaqah): View
    {
        $halaqah->load(['academicTerm.academicYear', 'activeMembers.student']);

        $categories = TahfidzUasCategory::where('academic_term_id', $halaqah->academic_term_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $days = TahfidzUasDay::where('academic_term_id', $halaqah->academic_term_id)
            ->where('is_active', true)
            ->orderBy('day_number')
            ->get();

        $members = $halaqah->activeMembers()->with('student')->orderBy('sort_order')->orderBy('student_id')->get();

        $scores = TahfidzUasScore::where('academic_term_id', $halaqah->academic_term_id)
            ->whereIn('student_id', $members->pluck('student_id'))
            ->get()
            ->keyBy(fn ($s) => $s->student_id.'-'.$s->tahfidz_uas_day_id.'-'.$s->tahfidz_uas_category_id);

        return view('guru.tahfidz.uas', compact('halaqah', 'categories', 'days', 'members', 'scores'));
    }

    public function uasUpdate(Request $request, TahfidzHalaqah $halaqah, TahfidzUasCalculator $calculator): RedirectResponse
    {
        $validated = $request->validate([
            'scores' => ['array'],
            'scores.*' => ['array'],
            'scores.*.*' => ['array'],
            'scores.*.*.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $categories = TahfidzUasCategory::where('academic_term_id', $halaqah->academic_term_id)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $days = TahfidzUasDay::where('academic_term_id', $halaqah->academic_term_id)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $members = $halaqah->activeMembers()->with('student')->get()->keyBy('student_id');

        foreach ($validated['scores'] ?? [] as $studentId => $dayScores) {
            if (! $members->has($studentId)) {
                continue;
            }

            foreach ($dayScores as $dayId => $categoryScores) {
                if (! $days->has($dayId)) {
                    continue;
                }

                foreach ($categoryScores as $categoryId => $score) {
                    if (! $categories->has($categoryId)) {
                        continue;
                    }

                    TahfidzUasScore::updateOrCreate(
                        [
                            'tahfidz_uas_day_id' => $dayId,
                            'tahfidz_uas_category_id' => $categoryId,
                            'student_id' => $studentId,
                        ],
                        [
                            'academic_term_id' => $halaqah->academic_term_id,
                            'tahfidz_halaqah_id' => $halaqah->id,
                            'score' => $score,
                            'input_by' => $request->user()->id,
                        ]
                    );
                }
            }

            // Recalculate UAS result for this student
            $calculator->calculateForStudent($halaqah->academic_term_id, (int) $studentId);
        }

        return redirect()->back()->with('status', 'Nilai UAS berhasil disimpan dan dihitung ulang.');
    }
}