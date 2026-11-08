<?php

namespace App\Http\Controllers;

use App\Jobs\ExportDiniyyahLedgerExcel;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\ReportExportRequest;
use App\Services\DiniyyahLedgerAccess;
use App\Services\DiniyyahLedgerGenerator;
use App\Services\DiniyyahLedgerWorkflow;
use App\Services\Exports\DiniyyahLedgerExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiniyyahLedgerController extends Controller
{
    public function __construct(private DiniyyahLedgerAccess $access) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasRole('guru'), 403);
        $classrooms = $this->access->classrooms($request->user())->with('academicTerm.academicYear')->latest('academic_term_id')->get();
        $snapshots = DiniyyahLedgerSnapshot::whereIn('classroom_term_id', $classrooms->pluck('id'))->get()->keyBy('classroom_term_id');

        return view('diniyyah.ledger.index', compact('classrooms', 'snapshots'));
    }

    public function generate(Request $request, ClassroomTerm $classroomTerm, DiniyyahLedgerGenerator $generator): RedirectResponse
    {
        abort_unless($this->access->allows($request->user(), $classroomTerm->id), 403);

        try {
            $snapshot = $generator->generate($classroomTerm, $request->user()->id, true);
        } catch (\DomainException $exception) {
            return back()->withErrors(['ledger' => $exception->getMessage()]);
        }

        return redirect()->route('diniyyah.ledger.show', $snapshot);
    }

    public function show(Request $request, DiniyyahLedgerSnapshot $snapshot): View
    {
        abort_unless($this->access->allows($request->user(), $snapshot->classroom_term_id), 403);

        $snapshot->load(['classroomTerm', 'academicTerm.academicYear', 'rows.cells', 'rows.classEnrollment.student']);
        $columns = collect($snapshot->snapshot_data['columns'] ?? []);
        $summary = $snapshot->snapshot_data['summary'] ?? [];
        $issues = collect($snapshot->snapshot_data['issues'] ?? []);

        $visibleRows = $snapshot->rows
            ->filter(fn ($row): bool => $row->classEnrollment?->student?->status === 'active')
            ->values();
        $visibleEnrollmentIds = $visibleRows->pluck('class_enrollment_id')->map(fn ($id) => (int) $id)->all();
        $issues = $issues
            ->reject(fn (array $issue): bool => isset($issue['class_enrollment_id'])
                && ! in_array((int) $issue['class_enrollment_id'], $visibleEnrollmentIds, true))
            ->values();
        $combined = ($snapshot->snapshot_data['layout_version'] ?? '') === 'combined-v1';
        $completeRows = $visibleRows->filter(fn ($row): bool => $combined ? $row->cells->firstWhere('column_key', 'combined_total')?->value_numeric !== null : $row->total_diniyyah_score !== null)->count();
        $snapshot->setRelation('rows', $visibleRows);
        $summary['total_students'] = $visibleRows->count();
        $summary['complete_rows'] = $completeRows;
        $summary['incomplete_rows'] = $visibleRows->count() - $completeRows;
        $summary['blocking_issues'] = $issues->where('level', 'blocking')->count();
        $exports = ReportExportRequest::where('requested_by', $request->user()->id)->where('export_type', 'diniyyah_ledger_excel')->where('filters->snapshot_id', $snapshot->id)->latest('id')->limit(5)->get();

        return view('diniyyah.ledger.show', compact('snapshot', 'columns', 'summary', 'issues', 'exports'));
    }

    /**
     * Export a ledger snapshot as an Excel-compatible file.
     *
     * For large ledgers, this dispatches a queue job and notifies the user.
     * For normal sizes, it generates synchronously.
     */
    public function exportExcel(Request $request, DiniyyahLedgerSnapshot $snapshot, DiniyyahLedgerExporter $exporter): StreamedResponse|RedirectResponse
    {
        abort_unless($this->access->allows($request->user(), $snapshot->classroom_term_id), 403);

        $includeArchivedStudents = false;
        $studentCount = $includeArchivedStudents
            ? $snapshot->rows()->count()
            : $snapshot->rows()->whereHas('classEnrollment.student', fn ($query) => $query->where('status', 'active'))->count();

        // For large ledgers (30+ students), use queue
        if ($studentCount > 30 && config('queue.default') !== 'sync') {
            $exportRequest = ReportExportRequest::create([
                'requested_by' => $request->user()->id,
                'export_type' => 'diniyyah_ledger_excel',
                'panel_key' => 'diniyyah',
                'filters' => ['snapshot_id' => $snapshot->id],
                'status' => 'queued',
            ]);

            ExportDiniyyahLedgerExcel::dispatch($exportRequest->id);

            return redirect()
                ->route('diniyyah.ledger.show', $snapshot)
                ->with('status', 'Export Excel leger sedang diproses. File akan tersedia saat selesai.');
        }

        // Synchronous export for normal sizes
        $content = $exporter->export($snapshot->id, $includeArchivedStudents);
        $filename = 'Leger-Diniyyah-'.str()->slug($snapshot->classroomTerm?->name ?? 'export').'.xlsx';

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function finalize(Request $request, DiniyyahLedgerSnapshot $snapshot, DiniyyahLedgerWorkflow $workflow): RedirectResponse
    {
        abort_unless($this->access->allows($request->user(), $snapshot->classroom_term_id), 403);
        try {
            $workflow->lock($snapshot, $request->user());
        } catch (\DomainException|ValidationException $exception) {
            return back()->withErrors(['ledger' => $exception->getMessage()]);
        }

        return back()->with('status', 'Leger dikunci. Nilai sumber Diniyyah tidak dapat diubah.');
    }

    public function exportPdf(Request $request, DiniyyahLedgerSnapshot $snapshot)
    {
        abort_unless($this->access->allows($request->user(), $snapshot->classroom_term_id), 403);
        $snapshot->load(['classroomTerm', 'academicTerm.academicYear', 'rows.cells', 'rows.classEnrollment.student']);
        $snapshot->setRelation('rows', $snapshot->rows->filter(fn ($row) => $row->classEnrollment?->student?->status === 'active')->values());

        return Pdf::loadView('diniyyah.ledger.pdf', ['snapshot' => $snapshot, 'columns' => collect($snapshot->snapshot_data['columns'] ?? [])])
            ->setPaper('a3', 'landscape')->download('leger-kelas.pdf')->withHeaders(['Cache-Control' => 'private, no-store']);
    }

    public function downloadExport(Request $request, ReportExportRequest $exportRequest)
    {
        abort_unless($exportRequest->requested_by === $request->user()->id && $exportRequest->export_type === 'diniyyah_ledger_excel', 403);
        $snapshot = DiniyyahLedgerSnapshot::findOrFail($exportRequest->filters['snapshot_id'] ?? null);
        abort_unless($this->access->allows($request->user(), $snapshot->classroom_term_id), 403);
        abort_unless($exportRequest->status === 'completed' && $exportRequest->file_path && Storage::disk('local')->exists($exportRequest->file_path), 404);

        return Storage::disk('local')->download($exportRequest->file_path, 'leger-kelas.xlsx', ['Cache-Control' => 'private, no-store']);
    }
}
