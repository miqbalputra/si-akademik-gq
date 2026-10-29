<?php

namespace App\Services;

use App\Models\DiniyyahTeacherAssignment;
use App\Models\Teacher;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * One decision for teacher and management journal forms. This service never
 * changes a journal, attendance, or the teacher's GeoPresensi status.
 */
class JournalAttendanceEligibilityService
{
    public const EXEMPT_LABEL = 'Dikecualikan dari GeoPresensi untuk jurnal';

    public function __construct(private readonly AttendanceTeacherReportClient $client) {}

    /** @return array{allowed: bool, exempt: bool, status_label: string, message: string} */
    public function check(?Teacher $teacher, string|CarbonInterface|null $date): array
    {
        // Authorize from the latest persisted identity and exemption, including
        // when a caller still holds an older loaded teacher relation.
        $teacher = $teacher?->exists ? Teacher::find($teacher->getKey()) : null;
        if (! $teacher) {
            return $this->unverified('Identitas guru belum terhubung. Hubungi admin.');
        }

        try {
            $day = $date instanceof CarbonInterface
                ? Carbon::instance($date)->setTimezone('Asia/Jakarta')->startOfDay()
                : Carbon::createFromFormat('!Y-m-d', (string) $date, 'Asia/Jakarta');
            if (! $day || (is_string($date) && $day->toDateString() !== $date)) {
                return $this->unverified('Pilih tanggal jurnal yang valid.');
            }
        } catch (Throwable) {
            return $this->unverified('Pilih tanggal jurnal yang valid.');
        }

        if ($teacher->journal_attendance_exempt) {
            return ['allowed' => true, 'exempt' => true, 'status_label' => self::EXEMPT_LABEL,
                'message' => self::EXEMPT_LABEL.'. Kewajiban mengisi jurnal tetap berlaku.'];
        }

        $niy = trim((string) $teacher->niy);
        if ($niy !== '' && Teacher::query()->whereKeyNot($teacher->id)->whereRaw('TRIM(niy) = ?', [$niy])->exists()) {
            return $this->unverified('NIY digunakan oleh lebih dari satu guru. Hubungi admin untuk memperbaiki identitas.');
        }

        // Never authorize a write using a report cached when the form was opened.
        $result = $this->client->reportForTeacher($teacher, $day, $day, refresh: true);
        if (! $result['ok']) {
            return $this->unverified($result['message']);
        }

        $matches = [];
        foreach ($result['report']['rows'] as $row) {
            if (! is_array($row) || ! is_string($row['tanggal'] ?? null)
                || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['tanggal'])) {
                return $this->unverified('Respons presensi GeoPresensi tidak valid. Silahkan cek aplikasi GeoPresensi.');
            }
            if ($row['tanggal'] !== $day->toDateString()) {
                continue;
            }
            if (isset($row['id_guru']) && trim((string) $row['id_guru']) !== $niy) {
                return $this->unverified('Identitas pada respons presensi tidak sesuai dengan guru.');
            }
            $matches[] = $row;
        }

        if (count($matches) > 1) {
            return $this->unverified('Terdapat lebih dari satu status presensi pada tanggal jurnal. Silahkan cek aplikasi GeoPresensi.');
        }
        if ($matches === []) {
            return $this->absent('Belum presensi');
        }

        $status = $matches[0]['status'] ?? null;
        if (! is_string($status)) {
            return $this->unverified('Status presensi GeoPresensi tidak valid.');
        }
        $status = strtolower(trim($status));
        $label = match ($status) {
            'hadir' => 'Hadir',
            'hadir_terlambat' => 'Hadir terlambat',
            'hadir_izin_terlambat' => 'Hadir - izin terlambat',
            'sakit' => 'Sakit',
            'izin' => 'Izin',
            'alfa' => 'Alfa',
            default => null,
        };
        if ($label === null) {
            return $this->unverified('Status presensi GeoPresensi belum dapat dikenali. Silahkan cek aplikasi GeoPresensi.');
        }
        if (! in_array($status, AttendanceStatusClient::PRESENT_STATUSES, true)) {
            return $this->absent($label);
        }

        return ['allowed' => true, 'exempt' => false, 'status_label' => $label,
            'message' => 'Status GeoPresensi: '.$label.' pada '.$day->format('d/m/Y').'.'];
    }

    /** Resolve the prospective form values, never the admin's own identity or old relations. */
    public function checkFormData(array $data): array
    {
        $assignmentId = filter_var($data['diniyyah_teacher_assignment_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $date = $data['date'] ?? null;
        if ($assignmentId === false || (! is_string($date) && ! $date instanceof CarbonInterface)) {
            return $this->unverified('Pilih tugas mengajar dan tanggal jurnal yang valid.');
        }
        $assignment = DiniyyahTeacherAssignment::find($assignmentId);
        if (! $assignment) {
            return $this->unverified('Tugas mengajar tidak ditemukan.');
        }
        $substituteId = $data['substitute_teacher_id'] ?? null;
        if (filled($substituteId) && filter_var($substituteId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return $this->unverified('Identitas guru pengganti tidak valid.');
        }
        $teacher = filled($substituteId)
            ? Teacher::find($substituteId)
            : $assignment?->teacher;

        return $this->check($teacher, $date);
    }

    private function absent(string $label): array
    {
        return ['allowed' => false, 'exempt' => false, 'status_label' => $label,
            'message' => 'Mohon maaf Anda tidak hadir pada jam ini dengan status '.$label.'. Silahkan cek aplikasi GeoPresensi'];
    }

    private function unverified(string $reason): array
    {
        return ['allowed' => false, 'exempt' => false, 'status_label' => 'Belum dapat diverifikasi',
            'message' => $reason.' Jurnal belum dapat disimpan.'];
    }
}
