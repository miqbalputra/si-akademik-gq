<?php

namespace App\Services;

use App\Models\ReportCard;
use App\Models\User;
use DomainException;

class ReportCardWorkflow
{
    public function lock(ReportCard $reportCard, User $user): void
    {
        if ($reportCard->status === 'published') {
            throw new DomainException('Rapor yang sudah published tidak bisa dikunci ulang.');
        }

        $reportCard->update([
            'status' => 'locked',
            'locked_at' => now(),
            'locked_by' => $user->id,
        ]);
    }

    public function publish(ReportCard $reportCard, User $user): void
    {
        if ($reportCard->status !== 'locked') {
            throw new DomainException('Rapor harus dikunci sebelum dipublish.');
        }

        $reportCard->update([
            'status' => 'published',
            'published_at' => now(),
            'published_by' => $user->id,
        ]);
    }
}
