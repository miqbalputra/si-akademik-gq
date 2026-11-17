<?php

namespace App\Services;

use App\Models\DiniyyahLedgerSnapshot;
use App\Models\ReportCard;
use App\Models\User;
use DomainException;

/** Only insert missing cards; automatic processing never refreshes old cards. */
class ReportCardAutomation
{
    public function refreshRevision(ReportCard $card, User $user): ReportCard
    {
        if (! $user->hasAnyRole(['admin', 'kabag_diniyyah'])) {
            throw new DomainException('Hanya pengelola yang dapat menyegarkan revisi rapor.');
        }

        return app(DiniyyahKkmResolver::class)->withClassLocks($card->classroomTerm, function () use ($card, $user) {
            $card = ReportCard::lockForUpdate()->findOrFail($card->id);
            if ($card->status !== 'draft' || $card->revisionLogs()->latest('id')->first()?->action !== 'opened') {
                throw new DomainException('Buka revisi rapor dengan alasan tercatat terlebih dahulu.');
            }
            $ledger = DiniyyahLedgerSnapshot::where('classroom_term_id', $card->classroom_term_id)->where('academic_term_id', $card->academic_term_id)->firstOrFail();
            $row = $ledger->rows()->where('class_enrollment_id', $card->class_enrollment_id)->firstOrFail();
            $updated = app(ReportCardGenerator::class)->generateFromLedgerRow($ledger, $row, $user->id, $user->hasRole('admin'));
            activity('report_cards')->performedOn($updated)->causedBy($user)->log('report_card_revision_scores_refreshed');

            return $updated;
        });
    }

    public function createMissing(DiniyyahLedgerSnapshot $snapshot, ?int $userId): int
    {
        return app(DiniyyahKkmResolver::class)->withClassLocks($snapshot->classroomTerm, function () use ($snapshot, $userId) {
            $snapshot = DiniyyahLedgerSnapshot::with('rows.cells', 'rows.classEnrollment.student')->lockForUpdate()->findOrFail($snapshot->id);
            if (! in_array($snapshot->status, ['locked', 'published'], true)) {
                throw new DomainException('Kunci leger final sebelum membuat rapor.');
            }
            $existing = ReportCard::where('academic_term_id', $snapshot->academic_term_id)
                ->where('report_type', 'diniyyah')->pluck('class_enrollment_id');
            $count = 0;
            foreach ($snapshot->rows as $row) {
                if ($existing->contains($row->class_enrollment_id) || $row->classEnrollment?->student?->status !== 'active') {
                    continue;
                }
                app(ReportCardGenerator::class)->generateFromLedgerRow($snapshot, $row, $userId);
                $count++;
            }

            return $count;
        });
    }
}
