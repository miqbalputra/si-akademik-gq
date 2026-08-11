<?php

namespace App\Services;

use App\Models\ReportCard;
use App\Models\User;
use App\Services\NotificationDispatcher;
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

        $this->notifyLocked($reportCard, $user);
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

        $this->notifyPublished($reportCard, $user);
    }

    // ── Notifikasi ────────────────────────────────────────────────────────

    private function notifyLocked(ReportCard $reportCard, User $user): void
    {
        $studentName = $reportCard->student?->name ?? 'santri';
        $kelas = $reportCard->classroomTerm?->name ?? 'kelas';
        $dispatcher = app(NotificationDispatcher::class);
        $linkUrl = route('report-cards.show', $reportCard);
        $body = "Rapor {$studentName} kelas {$kelas} dikunci oleh {$user->name}.";

        // Wali kelas kelas tsb.
        if ($reportCard->classroom_term_id) {
            $dispatcher->dispatchToHomeroomTeacher(
                $reportCard->classroom_term_id,
                "Rapor {$studentName} dikunci",
                $body,
                'rapor_locked',
                $linkUrl,
                'info',
            );
        }

        // Kepala sekolah.
        $dispatcher->dispatchToRole(
            'kepala_sekolah',
            "Rapor {$studentName} dikunci",
            $body,
            'rapor_locked',
            $linkUrl,
            'info',
        );
    }

    private function notifyPublished(ReportCard $reportCard, User $user): void
    {
        $studentName = $reportCard->student?->name ?? 'santri';
        $kelas = $reportCard->classroomTerm?->name ?? 'kelas';
        $dispatcher = app(NotificationDispatcher::class);
        $linkUrl = route('report-cards.show', $reportCard);
        $body = "Rapor {$studentName} kelas {$kelas} telah diterbitkan oleh {$user->name}. Wali santri kini dapat melihatnya di portal.";

        // Wali santri (orang tua) santri tsb.
        if ($reportCard->student_id) {
            $dispatcher->dispatchToGuardiansOfStudent(
                $reportCard->student_id,
                'Rapor anak Anda telah diterbitkan',
                "Rapor anak Anda ({$studentName}) kelas {$kelas} telah diterbitkan. Silakan lihat di dashboard wali santri.",
                'rapor_published',
                route('wali.dashboard'),
                'success',
            );
        }

        // Wali kelas kelas tsb.
        if ($reportCard->classroom_term_id) {
            $dispatcher->dispatchToHomeroomTeacher(
                $reportCard->classroom_term_id,
                "Rapor {$studentName} diterbitkan",
                $body,
                'rapor_published',
                $linkUrl,
                'success',
            );
        }

        // Kepala sekolah.
        $dispatcher->dispatchToRole(
            'kepala_sekolah',
            "Rapor {$studentName} diterbitkan",
            $body,
            'rapor_published',
            $linkUrl,
            'success',
        );
    }
}
