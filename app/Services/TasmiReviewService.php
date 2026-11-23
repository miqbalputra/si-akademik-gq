<?php

namespace App\Services;

use App\Models\PanelNotification;
use App\Models\ScoreChangeLog;
use App\Models\TasmiRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TasmiReviewService
{
    public function __construct(private readonly TasmiReportService $reports, private readonly NotificationDispatcher $notifications) {}

    public function review(User $user, TasmiRecord $record, int $version, string $decision, ?string $reason): void
    {
        DB::transaction(function () use ($user, $record, $version, $decision, $reason): void {
            abort_unless($user->hasRole('guru') && $user->teacher, 403);
            $current = $this->reports->forHomeroom($user->teacher)->whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($current->review_version !== $version || ! in_array($current->review_status, [null, TasmiRecord::REVIEW_PENDING], true)) {
                throw ValidationException::withMessages(['review' => 'Data atau status sudah berubah. Muat ulang detail dan periksa kembali sebelum menyetujui.']);
            }
            if (! in_array($decision, [TasmiRecord::REVIEW_APPROVED, TasmiRecord::REVIEW_CORRECTION], true)) {
                throw ValidationException::withMessages(['decision' => 'Pilih keputusan pemeriksaan.']);
            }
            $reason = trim((string) $reason);
            if ($decision === TasmiRecord::REVIEW_CORRECTION && $reason === '') {
                throw ValidationException::withMessages(['correction_reason' => 'Tuliskan alasan perbaikan untuk PJ Tasmi\'.']);
            }

            $current->forceFill([
                'review_status' => $decision,
                'review_version' => $current->review_version + 1,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'correction_reason' => $decision === TasmiRecord::REVIEW_CORRECTION ? $reason : null,
            ])->save();

            ScoreChangeLog::create([
                'score_table' => $current->getTable(), 'score_id' => $current->id,
                'old_score' => null, 'new_score' => null,
                'changed_by' => $user->id, 'changed_at' => now(),
                'reason' => $decision === TasmiRecord::REVIEW_APPROVED ? 'approved_tasmi' : 'correction_requested_tasmi',
            ]);

            activity()->performedOn($current)->causedBy($user)->withProperties([
                'decision' => $decision, 'reason' => $current->correction_reason, 'review_version' => $current->review_version,
            ])->log('reviewed_tasmi_record');

            PanelNotification::query()->where('user_id', $user->id)
                ->whereIn('notification_type', ['tasmi_created', 'tasmi_updated'])
                ->where('link_url', route('guru.tasmi-wali.show', $current))
                ->where('status', 'unread')->update(['status' => 'read', 'read_at' => now()]);

            $name = $current->student?->name ?? 'Santri';
            $approved = $decision === TasmiRecord::REVIEW_APPROVED;
            $body = $approved
                ? "Hasil Tasmi' {$name} ({$current->juz_range_label}) disetujui wali kelas dan siap diproses admin untuk piagam."
                : "Hasil Tasmi' {$name} ({$current->juz_range_label}) perlu diperbaiki. Alasan: {$reason}";
            $this->notifications->dispatchToExaminer($current->examiner_teacher_id,
                $approved ? 'Tasmi\' disetujui wali kelas' : 'Perbaiki data Tasmi\'', $body,
                $approved ? 'tasmi_approved' : 'tasmi_correction_requested',
                route('guru.tasmi.edit', $current), $approved ? 'success' : 'warning');

            if ($approved) {
                foreach (['admin', 'kabag_tahfidz'] as $role) {
                    $this->notifications->dispatchToRole($role, 'Tasmi\' siap diproses untuk piagam', $body,
                        'tasmi_approved', route('admin.tasmi-report.show', $current), 'success');
                }
            }
        });
    }
}
