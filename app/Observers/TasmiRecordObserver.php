<?php

namespace App\Observers;

use App\Models\ScoreChangeLog;
use App\Models\TasmiRecord;
use App\Services\NotificationDispatcher;
use Illuminate\Support\Facades\Auth;

class TasmiRecordObserver
{
    public function created(TasmiRecord $record): void
    {
        ScoreChangeLog::create([
            'score_table' => $record->getTable(),
            'score_id' => $record->id,
            'old_score' => null,
            'new_score' => $record->predicate,
            'changed_by' => $record->input_by ?: Auth::id(),
            'changed_at' => now(),
            'reason' => 'created_tasmi',
        ]);

        $this->dispatchCreatedNotifications($record);
    }

    public function updated(TasmiRecord $record): void
    {
        $changes = [];
        $trackedFields = ['predicate', 'juz_start', 'juz_end', 'exam_type', 'exam_date', 'hijri_date', 'examiner_teacher_id', 'student_id', 'notes'];

        foreach ($trackedFields as $field) {
            if ($record->wasChanged($field)) {
                $changes[$field] = [
                    'from' => $record->getOriginal($field),
                    'to' => $record->getAttribute($field),
                ];
            }
        }

        if (empty($changes)) {
            return;
        }

        // Catat perubahan utama di score_change_logs (kolom old/new dipakai
        // untuk predicate sesuai skema yang ada). Detail field lain dicatat
        // via activity log bawah.
        ScoreChangeLog::create([
            'score_table' => $record->getTable(),
            'score_id' => $record->id,
            'old_score' => $record->getOriginal('predicate'),
            'new_score' => $record->predicate,
            'changed_by' => $record->last_updated_by ?: Auth::id(),
            'changed_at' => now(),
            'reason' => 'updated_tasmi',
        ]);

        activity()
            ->performedOn($record)
            ->causedBy(Auth::user())
            ->withProperties(['changes' => $changes])
            ->log('updated_tasmi_record');

        $this->dispatchUpdatedNotifications($record, $changes);
    }

    public function deleted(TasmiRecord $record): void
    {
        activity()
            ->performedOn($record)
            ->causedBy(Auth::user())
            ->withProperties([
                'record_id' => $record->id,
                'student_id' => $record->student_id,
                'exam_date' => $record->exam_date?->toDateString(),
                'predicate' => $record->predicate,
            ])
            ->log('deleted_tasmi_record');

        $this->dispatchDeletedNotifications($record);
    }

    // ── Notifikasi ────────────────────────────────────────────────────────

    /**
     * Saat PJ Tasmi' input record baru:
     * - wali kelas santri tsb dapat notif (detail hasil tasmi')
     * - PJ sendiri dapat receipt konfirmasi
     * - kabag_tahfidz dapat notif (monitoring)
     */
    private function dispatchCreatedNotifications(TasmiRecord $record): void
    {
        $student = $record->student;
        $studentName = $student?->name ?? 'santri';
        $examType = \App\Models\TasmiRecord::examTypeOptions()[$record->exam_type] ?? $record->exam_type;
        $predicate = \App\Models\TasmiRecord::predicateLabel($record->predicate);
        $dateLabel = $record->exam_date?->locale('id')->translatedFormat('d M Y') ?? '-';
        $juzLabel = $record->juz_range_label;

        $body = "Hasil Tasmi' {$examType} santri {$studentName} ({$juzLabel}) tanggal {$dateLabel}: predikat {$predicate}.";

        $dispatcher = app(NotificationDispatcher::class);

        // Wali kelas santri (read-only lihat data).
        if ($record->classroom_term_id) {
            $dispatcher->dispatchToHomeroomTeacher(
                $record->classroom_term_id,
                "Nilai Tasmi' baru: {$studentName}",
                $body,
                'tasmi_created',
                route('guru.tasmi-wali.show', $record),
                'success',
            );
        }

        // PJ Tasmi' sendiri (receipt).
        if ($record->examiner_teacher_id) {
            $dispatcher->dispatchToExaminer(
                $record->examiner_teacher_id,
                'Tasmi\' berhasil disimpan',
                $body,
                'tasmi_created',
                route('guru.tasmi.edit', $record),
                'success',
            );
        }

        // Kabag tahfidz (broadcast).
        $dispatcher->dispatchToRole(
            'kabag_tahfidz',
            'Input tasmi\' baru',
            $body,
            'tasmi_created',
            route('guru.tasmi-wali.show', $record),
            'info',
        );
    }

    private function dispatchUpdatedNotifications(TasmiRecord $record, array $changes): void
    {
        $student = $record->student;
        $studentName = $student?->name ?? 'santri';
        $dateLabel = $record->exam_date?->locale('id')->translatedFormat('d M Y') ?? '-';

        // Bangun body berdasarkan field yang berubah.
        $parts = [];
        if (isset($changes['predicate'])) {
            $old = \App\Models\TasmiRecord::predicateLabel($changes['predicate']['from']);
            $new = \App\Models\TasmiRecord::predicateLabel($changes['predicate']['to']);
            $parts[] = "predikat {$old} → {$new}";
        }
        if (isset($changes['juz_start']) || isset($changes['juz_end'])) {
            $parts[] = 'juz diuji diubah';
        }
        if (isset($changes['exam_date'])) {
            $parts[] = 'tanggal diubah';
        }
        if (empty($parts)) {
            $parts[] = 'data diperbarui';
        }
        $body = "Hasil Tasmi' santri {$studentName} ({$dateLabel}) diperbarui: ".implode(', ', $parts).'.';

        $dispatcher = app(NotificationDispatcher::class);

        if ($record->classroom_term_id) {
            $dispatcher->dispatchToHomeroomTeacher(
                $record->classroom_term_id,
                "Tasmi' diperbarui: {$studentName}",
                $body,
                'tasmi_updated',
                route('guru.tasmi-wali.show', $record),
                'info',
            );
        }

        $dispatcher->dispatchToRole(
            'kabag_tahfidz',
            'Tasmi\' diperbarui',
            $body,
            'tasmi_updated',
            route('guru.tasmi-wali.show', $record),
            'info',
        );
    }

    private function dispatchDeletedNotifications(TasmiRecord $record): void
    {
        $student = $record->student;
        $studentName = $student?->name ?? 'santri';
        $dateLabel = $record->exam_date?->locale('id')->translatedFormat('d M Y') ?? '-';
        $body = "Record Tasmi' santri {$studentName} tanggal {$dateLabel} dihapus.";

        $dispatcher = app(NotificationDispatcher::class);

        if ($record->classroom_term_id) {
            $dispatcher->dispatchToHomeroomTeacher(
                $record->classroom_term_id,
                "Tasmi' dihapus: {$studentName}",
                $body,
                'tasmi_deleted',
                route('guru.tasmi-wali.index'),
                'warning',
            );
        }

        $dispatcher->dispatchToRole(
            'kabag_tahfidz',
            'Tasmi\' dihapus',
            $body,
            'tasmi_deleted',
            route('guru.tasmi-wali.index'),
            'warning',
        );
    }
}