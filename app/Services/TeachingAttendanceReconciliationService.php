<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Decides whether a scheduled teaching slot has an actionable mismatch between
 * its journal and the teacher's GeoPresensi status. Querying data remains the
 * responsibility of each caller; this service keeps the rules identical for
 * teacher and homeroom views.
 */
class TeachingAttendanceReconciliationService
{
    public const HADIR_TANPA_JURNAL = 'hadir_tanpa_jurnal';
    public const PRESENSI_BELUM_TERCATAT = 'presensi_belum_tercatat';
    public const PRESENSI_DAN_JURNAL_BELUM_TERCATAT = 'presensi_dan_jurnal_belum_tercatat';

    public function __construct(private readonly AttendanceStatusClient $attendanceStatusClient) {}

    /**
     * @return array{state: string, label: ?string, actionable: bool, due: bool, attendance_label: string, journal_label: string}
     */
    public function reconcile(
        string $date,
        ?string $endsAt,
        ?string $attendanceStatus,
        bool $attendanceAvailable,
        bool $hasJournal,
        bool $isSubstituteJournal = false,
        bool $isExcluded = false,
        ?Carbon $now = null,
    ): array {
        $due = $this->isDue($date, $endsAt, $now);
        $attendanceLabel = $this->attendanceLabel($attendanceStatus, $attendanceAvailable);
        $journalLabel = $isSubstituteJournal ? 'Diisi guru pengganti' : ($hasJournal ? 'Sudah diisi' : 'Belum diisi');

        if (! $due) {
            return $this->result('belum_jatuh_tempo', null, false, false, $attendanceLabel, $journalLabel);
        }

        if ($isExcluded || $isSubstituteJournal || $this->attendanceStatusClient->isExempt($attendanceStatus)) {
            return $this->result('selaras', null, false, true, $attendanceLabel, $journalLabel);
        }

        if (! $attendanceAvailable) {
            return $this->result('belum_terverifikasi', null, false, true, 'Belum dapat diverifikasi', $journalLabel);
        }

        if ($this->attendanceStatusClient->isPresent($attendanceStatus) && ! $hasJournal) {
            return $this->result(self::HADIR_TANPA_JURNAL, 'Hadir, jurnal belum diisi', true, true, $attendanceLabel, $journalLabel);
        }

        if ($attendanceStatus === null && $hasJournal) {
            return $this->result(self::PRESENSI_BELUM_TERCATAT, 'Presensi belum tercatat', true, true, $attendanceLabel, $journalLabel);
        }

        if ($attendanceStatus === null && ! $hasJournal) {
            return $this->result(self::PRESENSI_DAN_JURNAL_BELUM_TERCATAT, 'Presensi dan jurnal belum tercatat', true, true, $attendanceLabel, $journalLabel);
        }

        return $this->result('selaras', null, false, true, $attendanceLabel, $journalLabel);
    }

    public function isDue(string $date, ?string $endsAt, ?Carbon $now = null): bool
    {
        try {
            $slotDate = Carbon::createFromFormat('Y-m-d', $date, 'Asia/Jakarta')->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        $now ??= Carbon::now('Asia/Jakarta');
        $now = $now->copy()->setTimezone('Asia/Jakarta');
        if ($slotDate->lt($now->copy()->startOfDay())) {
            return true;
        }
        if (! $slotDate->isSameDay($now) || blank($endsAt)) {
            return false;
        }

        try {
            return $now->greaterThanOrEqualTo(
                Carbon::parse($date.' '.$endsAt, 'Asia/Jakarta')->addMinutes(30),
            );
        } catch (\Throwable) {
            return false;
        }
    }

    private function attendanceLabel(?string $status, bool $available): string
    {
        if (! $available) {
            return 'Belum dapat diverifikasi';
        }

        return match ($status) {
            'hadir' => 'Hadir',
            'hadir_terlambat' => 'Hadir terlambat',
            'hadir_izin_terlambat' => 'Hadir - izin terlambat',
            'izin' => 'Izin',
            'sakit' => 'Sakit',
            default => 'Belum tercatat',
        };
    }

    /** @return array{state: string, label: ?string, actionable: bool, due: bool, attendance_label: string, journal_label: string} */
    private function result(string $state, ?string $label, bool $actionable, bool $due, string $attendanceLabel, string $journalLabel): array
    {
        return [
            'state' => $state,
            'label' => $label,
            'actionable' => $actionable,
            'due' => $due,
            'attendance_label' => $attendanceLabel,
            'journal_label' => $journalLabel,
        ];
    }
}
