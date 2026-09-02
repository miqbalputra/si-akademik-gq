<?php

namespace App\Jobs;

use App\Models\Rpp;
use App\Services\RppExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateRppExport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function __construct(public readonly int $rppId, public readonly string $type) {}

    public function handle(RppExportService $exports): void
    {
        $rpp = Rpp::find($this->rppId);
        if ($rpp) {
            $exports->export($rpp, $this->type);
        }
    }
}
