<?php

namespace App\Services;

use App\Models\ClassroomTerm;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\HomeroomMonthlyJpConfirmation;
use App\Models\Teacher;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Membentuk rekap penggajian wali kelas dari hasil pemantauan jurnal.
 * Perhitungan slot kosong sengaja berasal dari WaliClassJournalMonitoringController
 * agar status libur/agenda/izin/sakit/Tafsir tetap satu sumber kebenaran.
 */
class WaliJpRecapService
{
    /** @return array<string, mixed> */
    public function build(
        Teacher $homeroomTeacher,
        ClassroomTerm $classroomTerm,
        CarbonInterface $periodStart,
        Collection $monitoringRows,
    ): array {
        $assignments = DiniyyahTeacherAssignment::query()
            ->with(['teacher', 'classSubject.subject'])
            ->whereHas('classSubject', fn ($query) => $query->where('classroom_term_id', $classroomTerm->id))
            ->where(function ($query) use ($periodStart): void {
                $query->whereNull('starts_at')->orWhereDate('starts_at', '<=', $periodStart->copy()->endOfMonth());
            })
            ->where(function ($query) use ($periodStart): void {
                $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', $periodStart->toDateString());
            })
            ->get();

        $teachers = collect();
        foreach ($assignments as $assignment) {
            $this->seedTeacher($teachers, $assignment->teacher, $assignment->classSubject?->subject?->name);
        }

        $rows = $monitoringRows
            ->filter(fn (array $row): bool => (int) ($row['classroom_term_id'] ?? 0) === (int) $classroomTerm->id)
            ->values();
        $tafsirSeen = [];

        foreach ($rows as $row) {
            $journal = $row['journal'] ?? null;
            $scheduledTeacherId = (int) ($row['teacher_id'] ?? 0);

            if (($row['status'] ?? null) === 'KOSONG' && $scheduledTeacherId > 0) {
                $this->seedTeacher($teachers, null, null, $scheduledTeacherId, $row['teacher_name'] ?? '-');
                $teacherRow = $teachers->get($scheduledTeacherId);
                $sessionTime = collect([
                    $row['session_time']['starts_at'] ?? null,
                    $row['session_time']['ends_at'] ?? null,
                ])->filter()->map(fn ($value) => substr((string) $value, 0, 5))->implode(' - ');
                $teacherRow['missing_slots'][] = [
                    'date' => $row['date']->toDateString(),
                    'date_label' => $row['date']->translatedFormat('l, d F Y'),
                    'classroom_name' => $row['classroom_name'] ?? 'Kelas',
                    'subject_name' => $row['subject_name'] ?? 'Mapel',
                    'session_name' => $row['session_name'] ?? '-',
                    'session_time' => $sessionTime ?: '-',
                    'label' => $row['date']->translatedFormat('d M').' · Jam '.($row['session_name'] ?? '-').' · '.($row['subject_name'] ?? 'Mapel'),
                ];
                $teachers->put($scheduledTeacherId, $teacherRow);
            }

            if (! $journal || ! in_array($row['status'] ?? null, ['TERISI', 'TERISI_TIDAK_TERJADWAL'], true)) {
                continue;
            }

            $credited = $journal->effectiveTeacher();
            if (! $credited) {
                continue;
            }
            $this->seedTeacher($teachers, $credited, $row['subject_name'] ?? null);
            $teacherId = (int) $credited->id;
            $teacherRow = $teachers->get($teacherId);
            $isTafsir = strtolower((string) $journal->session_hour) === 'tafsir';

            if ($isTafsir) {
                $timeKey = ($journal->session_starts_at && $journal->session_ends_at)
                    ? $journal->session_starts_at.'|'.$journal->session_ends_at
                    : 'legacy';
                $key = $teacherId.'|'.$journal->date?->toDateString().'|'.$timeKey;
                if (isset($tafsirSeen[$key])) {
                    continue;
                }
                $tafsirSeen[$key] = true;
                $teacherRow['sesi_tafsir']++;
                $teacherRow['total_jp']++;
                $teachers->put($teacherId, $teacherRow);
                continue;
            }

            $jp = (int) $journal->jp_count;
            $teacherRow['total_jp'] += $jp;
            if ($journal->substitute_teacher_id !== null) {
                $teacherRow['sesi_pengganti']++;
                $teacherRow['pengganti_dari'][] = $row['teacher_name'] ?? '-';
            } else {
                $teacherRow['sesi_asli']++;
            }
            $teachers->put($teacherId, $teacherRow);
        }

        $period = $periodStart->copy()->startOfMonth()->toDateString();
        $confirmations = HomeroomMonthlyJpConfirmation::query()
            ->where('classroom_term_id', $classroomTerm->id)
            ->where('homeroom_teacher_id', $homeroomTeacher->id)
            ->whereDate('period_start', $period)
            ->get()
            ->keyBy('teacher_id');

        $teachers = $teachers->map(function (array $row, int $teacherId) use ($confirmations): array {
            $row['missing_slots'] = collect($row['missing_slots'])->unique('label')->values()->all();
            $row['missing_count'] = count($row['missing_slots']);
            $row['subjects'] = collect($row['subjects'])->filter()->unique()->values()->all();
            $row['pengganti_dari'] = collect($row['pengganti_dari'])->filter()->unique()->values()->all();
            // total_jp adalah nama data lama. Nilainya memang hanya berasal dari
            // jurnal yang terisi; alias ini dipakai oleh keluaran penggajian.
            $row['jp_terealisasi'] = $row['total_jp'];
            $row['review_signature'] = $this->signature($row);
            $confirmation = $confirmations->get($teacherId);
            $row['confirmation'] = $this->confirmationState($confirmation, $row);

            return $row;
        })->sortBy('name')->values();

        $missingJournalRows = $teachers
            ->flatMap(function (array $teacherRow): Collection {
                return collect($teacherRow['missing_slots'])->map(fn (array $slot): array => [
                    'teacher_id' => $teacherRow['teacher_id'],
                    'teacher_name' => $teacherRow['name'],
                    'niy' => $teacherRow['niy'],
                    ...$slot,
                ]);
            })
            ->sortBy([['date', 'asc'], ['teacher_name', 'asc'], ['session_name', 'asc']])
            ->values();

        return [
            'classroom_term' => $classroomTerm,
            'period_start' => $periodStart->copy()->startOfMonth(),
            'teachers' => $teachers,
            'missing_journal_rows' => $missingJournalRows,
            'stats' => [
                'total_teachers' => $teachers->count(),
                'total_jp' => (int) $teachers->sum('total_jp'),
                'jp_terealisasi' => (int) $teachers->sum('jp_terealisasi'),
                'missing_slots' => (int) $teachers->sum('missing_count'),
                'confirmed_teachers' => $teachers->whereIn('confirmation.status', ['lengkap', 'override'])->count(),
            ],
        ];
    }

