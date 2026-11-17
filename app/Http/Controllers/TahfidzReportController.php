<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\ClassEnrollment;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\ReportCard;
use App\Models\User;
use App\Services\DiniyyahLedgerAccess;
use App\Services\TahfidzReportSources;
use App\Services\TahfidzReportWorkflow;
use Illuminate\Http\Request;

class TahfidzReportController extends Controller
{
    public function index(Request $request, TahfidzReportWorkflow $workflow)
    {
        $teacher = $request->user()->teacher;
        abort_unless($request->user()->hasRole('guru') && $teacher?->status === 'active' && in_array($teacher->gender, ['male', 'female'], true), 403, 'Lengkapi gender dan status guru melalui admin.');
        $terms = AcademicTerm::with('academicYear')->orderByDesc('is_active')->orderByDesc('id')->get();
        $termId = (int) ($request->query('academic_term_id') ?? $terms->first()?->id);
        $term = $terms->firstWhere('id', $termId);
        abort_unless($term, 404);
        $enrollments = $workflow->enrollments($request->user(), $termId)->with('student', 'classroomTerm')->orderBy('classroom_term_id')->orderBy('roll_number')->paginate(25)->withQueryString();
        $states = $this->states($enrollments->getCollection());

        return view('guru.tahfidz-report.index', compact('terms', 'term', 'enrollments', 'states'));
    }

    public function save(Request $request, ClassEnrollment $enrollment, TahfidzReportWorkflow $workflow)
    {
        abort_unless($workflow->enrollments($request->user(), $enrollment->academic_term_id)->whereKey($enrollment->id)->exists(), 403);
        $data = $request->validate([
            'juz_tested' => ['nullable', 'string', 'max:255'],
            'uas_score' => ['nullable', 'numeric', 'between:0,100'], 'sabaq_score' => ['nullable', 'numeric', 'between:0,100'],
            'sabqi_score' => ['nullable', 'numeric', 'between:0,100'], 'manzil_score' => ['nullable', 'numeric', 'between:0,100'],
            'version' => ['required', 'integer', 'min:0'], 'action' => ['required', 'in:draft,final'],
        ]);
        $workflow->save($request->user(), $enrollment, $data, $data['action'] === 'final', (int) $data['version']);

        return back()->with('status', 'Isian Tahfidz disimpan. Nilai final menunggu pemeriksaan wali kelas.');
    }

    public function monitor(Request $request, ClassroomTerm $classroom)
    {
        abort_unless(app(DiniyyahLedgerAccess::class)->allows($request->user(), $classroom->id), 403);
        $classroom->load('academicTerm.academicYear');
        $enrollments = $classroom->enrollments()->where('status', 'active')->whereHas('student', fn ($q) => $q->where('status', 'active'))->with('student', 'classroomTerm')->orderBy('roll_number')->get();
        $states = $this->states($enrollments);
        $users = User::with('teacher')->whereIn('id', collect($states)->pluck('entry.input_by')->filter())->get()->mapWithKeys(fn ($user) => [$user->id => $user->teacher?->name ?? $user->name]);

        return view('guru.tahfidz-report.monitor', compact('classroom', 'enrollments', 'states', 'users'));
    }

    public function review(Request $request, ClassEnrollment $enrollment, TahfidzReportWorkflow $workflow)
    {
        abort_unless(app(DiniyyahLedgerAccess::class)->allows($request->user(), $enrollment->classroom_term_id), 403);
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $workflow->review($request->user(), $enrollment, (int) $data['version']);

        return back()->with('status', 'Nilai final diperiksa. Segarkan leger agar memakai versi yang sudah diperiksa.');
    }

    private function states($enrollments): array
    {
        $states = [];
        foreach ($enrollments->groupBy('classroom_term_id') as $members) {
            $sources = app(TahfidzReportSources::class)->load($members->first()->classroomTerm, $members->pluck('student_id'));
            $locked = DiniyyahLedgerSnapshot::where('classroom_term_id', $members->first()->classroom_term_id)->whereIn('status', ['locked', 'published'])->exists()
                || ReportCard::where('classroom_term_id', $members->first()->classroom_term_id)->whereIn('status', ['locked', 'published'])->exists();
            foreach ($members as $enrollment) {
                $states[$enrollment->id] = app(TahfidzReportSources::class)->resolve($sources, $enrollment->student_id);
                $states[$enrollment->id]['locked'] = $locked;
            }
        }

        return $states;
    }
}
