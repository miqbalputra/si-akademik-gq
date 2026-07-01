<?php

namespace App\Jobs;

use App\Models\ReportCard;
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

        $pdf = Pdf::loadView('report-cards.print', compact('reportCard'))
            ->setPaper('a4');

        $filename = "rapor/rapor-{$reportCard->id}-".now()->format('YmdHis').'.pdf';
        Storage::disk('local')->put($filename, $pdf->output());

        $reportCard->snapshots()->latest()->first()?->update([
            'pdf_path' => $filename,
        ]);

        Log::info('Report card PDF generated via queue', [
            'report_card_id' => $this->reportCardId,
            'file' => $filename,
        ]);
    }
}