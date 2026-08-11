<?php

namespace App\Services;

use App\Models\TahfidzHalaqah;
use App\Models\TahfidzSemesterRecap;
use App\Models\TahfidzValidation;
use App\Models\User;
use App\Services\NotificationDispatcher;
use DomainException;

class TahfidzWorkflow
{
    public function submitHalaqah(TahfidzHalaqah $halaqah, User $user): void
    {
        $halaqah->weeklyScores()->where('status', 'draft')->update([
            'status' => 'submitted',
        ]);

        $this->notifySubmit($halaqah, $user);
    }

    public function approveHalaqah(TahfidzHalaqah $halaqah, User $validator, ?string $notes = null): TahfidzValidation
    {
        $halaqah->weeklyScores()->whereIn('status', ['draft', 'submitted'])->update([
            'status' => 'validated',
        ]);

        $validation = TahfidzValidation::create([
            'tahfidz_halaqah_id' => $halaqah->id,
            'validated_by' => $validator->id,
            'status' => 'approved',
            'validated_at' => now(),
            'notes' => $notes,
        ]);

        $this->notifyApproved($halaqah, $validator);

        return $validation;
    }

    public function requestRevisionHalaqah(TahfidzHalaqah $halaqah, User $validator, ?string $notes = null): TahfidzValidation
    {
        $halaqah->weeklyScores()->update(['status' => 'needs_revision']);

        $validation = TahfidzValidation::create([
            'tahfidz_halaqah_id' => $halaqah->id,
            'validated_by' => $validator->id,
            'status' => 'needs_revision',
            'validated_at' => now(),
            'notes' => $notes,
        ]);

        $this->notifyNeedsRevision($halaqah, $validator, $notes);

        return $validation;
    }

    public function lockSemesterRecap(TahfidzSemesterRecap $recap, User $user): void
    {
        if ($recap->locked_at !== null) {
            throw new DomainException('Rekap semester sudah dikunci.');
        }

        $recap->update([
            'status' => 'locked',
            'locked_at' => now(),
        ]);

        $this->notifyRecapLocked($recap, $user);
    }

    // ── Notifikasi ────────────────────────────────────────────────────────

    private function notifySubmit(TahfidzHalaqah $halaqah, User $user): void
    {
        $name = $halaqah->name ?: 'Halaqah';

        app(NotificationDispatcher::class)->dispatchToRole(
            'kabag_tahfidz',
            "Halaqah {$name} disubmit",
            "Nilai halaqah {$name} disubmit oleh {$user->name} dan menunggu validasi Anda.",
            'tahfidz_halaqah_submit',
            route('guru.tahfidz.show', $halaqah),
            'info',
        );
    }

    private function notifyApproved(TahfidzHalaqah $halaqah, User $validator): void
    {
        $name = $halaqah->name ?: 'Halaqah';

        app(NotificationDispatcher::class)->dispatchToHalaqahTeachers(
            $halaqah->id,
            "Halaqah {$name} divalidasi",
            "Nilai halaqah {$name} telah divalidasi oleh {$validator->name}.",
            'tahfidz_halaqah_approved',
            route('guru.tahfidz.show', $halaqah),
            'success',
        );
    }

    private function notifyNeedsRevision(TahfidzHalaqah $halaqah, User $validator, ?string $notes): void
    {
        $name = $halaqah->name ?: 'Halaqah';
        $body = "Nilai halaqah {$name} perlu direvisi oleh {$validator->name}.";
        if ($notes) {
            $body .= " Catatan: {$notes}";
        }

        app(NotificationDispatcher::class)->dispatchToHalaqahTeachers(
            $halaqah->id,
            "Halaqah {$name} perlu revisi",
            $body,
            'tahfidz_halaqah_approved',
            route('guru.tahfidz.show', $halaqah),
            'warning',
        );
    }

    private function notifyRecapLocked(TahfidzSemesterRecap $recap, User $user): void
    {
        $studentName = $recap->student?->name ?? 'santri';
        $predicate = $recap->sabaq_category ?? '-';
        $body = "Rekap semester tahfidz {$studentName} dikunci oleh {$user->name}. Predikat sabaq: {$predicate}.";

        $dispatcher = app(NotificationDispatcher::class);

        // Wali santri.
        if ($recap->student_id) {
            $dispatcher->dispatchToGuardiansOfStudent(
                $recap->student_id,
                'Rekap tahfidz anak Anda dikunci',
                "Rekap semester tahfidz anak Anda ({$studentName}) telah dikunci. Lihat detail di portal wali santri.",
                'tahfidz_recap_locked',
                route('wali.tahfidz'),
                'success',
            );
        }

        // Kabag tahfidz.
        $dispatcher->dispatchToRole(
            'kabag_tahfidz',
            'Rekap semester tahfidz dikunci',
            $body,
            'tahfidz_recap_locked',
            route('guru.tahfidz.show', $recap->tahfidz_halaqah_id),
            'info',
        );
    }
}