    /** @param Collection<int, array<string, mixed>> $teachers */
    private function seedTeacher(Collection $teachers, ?Teacher $teacher, ?string $subject = null, ?int $fallbackId = null, ?string $fallbackName = null): void
    {
        $id = $teacher?->id ?? $fallbackId;
        if (! $id) {
            return;
        }
        $row = $teachers->get($id, [
            'teacher_id' => $id,
            'name' => $teacher?->name ?? $fallbackName ?? '-',
            'niy' => $teacher?->niy,
            'status' => $teacher?->status,
            'subjects' => [],
            'sesi_asli' => 0,
            'sesi_pengganti' => 0,
            'sesi_tafsir' => 0,
            'total_jp' => 0,
            'missing_slots' => [],
            'pengganti_dari' => [],
        ]);
        if ($subject) {
            $row['subjects'][] = $subject;
        }
        $teachers->put($id, $row);
    }

    /** @param array<string, mixed> $row */
    private function signature(array $row): string
    {
        return hash('sha256', json_encode([
            'teacher' => $row['teacher_id'],
            'jp' => $row['total_jp'],
            'missing' => collect($row['missing_slots'])->pluck('label')->sort()->values()->all(),
        ], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $row */
    private function confirmationState(?HomeroomMonthlyJpConfirmation $confirmation, array $row): array
    {
        if (! $confirmation) {
            return ['status' => 'belum_dicek', 'label' => 'Belum dicek'];
        }
        if ($confirmation->review_signature !== $row['review_signature']) {
            return ['status' => 'perlu_cek_ulang', 'label' => 'Perlu cek ulang'];
        }

        return [
            'status' => $confirmation->is_override ? 'override' : 'lengkap',
            'label' => $confirmation->is_override ? 'Override wali kelas' : 'Lengkap',
            'reason' => $confirmation->override_reason,
            'confirmed_at' => $confirmation->confirmed_at,
        ];
    }
}
