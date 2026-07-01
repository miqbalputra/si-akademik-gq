<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportCardPdf;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\ReportCard;
use App\Services\ReportCardBulkWorkflow;
use App\Services\ReportCardGenerator;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportCardController extends Controller
{
    public function generate(Request $request, DiniyyahLedgerSnapshot $snapshot, ReportCardGenerator $generator): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'kabag_diniyyah']), 403);

        $generator->generateFromLedgerSnapshot($snapshot, $request->user()->id);

        return redirect()->route('diniyyah.ledger.show', $snapshot);
    }

    public function lockFromLedgerSnapshot(Request $request, DiniyyahLedgerSnapshot $snapshot, ReportCardBulkWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'kabag_diniyyah']), 403);

        try {
            $result = $workflow->lockForSnapshot($snapshot, $request->user());

            return redirect()
                ->route('diniyyah.ledger.show', $snapshot)
                ->with('status', "{$result['locked']} rapor berhasil dikunci. {$result['skipped']} rapor dilewati.");
        } catch (DomainException $exception) {
            return redirect()
                ->route('diniyyah.ledger.show', $snapshot)
                ->withErrors(['report_cards' => $exception->getMessage()]);
        }
    }

    public function publishFromLedgerSnapshot(Request $request, DiniyyahLedgerSnapshot $snapshot, ReportCardBulkWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'kabag_diniyyah']), 403);

        try {
            $result = $workflow->publishForSnapshot($snapshot, $request->user());

            return redirect()
                ->route('diniyyah.ledger.show', $snapshot)
                ->with('status', "{$result['published']} rapor berhasil dipublish. {$result['skipped']} rapor dilewati.");
        } catch (DomainException $exception) {
            return redirect()
                ->route('diniyyah.ledger.show', $snapshot)
                ->withErrors(['report_cards' => $exception->getMessage()]);
        }
    }

    public function show(Request $request, ReportCard $reportCard): View
    {
        Gate::authorize('view', $reportCard);

        $reportCard->load(['academicTerm.academicYear', 'classroomTerm', 'student', 'lines', 'attendance', 'signatures']);

        return view('report-cards.show', compact('reportCard'));
    }

    public function print(Request $request, ReportCard $reportCard): View
    {
        Gate::authorize('view', $reportCard);

        $reportCard->load(['academicTerm.academicYear', 'classroomTerm', 'student', 'lines', 'attendance', 'signatures']);

        return view('report-cards.print', compact('reportCard'));
    }

    /**
     * Generate and download PDF for a single report card.
     *
     * If a pre-generated PDF exists (from queue), serve it directly.
     * Otherwise generate synchronously using DomPDF.
     */
    public function downloadPdf(Request $request, ReportCard $reportCard): Response|StreamedResponse
    {
        Gate::authorize('view', $reportCard);

        $reportCard->load(['academicTerm.academicYear', 'classroomTerm', 'student', 'lines', 'attendance', 'signatures']);

        $existingPdf = $reportCard->snapshots()
            ->whereNotNull('pdf_path')
            ->latest()
            ->first();

        if ($existingPdf && Storage::disk('local')->exists($existingPdf->pdf_path)) {
            return Storage::disk('local')->download(
                $existingPdf->pdf_path,
                "Rapor-{$reportCard->student?->nis}-{$reportCard->student?->name}.pdf"
            );
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('report-cards.print', compact('reportCard'))
            ->setPaper('a4');

        $filename = "Rapor-{$reportCard->student?->nis}-{$reportCard->student?->name}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Dispatch PDF generation job for a report card (async via queue).
     */
    public function generatePdf(Request $request, ReportCard $reportCard): RedirectResponse
    {
        Gate::authorize('view', $reportCard);

        GenerateReportCardPdf::dispatch($reportCard->id);

        return redirect()
            ->route('report-cards.show', $reportCard)
            ->with('status', 'Generate PDF rapor sedang diproses. File akan tersedia saat selesai.');
    }
}