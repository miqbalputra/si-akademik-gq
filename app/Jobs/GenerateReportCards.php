<?php

namespace App\Jobs;

use App\Models\DiniyyahLedgerSnapshot;
use App\Services\ReportCardGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateReportCards implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        public int $snapshotId,
        public ?int $generatedBy = null,
    ) {}

    public function handle(ReportCardGenerator $generator): void
    {
        $snapshot = DiniyyahLedgerSnapshot::findOrFail($this->snapshotId);

        $count = $generator->generateFromLedgerSnapshot($snapshot, $this->generatedBy);

        Log::info('Report cards generated via queue', [
            'snapshot_id' => $this->snapshotId,
            'count' => $count,
        ]);
    }
}