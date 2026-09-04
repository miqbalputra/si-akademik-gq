<?php

namespace App\Console\Commands;

use App\Services\RppSyncService;
use Illuminate\Console\Command;

class SyncRppSource extends Command
{
    protected $signature = 'rpp:sync-source';
    protected $description = 'Rekonsiliasi RPP dari Project RPP.';

    public function handle(RppSyncService $sync): int
    {
        if (! config('rpp_sync.enabled')) {
            $this->warn('Integrasi RPP belum diaktifkan.');
            return self::SUCCESS;
        }
        try {
            $report = $sync->reconcile();
            $this->info("RPP tersinkron: {$report['processed']}; konflik: {$report['conflicts']}.");
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
