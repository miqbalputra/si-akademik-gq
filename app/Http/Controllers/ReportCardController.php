<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportCardPdf;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\ReportCard;
use App\Services\DiniyyahLedgerAccess;
use App\Services\ReportCardAutomation;
use App\Services\ReportCardBulkWorkflow;
use App\Services\ReportCardPrintOptions;
use App\Services\ReportCardPrintService;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportCardController extends Controller
{
    public function generate(Request $request, DiniyyahLedgerSnapshot $snapshot): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'kabag_diniyyah']) || app(DiniyyahLedgerAccess::class)->allows($request->user(), $snapshot->classroom_term_id), 403);

        try {
            $count = app(ReportCardAutomation::class)->createMissing($snapshot, $request->user()->id);
        } catch (DomainException $exception) {
            return back()->withErrors(['report_cards' => $exception->getMessage()]);
        }

        return back()->with('status', "{$count} rapor baru dibuat. Rapor yang sudah ada tetap utuh.");
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
        $this->recordAccess($request, $reportCard, 'viewed');

        $reportCard->load(['academicTerm.academicYear', 'classroomTerm', 'student', 'lines', 'attendance', 'signatures']);

        if ($printData = app(ReportCardPrintService::class)->data($reportCard)) {
            return view('report-cards.template-show', ['reportCard' => $reportCard, 'printData' => $printData, 'missing' => app(ReportCardPrintService::class)->readiness($reportCard)]);
        }

        return view('report-cards.show', compact('reportCard'));
    }

    public function print(Request $request, ReportCard $reportCard): View|RedirectResponse
    {
        Gate::authorize('view', $reportCard);
        $this->recordAccess($request, $reportCard, 'printed');

        $reportCard->load(['academicTerm.academicYear', 'classroomTerm', 'student', 'lines', 'attendance', 'signatures']);

        if (app(ReportCardPrintService::class)->data($reportCard)) {
            try {
                return view('report-cards.template-print', ['pages' => [app(ReportCardPrintService::class)->page($reportCard)]]);
            } catch (DomainException $exception) {
                return back()->withErrors(['print' => $exception->getMessage()]);
            }
        }

        return view('report-cards.print', compact('reportCard'));
    }

    /**
     * Generate and download PDF for a single report card.
     *
     * If a pre-generated PDF exists (from queue), serve it directly.
     * Otherwise generate synchronously using DomPDF.
     */
    public function downloadPdf(Request $request, ReportCard $reportCard): Response|StreamedResponse|RedirectResponse
    {
        Gate::authorize('view', $reportCard);
        $this->recordAccess($request, $reportCard, 'pdf_downloaded');

        $reportCard->load(['academicTerm.academicYear', 'classroomTerm', 'student', 'lines', 'attendance', 'signatures']);

        if (app(ReportCardPrintService::class)->data($reportCard)) {
            try {
                $content = app(ReportCardPrintService::class)->pdf([$reportCard]);

                return response($content, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="Rapor-'.$reportCard->id.'.pdf"', 'Cache-Control' => 'private, no-store']);
            } catch (DomainException $exception) {
                return back()->withErrors(['print' => $exception->getMessage()]);
            }
        }

        $existingPdf = $reportCard->snapshots()
            ->latest('id')
            ->first();

        if ($existingPdf?->pdf_path && Storage::disk('local')->exists($existingPdf->pdf_path)) {
            return Storage::disk('local')->download(
                $existingPdf->pdf_path,
                "Rapor-{$reportCard->student?->nis}-{$reportCard->student?->name}.pdf"
            );
        }

        $pdf = Pdf::loadView('report-cards.print', [
            'reportCard' => $reportCard,
            'isPdf' => true,
        ])
            ->setPaper([0, 0, 215.9 * 72 / 25.4, 330.2 * 72 / 25.4]);

        $filename = "Rapor-{$reportCard->student?->nis}-{$reportCard->student?->name}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Dispatch PDF generation job for a report card (async via queue).
     */
    public function generatePdf(Request $request, ReportCard $reportCard): RedirectResponse
    {
        Gate::authorize('view', $reportCard);
        $this->recordAccess($request, $reportCard, 'pdf_generation_requested');

        GenerateReportCardPdf::dispatch($reportCard->id);

        return redirect()
            ->route('report-cards.show', $reportCard)
            ->with('status', 'Generate PDF rapor sedang diproses. File akan tersedia saat selesai.');
    }

    public function index(Request $request): View
    {
        $classrooms = app(DiniyyahLedgerAccess::class)->classrooms($request->user())->with('academicTerm.academicYear')->latest('academic_term_id')->get();
        abort_unless($request->user()->hasRole('guru'), 403);
        $snapshots = DiniyyahLedgerSnapshot::whereIn('classroom_term_id', $classrooms->pluck('id'))->get()->keyBy('classroom_term_id');

        return view('report-cards.index', compact('classrooms', 'snapshots'));
    }

    public function classroom(Request $request, DiniyyahLedgerSnapshot $snapshot): View
    {
        $this->authorizeClass($request, $snapshot);
        $cards = $this->classCards($snapshot);
        $service = app(ReportCardPrintService::class);
        $readiness = $cards->mapWithKeys(fn ($card) => [$card->id => $service->readiness($card)]);
        $missingCount = $snapshot->rows()->whereHas('classEnrollment.student', fn ($q) => $q->where('status', 'active'))->count() - $cards->count();

        return view('report-cards.classroom', compact('snapshot', 'cards', 'readiness', 'missingCount'));
    }

    public function printClass(Request $request, DiniyyahLedgerSnapshot $snapshot): View|RedirectResponse
    {
        $this->authorizeClass($request, $snapshot);
        $cards = $this->classCards($snapshot);
        abort_if($cards->isEmpty(), 422, 'Belum ada rapor.');
        try {
            $this->ensureClassComplete($snapshot, $cards);
            $pages = $cards->map(fn ($card) => app(ReportCardPrintService::class)->page($card))->all();
        } catch (DomainException $exception) {
            return back()->withErrors(['print' => $exception->getMessage()]);
        }

        return view('report-cards.template-print', compact('pages'));
    }

    public function downloadClassPdf(Request $request, DiniyyahLedgerSnapshot $snapshot): Response|RedirectResponse
    {
        $this->authorizeClass($request, $snapshot);
        try {
            $cards = $this->classCards($snapshot);
            $this->ensureClassComplete($snapshot, $cards);
            $content = app(ReportCardPrintService::class)->pdf($cards->all());

            return response($content, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="Rapor-Kelas-'.$snapshot->classroom_term_id.'.pdf"', 'Cache-Control' => 'private, no-store']);
        } catch (DomainException $exception) {
            return back()->withErrors(['print' => $exception->getMessage()]);
        }
    }

    public function savePrintOptions(Request $request, ReportCard $reportCard, ReportCardPrintOptions $options): RedirectResponse
    {
        Gate::authorize('configurePrint', $reportCard);
        $data = $request->validate([
            'issue_date' => ['nullable', 'date_format:Y-m-d', function ($attribute, $value, $fail) use ($request, $reportCard) {
                $current = app(ReportCardPrintService::class)->data($reportCard)['options']['issue_date'] ?? null;
                if (! $request->user()->hasRole('admin') && $value !== $current) {
                    $fail('Tanggal rapor hanya ditentukan admin.');
                }
            }], 'issue_place' => ['required', 'string', 'max:80'],
            'homeroom_name' => ['required', 'string', 'max:100'], 'principal_name' => ['required', 'string', 'max:100'],
            'principal_title' => ['required', 'string', 'max:100'], 'note' => ['nullable', 'string', 'max:350'],
            'promotion_status' => ['nullable', 'in:promoted,retained,graduated'], 'next_class' => ['nullable', 'required_if:promotion_status,promoted', 'string', 'max:60'],
        ]);
        $options->save($reportCard, $data, $request->user());

        return back()->with('status', 'Pelengkap rapor disimpan. Nilai dan KKM tetap dari leger.');
    }

    private function authorizeClass(Request $request, DiniyyahLedgerSnapshot $snapshot): void
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']) || app(DiniyyahLedgerAccess::class)->allows($request->user(), $snapshot->classroom_term_id), 403);
    }

    private function classCards(DiniyyahLedgerSnapshot $snapshot): Collection
    {
        return ReportCard::where('academic_term_id', $snapshot->academic_term_id)->where('classroom_term_id', $snapshot->classroom_term_id)
            ->whereIn('class_enrollment_id', $snapshot->rows()->select('class_enrollment_id'))
            ->where('report_type', 'diniyyah')->whereHas('student', fn ($q) => $q->where('status', 'active'))
            ->with('student', 'classEnrollment')->get()->sortBy(fn ($card) => [$card->classEnrollment?->roll_number ?? PHP_INT_MAX, $card->student?->name])->values();
    }

    private function ensureClassComplete(DiniyyahLedgerSnapshot $snapshot, Collection $cards): void
    {
        $expected = $snapshot->rows()->whereHas('classEnrollment.student', fn ($q) => $q->where('status', 'active'))->pluck('class_enrollment_id');
        if ($expected->diff($cards->pluck('class_enrollment_id'))->isNotEmpty()) {
            throw new DomainException('Masih ada santri yang rapornya belum dibuat. Buat rapor yang belum ada sebelum mencetak seluruh kelas.');
        }
    }

    private function recordAccess(Request $request, ReportCard $reportCard, string $action): void
    {
        activity('report_cards')
            ->performedOn($reportCard)
            ->causedBy($request->user())
            ->withProperties([
                'action' => $action,
                'student_id' => $reportCard->student_id,
                'academic_term_id' => $reportCard->academic_term_id,
            ])
            ->log('report_card_'.$action);
    }
}
