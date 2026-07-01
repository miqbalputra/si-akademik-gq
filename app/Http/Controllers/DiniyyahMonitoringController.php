<?php

namespace App\Http\Controllers;

use App\Models\DiniyyahAssessmentSet;
use App\Services\DiniyyahAssessmentWorkflow;
use App\Services\DiniyyahInputProgressService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DiniyyahMonitoringController extends Controller
{
    public function index(Request $request, DiniyyahInputProgressService $progressService): View
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']), 403);

        $summaries = $progressService->summaries();
        $classrooms = $summaries->pluck('classroom_name')->filter()->unique()->sort()->values();
        $subjects = $summaries->pluck('subject_name')->filter()->unique()->sort()->values();
        $statuses = $summaries->pluck('status')->filter()->unique()->sort()->values();

        $summaries = $summaries
            ->when($request->filled('classroom'), fn ($items) => $items->where('classroom_name', $request->string('classroom')->toString()))
            ->when($request->filled('subject'), fn ($items) => $items->where('subject_name', $request->string('subject')->toString()))
            ->when($request->filled('status'), fn ($items) => $items->where('status', $request->string('status')->toString()))
            ->when($request->boolean('needs_attention'), fn ($items) => $items->filter(
                fn (array $summary): bool => $summary['incomplete_students'] > 0 || in_array($summary['status'], ['draft', 'active', 'needs_revision', 'submitted'], true)
            ))
            ->values();

        return view('diniyyah.monitoring.index', [
            'summaries' => $summaries,
            'classrooms' => $classrooms,
            'subjects' => $subjects,
            'statuses' => $statuses,
        ]);
    }

    public function approve(Request $request, DiniyyahAssessmentSet $assessmentSet, DiniyyahAssessmentWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'kabag_diniyyah']), 403);
        abort_unless($assessmentSet->status === 'submitted', 403);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $workflow->approve($assessmentSet, $request->user(), $validated['notes'] ?? null);

        return redirect()
            ->route('diniyyah.monitoring.index')
            ->with('status', 'Nilai berhasil divalidasi.');
    }

    public function requestRevision(Request $request, DiniyyahAssessmentSet $assessmentSet, DiniyyahAssessmentWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'kabag_diniyyah']), 403);
        abort_unless(in_array($assessmentSet->status, ['submitted', 'validated'], true), 403);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $workflow->requestRevision($assessmentSet, $request->user(), $validated['notes'] ?? null);

        return redirect()
            ->route('diniyyah.monitoring.index')
            ->with('status', 'Nilai dikembalikan untuk revisi.');
    }
}
