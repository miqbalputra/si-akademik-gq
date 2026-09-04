<?php

namespace App\Jobs;

use App\Models\RppSyncEvent;
use App\Services\RppSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncRppSourceEntity implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public readonly string $entity, public readonly string $entityId, public readonly int $eventId) {}

    public function handle(RppSyncService $sync): void
    {
        try {
            $sync->syncEntity($this->entity, $this->entityId);
            RppSyncEvent::whereKey($this->eventId)->update(['status' => 'processed', 'processed_at' => now(), 'error' => null]);
        } catch (\Throwable $exception) {
            RppSyncEvent::whereKey($this->eventId)->update(['status' => 'failed', 'error' => (string) str($exception->getMessage())->limit(4000), 'processed_at' => now()]);
            throw $exception;
        }
    }
}
