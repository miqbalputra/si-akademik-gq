<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PanelNotification extends Model
{
    protected $fillable = [
        'user_id',
        'audience_role',
        'title',
        'body',
        'severity',
        'notification_type',
        'link_url',
        'status',
        'read_at',
        'batch_key',
        'batch_count',
        'archived_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'archived_at' => 'datetime',
        'batch_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead(): void
    {
        if ($this->status === 'unread') {
            $this->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
        }
    }

    public function archive(): void
    {
        if ($this->archived_at === null) {
            $this->update(['archived_at' => now()]);
        }
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('status', 'unread');
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhereNull('user_id'); // notifikasi broadcast by audience_role
        });
    }

    /**
     * Notifikasi yang relevan untuk user tertentu: direct (user_id) + broadcast
     * by role yang dimiliki user. Exclude archived.
     */
    public function scopeRelevantFor(Builder $query, User $user): Builder
    {
        $roleNames = $user->roles()->pluck('name')->all();

        return $query->whereNull('archived_at')->where(function (Builder $q) use ($user, $roleNames) {
            $q->where('user_id', $user->id);
            if (! empty($roleNames)) {
                $q->orWhereIn('audience_role', $roleNames);
            }
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public static function severityOptions(): array
    {
        return [
            'info' => 'Info',
            'success' => 'Sukses',
            'warning' => 'Peringatan',
            'danger' => 'Penting',
        ];
    }

    public static function typeOptions(): array
    {
        return [
            'tasmi_created' => 'Tasmi\' baru',
            'tasmi_updated' => 'Tasmi\' diperbarui',
            'tasmi_deleted' => 'Tasmi\' dihapus',
            'diniyyah_score_input' => 'Nilai diniyyah diinput',
            'assessment_submitted' => 'Nilai disubmit',
            'assessment_approved' => 'Nilai divalidasi',
            'assessment_needs_revision' => 'Nilai perlu revisi',
            'journal_created' => 'Jurnal baru',
            'journal_updated' => 'Jurnal diperbarui',
            'journal_deleted' => 'Jurnal dihapus',
            'attendance_absent' => 'Ketidakhadiran santri',
            'tahfidz_weekly' => 'Nilai tahfidz pekanan',
            'tahfidz_uas' => 'Nilai UAS tahfidz',
            'tahfidz_halaqah_submit' => 'Halaqah disubmit',
            'tahfidz_halaqah_approved' => 'Halaqah divalidasi',
            'tahfidz_recap_locked' => 'Rekap semester tahfidz dikunci',
            'rapor_generated' => 'Rapor di-generate',
            'rapor_locked' => 'Rapor dikunci',
            'rapor_published' => 'Rapor diterbitkan',
            'ledger_generated' => 'Leger di-generate',
            'ledger_validated' => 'Leger divalidasi',
            'ledger_locked' => 'Leger dikunci',
            'assignment_created' => 'Penugasan baru',
            'assignment_updated' => 'Penugasan diperbarui',
            'assignment_removed' => 'Penugasan dicabut',
            'school_event_created' => 'Agenda sekolah baru',
            'school_event_updated' => 'Agenda diperbarui',
            'school_event_deleted' => 'Agenda dibatalkan',
            'event_rsvp' => 'Respon wali santri',
            'schedule_changed' => 'Jadwal mengajar diubah',
        ];
    }
}