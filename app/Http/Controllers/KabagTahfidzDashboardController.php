<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\TahfidzHalaqah;
use App\Models\TahfidzHalaqahMember;
use App\Models\TasmiExaminerAssignment;
use App\Models\TasmiRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class KabagTahfidzDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'kabag_tahfidz']), 403);

        $term = AcademicTerm::query()->with('academicYear')->where('is_active', true)->latest('id')->first();
        $termId = $term?->id;
        $records = TasmiRecord::query()->when(
            $termId,
            fn ($query) => $query->where('academic_term_id', $termId),
            fn ($query) => $query->whereRaw('1 = 0'),
        );

        $predicateCounts = collect(TasmiRecord::predicateOptions())->mapWithKeys(
            fn (string $label, string $predicate) => [$predicate => (clone $records)->where('predicate', $predicate)->count()]
        )->all();

        return view('kabag.tahfidz.dashboard', [
            'term' => $term,
            'summary' => [
                'halaqahs' => $termId ? TahfidzHalaqah::query()->where('academic_term_id', $termId)->where('status', 'active')->count() : 0,
                'members' => $termId ? TahfidzHalaqahMember::query()->where('status', 'active')->whereHas('halaqah', fn ($query) => $query->where('academic_term_id', $termId))->count() : 0,
                'examiners' => $termId ? TasmiExaminerAssignment::query()->where('academic_term_id', $termId)->where('status', 'active')->count() : 0,
                'tasmi_records' => (clone $records)->count(),
                'predicates' => $predicateCounts,
            ],
            'recentRecords' => (clone $records)->with(['student', 'classroomTerm.classroom', 'examinerTeacher'])->latest('exam_date')->latest('id')->limit(8)->get(),
        ]);
    }
}
