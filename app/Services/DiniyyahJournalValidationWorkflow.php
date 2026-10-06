<?php

namespace App\Services;

use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassJournalValidationLog;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class DiniyyahJournalValidationWorkflow
{
    public function validate(DiniyyahClassJournal $journal, User $user): void
    {
        DB::transaction(function () use ($journal, $user): void {
            $journal = DiniyyahClassJournal::query()->lockForUpdate()->findOrFail($journal->id);

            if ($journal->status !== 'draft') {
                throw new DomainException('Hanya jurnal draf yang dapat divalidasi.');
            }

            $performedAt = now();
            $journal->update([
                'status' => 'validated',
                'validated_by' => $user->id,
                'validated_at' => $performedAt,
                'validation_revoked_by' => null,
                'validation_revoked_at' => null,
                'validation_revocation_reason' => null,
            ]);

            $this->record($journal, $user, 'validated', null, $performedAt);
        });
    }

    public function revoke(DiniyyahClassJournal $journal, User $user, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('Alasan pembatalan validasi wajib diisi.');
        }

        DB::transaction(function () use ($journal, $user, $reason): void {
            $journal = DiniyyahClassJournal::query()->lockForUpdate()->findOrFail($journal->id);

            if ($journal->status !== 'validated') {
                throw new DomainException('Hanya jurnal tervalidasi yang dapat dibatalkan validasinya.');
            }

            $performedAt = now();
            $journal->update([
                'status' => 'draft',
                'validation_revoked_by' => $user->id,
                'validation_revoked_at' => $performedAt,
                'validation_revocation_reason' => $reason,
            ]);

            $this->record($journal, $user, 'validation_revoked', $reason, $performedAt);
        });
    }

    private function record(DiniyyahClassJournal $journal, User $user, string $action, ?string $reason, mixed $performedAt): void
    {
        DiniyyahClassJournalValidationLog::create([
            'diniyyah_class_journal_id' => $journal->id,
            'action' => $action,
            'performed_by' => $user->id,
            'reason' => $reason,
            'performed_at' => $performedAt,
        ]);
    }
}
