<?php

namespace App\Observers;

use App\Models\StudentAttendance;
use App\Models\StudentAttendanceChangeLog;
use Illuminate\Support\Facades\Auth;
use App\Services\NotificationDispatcher;

class StudentAttendanceObserver
{
    /**
     * Notifikasi ke wali santri HANYA bila status = sick/permission/absent
     * (bukan present/holiday). Batching otomatis per (user, type, link, 10min)
     * menangani bulk input presensi 30 santri sekaligus → 1 notif per wali santri
     * yang anaknya absen (bukan 30 notif ke satu user).
     */
    public function created(StudentAttendance $attendance): void
    {
        $this->logChange($attendance, 'created', null, [
            'status' => $attendance->status,
            'notes' => $attendance->notes,
        ], $attendance->input_by ?: Auth::id());
        $this->maybeNotifyGuardian($attendance, isCreated: true);
    }

    public function updated(StudentAttendance $attendance): void
    {
        $changes = array_values(array_diff(array_keys($attendance->getChanges()), ['updated_at']));
        if ($changes !== []) {
            $old = [];
            $new = [];
            foreach (array_intersect($changes, ['status', 'notes', 'classroom_term_id', 'class_enrollment_id']) as $field) {
                $old[$field] = $attendance->getOriginal($field);
                $new[$field] = $attendance->getAttribute($field);
            }
            if ($old !== []) {
                $this->logChange($attendance, 'updated', $old, $new, $attendance->updated_by ?: Auth::id());
            }
        }

        // Hanya bila status berubah.
        if (! $attendance->wasChanged('status')) {
            return;
        }
        $this->maybeNotifyGuardian($attendance, isCreated: false);
    }

    private function logChange(StudentAttendance $attendance, string $event, ?array $oldValues, ?array $newValues, ?int $changedBy): void
    {
        StudentAttendanceChangeLog::create([
            'student_attendance_id' => $attendance->id,
            'student_id' => $attendance->student_id,
            'attendance_date' => $attendance->attendance_date?->toDateString(),
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_by' => $changedBy,
            'changed_at' => now(),
        ]);
    }

    private function maybeNotifyGuardian(StudentAttendance $attendance, bool $isCreated): void
    {
        // Hanya status ketidakhadiran yang memicu notifikasi.
        if (! in_array($attendance->status, StudentAttendance::recapStatuses(), true)) {
            return;
        }

        $student = $attendance->student;
        if (! $student) {
            return;
        }

        $statusLabel = StudentAttendance::statusOptions()[$attendance->status] ?? $attendance->status;
        $dateLabel = $attendance->attendance_date?->locale('id')->translatedFormat('d M Y') ?? '-';
        $verb = $isCreated ? 'tercatat' : 'diperbarui';

        app(NotificationDispatcher::class)->dispatchToGuardiansOfStudent(
            $attendance->student_id,
            "Kehadiran anak Anda: {$statusLabel}",
            "Anak Anda ({$student->name}) {$verb} {$statusLabel} pada {$dateLabel}.",
            'attendance_absent',
            route('wali.dashboard'),
            $attendance->status === 'absent' ? 'warning' : 'info',
        );
    }
}
