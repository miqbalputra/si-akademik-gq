<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahTeachingSchedule;
use Illuminate\Support\Collection;

/**
 * Rekapitulasi jurnal kelas diniyyah per guru untuk penghitungan gaji (JP).
 *
 * Aturan:
 * - Guru yang dihitung JP-nya = effectiveTeacher() jurnal: pengganti jika ada,
 *   jika tidak guru asli pemilik assignment.
 * - 1 baris jurnal non-tafsir = 1 JP (jp_count).
 * - Tafsir serentak: 1 sesi Kamis diajar ke beberapa kelas menghasilkan N baris
 *   jurnal (1 per kelas). Dihitung 1 JP per (guru, tanggal) — N baris di-dedup.
 */
class RekapJurnalGuruService
{
    public function __construct(private readonly TafsirScheduleGroupingService $tafsirGroups) {}

    /** @return array<string, mixed> */
    public function build(?int $academicTermId, ?string $dateFrom, ?string $dateUntil): array
    {
        $term = $academicTermId
            ? AcademicTerm::with('academicYear')->find($academicTermId)
            : AcademicTerm::with('academicYear')->where('is_active', true)->first();

        $resolvedTermId = $term?->id ?? $academicTermId;

        $journals = DiniyyahClassJournal::query()
            ->with([
                'teacherAssignment.teacher',
                'substituteTeacher',
                'teacherAssignment.classSubject.classroomTerm.academicTerm',
            ])
            ->when($resolvedTermId, function ($q, $id): void {
                $q->whereHas('teacherAssignment.classSubject.classroomTerm', function ($qq) use ($id): void {
                    $qq->where('academic_term_id', $id);
                });
            })
            ->when($dateFrom, fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($dateUntil, fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->orderBy('date')
            ->orderBy('session_hour')
            ->get();

        // Jurnal Tafsir lama pernah tersimpan dengan nama sesi kelas (misalnya
        // "1"), sehingga label jurnal saja tidak cukup untuk menentukan JP.
        // Jadwal aktif adalah sumber kebenaran untuk mengenali sesi serentak.
        $schedules = DiniyyahTeachingSchedule::query()->with([
            'teacherAssignment.teacher',
            'teacherAssignment.classSubject.subject',
            'teacherAssignment.classSubject.classroomTerm.classroom',
            'classSession',
        ])
            ->when($resolvedTermId, function ($q, $id): void {
                $q->whereHas('teacherAssignment.classSubject.classroomTerm', function ($qq) use ($id): void {
                    $qq->where('academic_term_id', $id);
                });
            })
            ->get();

        $teachers = $this->aggregate($journals, $schedules);
        $teachers = $teachers->sortBy('name')->values();

        return [
            'term' => $term,
            'date_from' => $dateFrom,
            'date_until' => $dateUntil,
            'teachers' => $teachers,
            'stats' => [
                'total_teachers' => $teachers->count(),
                'total_jp' => (int) $teachers->sum('total_jp'),
                'total_sesi_asli' => (int) $teachers->sum('sesi_asli'),
                'total_sesi_pengganti' => (int) $teachers->sum('sesi_pengganti'),
                'total_sesi_tafsir' => (int) $teachers->sum('sesi_tafsir'),
            ],
        ];
    }

    /**
     * Akumulasi JP per credited teacher.
     *
     * @param  Collection<int, DiniyyahClassJournal>  $journals
     * @return Collection<int, array<string, mixed>>
     */
    private function aggregate(Collection $journals, Collection $schedules): Collection
    {
        $teachers = collect();
        $tafsirSeen = [];
        $tafsirJournalKeys = $this->tafsirJournalKeys($journals, $schedules);

        foreach ($journals as $journal) {
            $credited = $journal->effectiveTeacher();
            if (! $credited) {
                continue; // FK menjamin assignment ada; defensif.
            }

            $tid = $credited->id;
            $row = $teachers->get($tid, [
                'teacher_id' => $tid,
                'name' => $credited->name,
                'niy' => $credited->niy,
                'status' => $credited->status,
                'sesi_asli' => 0,
                'sesi_pengganti' => 0,
                'sesi_tafsir' => 0,
                'total_jp' => 0,
            ]);

            $dateStr = $journal->date?->toDateString();

            $tafsirKey = $tafsirJournalKeys[$journal->id] ?? null;
            if ($tafsirKey !== null || strtolower((string) $journal->session_hour) === 'tafsir') {
                // Dedup per kelompok waktu. Dua sesi Tafsir serentak pada hari
                // yang sama tetapi jam berbeda harus tetap dihitung 2 JP.
                // Jurnal lama tanpa snapshot jam tetap kompatibel sebagai satu
                // kelompok legacy pada tanggal tersebut.
                $timeKey = $tafsirKey ?? (($journal->session_starts_at && $journal->session_ends_at)
                    ? $journal->session_starts_at.'|'.$journal->session_ends_at
                    : 'legacy-tafsir');
                $key = $tafsirKey ?? $tid.'|'.$dateStr.'|'.$timeKey;
                if (isset($tafsirSeen[$key])) {
                    $teachers->put($tid, $row);

                    continue;
                }
                $tafsirSeen[$key] = true;
                $row['sesi_tafsir']++;
                $row['total_jp'] += 1;
            } else {
                if ($journal->substitute_teacher_id !== null) {
                    $row['sesi_pengganti']++;
                } else {
                    $row['sesi_asli']++;
                }
                $row['total_jp'] += (int) $journal->jp_count;
            }

            $teachers->put($tid, $row);
        }

        return $teachers;
    }

    /**
     * Map jurnal ke identitas sesi Tafsir serentak yang tervalidasi jadwal.
     *
     * @param  Collection<int, DiniyyahClassJournal>  $journals
     * @param  Collection<int, DiniyyahTeachingSchedule>  $schedules
     * @return array<int, string>
     */
    private function tafsirJournalKeys(Collection $journals, Collection $schedules): array
    {
        $keys = [];

        foreach ($journals->groupBy(fn (DiniyyahClassJournal $journal) => $journal->date?->toDateString()) as $date => $dayJournals) {
            if (! $date) {
                continue;
            }

            foreach ($this->tafsirGroups->simultaneousGroupsForDate($schedules, $date) as $group) {
                foreach ($dayJournals->filter(fn (DiniyyahClassJournal $journal) => $group['schedules']->contains(fn ($schedule) => $this->tafsirGroups->journalMatchesSchedule($journal, $schedule))) as $journal) {
                    $teacherId = $journal->effectiveTeacher()?->id;
                    if ($teacherId) {
                        $keys[$journal->id] = implode('|', [$teacherId, $date, $group['starts_at'], $group['ends_at']]);
                    }
                }
            }
        }

        return $keys;
    }
}
