<?php

namespace App\Services;

use App\Models\DiniyyahLedgerSnapshot;
use App\Models\User;
use App\Services\NotificationDispatcher;
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

        $this->notifyLocked($snapshot, $user);
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

        $this->notifyValidated($snapshot, $user);
    }

    // ── Notifikasi ────────────────────────────────────────────────────────

    private function notifyValidated(DiniyyahLedgerSnapshot $snapshot, User $user): void
    {
        $kelas = $snapshot->classroomTerm?->name ?? 'kelas';
        $dispatcher = app(NotificationDispatcher::class);
        $linkUrl = route('diniyyah.ledger.show', $snapshot);

        // Wali kelas kelas tsb.
        if ($snapshot->classroom_term_id) {
            $dispatcher->dispatchToHomeroomTeacher(
                $snapshot->classroom_term_id,
                "Leger {$kelas} divalidasi",
                "Leger kelas {$kelas} telah divalidasi oleh {$user->name}. Siap untuk dikunci.",
                'ledger_validated',
                $linkUrl,
                'success',
            );
        }

        // Kabag diniyyah (info).
        $dispatcher->dispatchToRole(
            'kabag_diniyyah',
            "Leger {$kelas} divalidasi",
            "Leger kelas {$kelas} divalidasi oleh {$user->name}.",
            'ledger_validated',
            $linkUrl,
            'info',
        );
    }

    private function notifyLocked(DiniyyahLedgerSnapshot $snapshot, User $user): void
    {
        $kelas = $snapshot->classroomTerm?->name ?? 'kelas';
        $dispatcher = app(NotificationDispatcher::class);
        $linkUrl = route('diniyyah.ledger.show', $snapshot);
        $body = "Leger kelas {$kelas} dikunci oleh {$user->name}. Perubahan nilai diniyyah untuk kelas ini dinonaktifkan.";

        // Kepala sekolah (info).
        $dispatcher->dispatchToRole(
            'kepala_sekolah',
            "Leger {$kelas} dikunci",
            $body,
            'ledger_locked',
            $linkUrl,
            'info',
        );

        // Kabag diniyyah.
        $dispatcher->dispatchToRole(
            'kabag_diniyyah',
            "Leger {$kelas} dikunci",
            $body,
            'ledger_locked',
            $linkUrl,
            'info',
        );

        // Wali kelas kelas tsb.
        if ($snapshot->classroom_term_id) {
            $dispatcher->dispatchToHomeroomTeacher(
                $snapshot->classroom_term_id,
                "Leger {$kelas} dikunci",
                $body,
                'ledger_locked',
                $linkUrl,
                'warning',
            );
        }

        // Guru pemilik nilai kelas tsb (nilai dibekukan).
        $assessmentSets = \App\Models\DiniyyahAssessmentSet::query()
            ->whereHas('classSubject', function ($q) use ($snapshot) {
                $q->where('classroom_term_id', $snapshot->classroom_term_id);
            })
            ->pluck('id');
        foreach ($assessmentSets as $setId) {
            $dispatcher->dispatchToSubjectTeachers(
                $setId,
                "Leger {$kelas} dikunci",
                $body.' Input nilai dinonaktifkan.',
                'ledger_locked',
                route('guru.diniyyah-scores.index'),
                'warning',
            );
        }
    }
}
