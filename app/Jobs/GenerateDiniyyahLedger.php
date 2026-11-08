<?php

namespace App\Jobs;

use App\Models\ClassroomTerm;
use App\Models\User;
use App\Services\DiniyyahLedgerAccess;
use App\Services\DiniyyahLedgerGenerator;
use DomainException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDiniyyahLedger implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $classroomTermId,
        public ?int $generatedBy = null,
    ) {}

    public function handle(DiniyyahLedgerGenerator $generator): void
    {
        $classroomTerm = ClassroomTerm::findOrFail($this->classroomTermId);
        $actor = User::find($this->generatedBy);
        if (! $actor || ! app(DiniyyahLedgerAccess::class)->allows($actor, $classroomTerm->id)) {
            return;
        }

        try {
            $snapshot = $generator->generate($classroomTerm, $this->generatedBy, true);

            Log::info('Leger diniyyah generated via queue', [
                'snapshot_id' => $snapshot->id,
                'classroom_term_id' => $this->classroomTermId,
            ]);
        } catch (DomainException $e) {
            Log::warning('Leger generation skipped', [
                'classroom_term_id' => $this->classroomTermId,
                'reason' => $e->getMessage(),
            ]);
        }
    }
}
