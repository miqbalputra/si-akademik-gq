<?php

namespace App\Services;

use App\Models\DiniyyahLedgerSnapshot;
use App\Models\User;
use DomainException;

class DiniyyahLedgerWorkflow
{
    public function lock(DiniyyahLedgerSnapshot $snapshot, User $user): void
    {
        if ($snapshot->status === 'published') {
            throw new DomainException('Leger yang sudah published tidak bisa dikunci ulang.');
        }

        if (($snapshot->snapshot_data['summary']['blocking_issues'] ?? 0) > 0) {
            throw new DomainException('Leger masih memiliki masalah kelengkapan dan belum bisa dikunci.');
        }

        $snapshot->update([
            'status' => 'locked',
            'locked_at' => now(),
            'locked_by' => $user->id,
        ]);
    }

    public function validate(DiniyyahLedgerSnapshot $snapshot, User $user): void
    {
        if (in_array($snapshot->status, ['locked', 'published'], true)) {
            throw new DomainException('Leger terkunci tidak bisa divalidasi ulang.');
        }

        $snapshot->update([
            'status' => 'validated',
            'validated_at' => now(),
            'validated_by' => $user->id,
        ]);
    }
}
