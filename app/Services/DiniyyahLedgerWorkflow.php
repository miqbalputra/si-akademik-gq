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
        app(DiniyyahKkmResolver::class)->withClassLocks($snapshot->classroomTerm, function () use ($snapshot, $user): void {
            $snapshot = DiniyyahLedgerSnapshot::whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
            $this->ensureKkmCurrent($snapshot);
            $this->lockOpenSnapshot($snapshot, $user);
        });
        $snapshot->refresh();
    }

    private function lockOpenSnapshot(DiniyyahLedgerSnapshot $snapshot, User $user): void
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
        $this->ensureKkmCurrent($snapshot);
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
    private function ensureKkmCurrent(DiniyyahLedgerSnapshot $snapshot): void
    {
        if (! $snapshot->classroomTerm?->academicTerm?->kkm_workflow_enabled) return;
        if ($snapshot->snapshot_data['kkm_stale'] ?? false) throw new DomainException('KKM berubah. Generate ulang leger.');
        $sets = \App\Models\DiniyyahAssessmentSet::where('appears_on_ledger', true)
            ->whereHas('classSubject', fn ($q) => $q->where('classroom_term_id', $snapshot->classroom_term_id)->where('is_active', true)->where('appears_on_ledger', true))->get();
        $columns = collect($snapshot->snapshot_data['columns'] ?? [])->where('source_type', 'diniyyah_assessment_set');
        if ($sets->isEmpty() || $sets->count() !== $columns->count()) throw new DomainException('Struktur leger berubah atau belum lengkap. Generate ulang leger.');
        foreach ($sets as $set) {
            $column = $columns->firstWhere('assessment_set_id', $set->id);
            $current = app(DiniyyahKkmResolver::class)->ensureApproved($set);
            if (($column['kkm_revision_id'] ?? null) !== $current['kkm_revision_id'] || $set->status !== 'validated') {
                throw new DomainException('Versi KKM atau validasi nilai berubah. Generate ulang leger.');
            }
        }
    }

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
