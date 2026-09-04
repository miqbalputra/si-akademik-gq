<?php

namespace App\Jobs;

use App\Services\RppSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReconcileRppSource implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function handle(RppSyncService $sync): void
    {
        $sync->reconcile();
    }
}
