<?php

namespace App\Observers;

use App\Models\StudentAttendance;
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
        $this->maybeNotifyGuardian($attendance, isCreated: true);
    }

    public function updated(StudentAttendance $attendance): void
    {
        // Hanya bila status berubah.
        if (! $attendance->wasChanged('status')) {
            return;
        }
        $this->maybeNotifyGuardian($attendance, isCreated: false);
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