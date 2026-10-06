<?php

namespace App\Services;

use App\Models\ReportCard;
use App\Models\ReportCardRevisionLog;
use App\Models\User;
use App\Services\NotificationDispatcher;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReportCardWorkflow
{
    public function lock(ReportCard $reportCard, User $user): void
    {
        if ($reportCard->status !== 'draft') {
            throw new DomainException('Hanya rapor draf yang dapat dikunci.');
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

        $revision = ReportCardRevisionLog::query()
            ->where('report_card_id', $reportCard->id)
            ->where('action', 'opened')
            ->orderByDesc('revision_number')
            ->first();

        if ($revision && ! ReportCardRevisionLog::query()
            ->where('report_card_id', $reportCard->id)
            ->where('revision_number', $revision->revision_number)
            ->where('action', 'published')
            ->exists()) {
            $reportCard->load(['lines', 'attendance', 'signatures']);
            ReportCardRevisionLog::create([
                'report_card_id' => $reportCard->id,
                'revision_number' => $revision->revision_number,
                'action' => 'published',
                'reason' => $revision->reason,
                'after_data' => $reportCard->toArray(),
                'performed_by' => $user->id,
                'performed_at' => now(),
            ]);
        }

        $this->notifyPublished($reportCard, $user);
    }

    public function openRevision(ReportCard $reportCard, User $user, string $reason): void
    {
        $reason = trim($reason);
        if (! $user->hasAnyRole(['admin', 'kabag_diniyyah'])) {
            throw new DomainException('Anda tidak berwenang membuka revisi rapor.');
        }
        if (mb_strlen($reason) < 10) {
            throw new DomainException('Alasan revisi minimal 10 karakter.');
        }

        DB::transaction(function () use ($reportCard, $user, $reason): void {
            $reportCard = ReportCard::query()->lockForUpdate()->with(['lines', 'attendance', 'signatures'])->findOrFail($reportCard->id);
            if ($reportCard->status !== 'published') {
                throw new DomainException('Revisi hanya dapat dibuka untuk rapor yang sudah terbit.');
            }

            $revisionNumber = (int) $reportCard->revisionLogs()->max('revision_number') + 1;
            $beforeData = $reportCard->toArray();
            $reportCard->authorizeRevisionTransition();
            $reportCard->update([
                'status' => 'draft',
                'published_at' => null,
                'published_by' => null,
                'locked_at' => null,
                'locked_by' => null,
            ]);

            ReportCardRevisionLog::create([
                'report_card_id' => $reportCard->id,
                'revision_number' => $revisionNumber,
                'action' => 'opened',
                'reason' => $reason,
                'before_data' => $beforeData,
                'performed_by' => $user->id,
                'performed_at' => now(),
            ]);
        });
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
