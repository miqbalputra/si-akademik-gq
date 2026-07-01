<?php

namespace App\Jobs;

use App\Models\ReportExportRequest;
use App\Services\Exports\DiniyyahLedgerExporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExportDiniyyahLedgerExcel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $exportRequestId,
    ) {}

    public function handle(): void
    {
        $exportRequest = ReportExportRequest::findOrFail($this->exportRequestId);
        $exportRequest->markAsProcessing();

        try {
            $filters = $exportRequest->filters ?? [];
            $snapshotId = $filters['snapshot_id'] ?? null;

            $exporter = app(DiniyyahLedgerExporter::class);
            $content = $exporter->export($snapshotId);

            $filename = 'exports/leger-diniyyah-'.now()->format('YmdHis').'.xls';
            Storage::disk('local')->put($filename, $content);

            $exportRequest->markAsCompleted($filename);

            Log::info('Leger Excel export completed via queue', [
                'export_request_id' => $this->exportRequestId,
                'file' => $filename,
            ]);
        } catch (\Throwable $e) {
            $exportRequest->markAsFailed($e->getMessage());

            Log::error('Leger Excel export failed', [
                'export_request_id' => $this->exportRequestId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}