<?php

namespace App\Jobs;

use App\Models\ReportCard;
use App\Services\ReportCardPrintService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateReportCardPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $reportCardId,
    ) {}

    public function handle(): void
    {
        $reportCard = ReportCard::with([
            'academicTerm.academicYear',
            'classroomTerm',
            'student',
            'lines',
            'attendance',
            'signatures',
        ])->findOrFail($this->reportCardId);

        $targetSnapshot = $reportCard->snapshots()->latest('id')->first();
        $service = app(ReportCardPrintService::class);
        $content = $service->data($reportCard)
            ? $service->pdf([$reportCard])
            : Pdf::loadView('report-cards.print', compact('reportCard'))->setPaper([0, 0, 215.9 * 72 / 25.4, 330.2 * 72 / 25.4])->output();

        $filename = "rapor/rapor-{$reportCard->id}-".now()->format('YmdHis').'.pdf';
        Storage::disk('local')->put($filename, $content);

        $targetSnapshot?->update([
            'pdf_path' => $filename,
        ]);

        Log::info('Report card PDF generated via queue', [
            'report_card_id' => $this->reportCardId,
            'file' => $filename,
        ]);
    }
}
