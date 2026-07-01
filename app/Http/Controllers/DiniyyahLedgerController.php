<?php

namespace App\Http\Controllers;

use App\Jobs\ExportDiniyyahLedgerExcel;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\ReportExportRequest;
use App\Services\DiniyyahLedgerGenerator;
use App\Services\Exports\DiniyyahLedgerExporter;
use App\Services\ReportCardBulkWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiniyyahLedgerController extends Controller
{
    public function generate(Request $request, ClassroomTerm $classroomTerm, DiniyyahLedgerGenerator $generator): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'kabag_diniyyah']), 403);

        $snapshot = $generator->generate($classroomTerm, $request->user()->id);

        return redirect()->route('diniyyah.ledger.show', $snapshot);
    }

    public function show(Request $request, DiniyyahLedgerSnapshot $snapshot, ReportCardBulkWorkflow $reportCardBulkWorkflow): View
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']), 403);

        $snapshot->load(['classroomTerm', 'academicTerm.academicYear', 'rows.cells']);
        $columns = collect($snapshot->snapshot_data['columns'] ?? []);
        $summary = $snapshot->snapshot_data['summary'] ?? [];
        $issues = collect($snapshot->snapshot_data['issues'] ?? []);
        $reportCardSummary = $reportCardBulkWorkflow->summaryForSnapshot($snapshot);

        return view('diniyyah.ledger.show', compact('snapshot', 'columns', 'summary', 'issues', 'reportCardSummary'));
    }

    /**
     * Export a ledger snapshot as an Excel-compatible file.
     *
     * For large ledgers, this dispatches a queue job and notifies the user.
     * For normal sizes, it generates synchronously.
     */
    public function exportExcel(Request $request, DiniyyahLedgerSnapshot $snapshot, DiniyyahLedgerExporter $exporter): StreamedResponse|RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']), 403);

        $studentCount = $snapshot->rows()->count();

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
        $content = $exporter->export($snapshot->id);
        $filename = 'Leger-Diniyyah-' . str()->slug($snapshot->classroomTerm?->name ?? 'export') . '.xls';

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}