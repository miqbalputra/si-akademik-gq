<?php

namespace App\Http\Controllers;

use App\Models\ClassroomTerm;
use App\Models\DiniyyahLedgerSnapshot;
use App\Services\DiniyyahAssessmentAccess;
use App\Services\DiniyyahLedgerAccess;
use App\Services\DiniyyahLedgerWorkflow;
use App\Services\ReportCardPrintService;
use App\Services\TahfidzReportSources;
use App\Services\TahfidzReportWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class GuruReportWorkflowController extends Controller
{
    public function __invoke(Request $request, DiniyyahAssessmentAccess $assessments, DiniyyahLedgerAccess $homerooms): View
    {
        $user = $request->user();
        $classroomId = $request->validate(['classroom_term_id' => ['nullable', 'integer', 'min:1']])['classroom_term_id'] ?? null;
        $ownedSubjects = $assessments->classSubjects($user);
        $homeroomClasses = $homerooms->classrooms($user)->pluck('id');
        $classrooms = ClassroomTerm::query()
            ->where(fn ($classes) => $classes->whereIn('id', $homeroomClasses)
                ->orWhereIn('id', (clone $ownedSubjects)->select('classroom_term_id')))
            ->with('academicTerm.academicYear')->orderByDesc('academic_term_id')->orderBy('name')->get();
        $classroom = $classroomId === null ? null : $classrooms->find($classroomId);
        abort_if($classroomId !== null && ! $classroom, 403);
        $canInputTahfidz = $user->teacher?->status === 'active' && in_array($user->teacher?->gender, ['male', 'female'], true);

        $details = [];
        if ($classroom) {
            $ownSubjects = $ownedSubjects->where('classroom_term_id', $classroom->id)->with('subject', 'assessmentSets.classSubject.subject')->get();
            $isHomeroom = $homeroomClasses->contains($classroom->id);
            $tahfidzClassLink = $canInputTahfidz && app(TahfidzReportWorkflow::class)->enrollments($user, $classroom->academic_term_id)
                ->where('classroom_term_id', $classroom->id)->exists();
            $details = compact('ownSubjects', 'isHomeroom', 'tahfidzClassLink');
            if ($isHomeroom) {
                $details += $this->homeroomProgress($classroom);
            } elseif ($ownSubjects->every(fn ($subject) => $subject->assessmentSets->isNotEmpty()
                && $subject->assessmentSets->every(fn ($set) => $set->status === 'validated' && ! $set->missingExamName()))) {
                $details['step'] = 3;
            }
        }

        return view('guru.report-workflow', compact('classrooms', 'classroom', 'canInputTahfidz', 'details'));
    }

    private function homeroomProgress(ClassroomTerm $classroom): array
    {
        $snapshot = DiniyyahLedgerSnapshot::where('classroom_term_id', $classroom->id)
            ->where('academic_term_id', $classroom->academic_term_id)->first();
        if ($snapshot && in_array($snapshot->status, ['locked', 'published'], true)) {
            $print = app(ReportCardPrintService::class);
            $cards = $print->classCards($snapshot);
            $expected = $snapshot->rows()->whereHas('classEnrollment.student', fn ($q) => $q->where('status', 'active'))->count();
            $missingReports = max(0, $expected - $cards->count());
            $combined = ($snapshot->snapshot_data['layout_version'] ?? '') === 'combined-v1';
            $f4Reports = $combined ? $cards->filter(fn ($card) => $print->data($card) !== null) : collect();
            $incompleteReports = $f4Reports->filter(fn ($card) => $print->readiness($card) !== [])->count();
            $archiveReports = $cards->count() - $f4Reports->count();
            $unpublished = $cards->where('status', '!=', 'published')->count();

            return compact('snapshot', 'missingReports', 'incompleteReports', 'archiveReports', 'unpublished')
                + ['step' => $missingReports || $incompleteReports ? 4 : 5, 'frozen' => true];
        }

        $enrollments = $classroom->enrollments()->where('status', 'active')->forVisibleStudents()->get(['id', 'student_id']);
        $enrollmentIds = $enrollments->modelKeys();
        $subjects = $classroom->diniyyahClassSubjects()->where('is_active', true)->where('appears_on_ledger', true)
            ->with('subject', 'assessmentSets.results', 'assessmentSets.classSubject.subject')->get();
        $sets = $subjects->flatMap->assessmentSets->where('appears_on_ledger', true);
        $withoutSet = $subjects->filter(fn ($subject) => $subject->assessmentSets->isEmpty())
            ->map(fn ($subject) => $subject->subject?->name ?? 'Mapel kelas')->values();
        $pendingSets = $sets->filter(fn ($set) => $set->status !== 'validated' || $set->missingExamName()
            || $set->results->whereIn('class_enrollment_id', $enrollmentIds)->where('is_complete', true)->whereNotNull('final_score')->count() !== $enrollments->count()
            || ($classroom->academicTerm->kkm_workflow_enabled && $set->kkm_revision_id === null))->values();
        $tahfidz = app(TahfidzReportSources::class);
        $sources = $tahfidz->load($classroom, $enrollments->pluck('student_id'));
        $pendingTahfidz = $enrollments->reject(fn ($enrollment) => $tahfidz->resolve($sources, $enrollment->student_id)['ready'])->count();
        $inputsReady = $enrollments->isNotEmpty() && $sets->isNotEmpty() && $pendingSets->isEmpty() && $withoutSet->isEmpty() && $pendingTahfidz === 0;
        $ledgerReadiness = $snapshot ? app(DiniyyahLedgerWorkflow::class)->readiness($snapshot) : null;
        $ledgerIssue = $ledgerReadiness['blocking_reason'] ?? null;

        return compact('snapshot', 'enrollments', 'sets', 'pendingSets', 'withoutSet', 'pendingTahfidz', 'inputsReady', 'ledgerIssue', 'ledgerReadiness')
            + ['step' => $inputsReady ? 3 : 2, 'frozen' => false];
    }
}
