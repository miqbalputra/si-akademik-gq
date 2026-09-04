<?php

namespace App\Services;

use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\TafsirJournalNormalization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Audit jurnal Tafsir historis yang dibuat dari form reguler per kelas.
 *
 * Normalisasi selalu dibangun ulang dari jadwal yang aktif pada tanggal jurnal;
 * URL tidak pernah membawa daftar jurnal yang dapat dipercaya sendiri.
 */
class TafsirJournalAuditService
{
    public function __construct(private readonly TafsirScheduleGroupingService $groups) {}

    /** @return Collection<int, array<string, mixed>> */
    public function candidates(int $academicTermId, Carbon|string $start, Carbon|string $end): Collection
    {
        $start = Carbon::parse($start, 'Asia/Jakarta')->startOfDay();
        $end = Carbon::parse($end, 'Asia/Jakarta')->endOfDay();
        $schedules = $this->schedulesForTerm($academicTermId);
        $journals = $this->journalsForTerm($academicTermId, $start, $end);
        $normalizations = TafsirJournalNormalization::query()
            ->whereIn('diniyyah_class_journal_id', $journals->pluck('id'))
            ->get();
        $candidates = collect();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateString = $date->toDateString();
            $dayJournals = $journals->filter(fn (DiniyyahClassJournal $journal) => $journal->date?->toDateString() === $dateString);
            if ($dayJournals->isEmpty()) {
                continue;
            }

            foreach ($this->groups->simultaneousGroupsForDate($schedules, $date) as $group) {
                $matches = $dayJournals
                    ->filter(fn (DiniyyahClassJournal $journal) => $group['schedules']->contains(fn ($schedule) => $this->groups->journalMatchesSchedule($journal, $schedule)))
                    ->values();
                if ($matches->isEmpty()) {
                    continue;
                }

                $candidate = $this->candidate($dateString, $group, $matches, $normalizations);
                $candidates->push($candidate);
            }
        }

        return $candidates->sortBy(fn (array $row) => [$row['date'], $row['starts_at'], $row['effective_teacher_name']])->values();
    }

    /** @return array<string, mixed> */
    public function normalize(int $academicTermId, string $date, int $scheduleId, User $user): array
    {
        $candidate = $this->candidates($academicTermId, $date, $date)
            ->first(fn (array $row) => in_array($scheduleId, $row['schedule_ids'], true));

        if (! $candidate) {
            throw new RuntimeException('Kelompok Tafsir tidak ditemukan untuk tanggal dan jadwal tersebut.');
        }
        if (! $candidate['can_normalize']) {
            throw new RuntimeException('Kelompok ini belum aman dinormalisasi: '.$candidate['status'].'.');
        }

        DB::transaction(function () use ($candidate, $user): void {
            $journals = DiniyyahClassJournal::query()
                ->whereIn('id', $candidate['normalizable_journal_ids'])
                ->lockForUpdate()
                ->get();
            if ($journals->count() !== count($candidate['normalizable_journal_ids'])) {
                throw new RuntimeException('Salah satu jurnal telah berubah. Muat ulang audit sebelum menyetujui.');
            }

            foreach ($journals as $journal) {
                $originalSession = (string) $journal->session_hour;
                if (strtolower($originalSession) === 'tafsir') {
                    continue;
                }
                $journal->update(['session_hour' => 'tafsir']);
                TafsirJournalNormalization::create([
                    'diniyyah_class_journal_id' => $journal->id,
                    'group_key' => $candidate['group_key'],
                    'original_session_hour' => $originalSession,
                    'normalized_by' => $user->id,
                    'normalized_at' => now('Asia/Jakarta'),
                ]);
            }
        });

        return $candidate;
    }

    /** @return array<string, mixed> */
    public function revert(int $academicTermId, string $date, int $scheduleId, User $user): array
    {
        $candidate = $this->candidates($academicTermId, $date, $date)
            ->first(fn (array $row) => in_array($scheduleId, $row['schedule_ids'], true));

        if (! $candidate || ! $candidate['can_revert']) {
            throw new RuntimeException('Tidak ada normalisasi aktif yang dapat dipulihkan untuk kelompok ini.');
        }

        DB::transaction(function () use ($candidate, $user): void {
            $normalizations = TafsirJournalNormalization::query()
                ->whereIn('id', $candidate['normalization_ids'])
                ->whereNull('reverted_at')
                ->lockForUpdate()
                ->get();
            if ($normalizations->count() !== count($candidate['normalization_ids'])) {
                throw new RuntimeException('Status normalisasi telah berubah. Muat ulang audit sebelum memulihkan.');
            }

            $journals = DiniyyahClassJournal::query()
                ->whereIn('id', $normalizations->pluck('diniyyah_class_journal_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            foreach ($normalizations as $normalization) {
                $journal = $journals->get($normalization->diniyyah_class_journal_id);
                if (! $journal || strtolower((string) $journal->session_hour) !== 'tafsir') {
                    throw new RuntimeException('Salah satu jurnal telah berubah sehingga tidak aman dipulihkan otomatis.');
                }
                $journal->update(['session_hour' => $normalization->original_session_hour]);
                $normalization->update(['reverted_by' => $user->id, 'reverted_at' => now('Asia/Jakarta')]);
            }
        });

        return $candidate;
    }

    /** @return Collection<int, DiniyyahTeachingSchedule> */
    private function schedulesForTerm(int $termId): Collection
    {
        return DiniyyahTeachingSchedule::query()->with([
            'teacherAssignment.teacher',
            'teacherAssignment.classSubject.subject',
            'teacherAssignment.classSubject.classroomTerm.classroom',
            'classSession',
        ])->whereHas('teacherAssignment.classSubject.classroomTerm', fn ($query) => $query->where('academic_term_id', $termId))->get();
    }

    /** @return Collection<int, DiniyyahClassJournal> */
    private function journalsForTerm(int $termId, Carbon $start, Carbon $end): Collection
    {
        return DiniyyahClassJournal::query()->with([
            'teacherAssignment.teacher',
            'teacherAssignment.classSubject.subject',
            'teacherAssignment.classSubject.classroomTerm.classroom',
            'substituteTeacher',
        ])->whereHas('teacherAssignment.classSubject.classroomTerm', fn ($query) => $query->where('academic_term_id', $termId))
            // Kolom date historis pada sebagian basis data SQLite terserialisasi
            // sebagai midnight. Batas akhir harus mencakup satu hari penuh.
            ->whereBetween('date', [$start->copy()->startOfDay()->toDateTimeString(), $end->copy()->endOfDay()->toDateTimeString()])
            ->get();
    }

    /** @param array<string, mixed> $group @return array<string, mixed> */
    private function candidate(string $date, array $group, Collection $matches, Collection $normalizations): array
    {
        $schedules = $group['schedules'];
        $expectedAssignmentIds = $schedules->pluck('diniyyah_teacher_assignment_id')->map(fn ($id) => (int) $id)->unique()->values();
        $journalCountByAssignment = $matches->countBy('diniyyah_teacher_assignment_id');
        $complete = $expectedAssignmentIds->every(fn (int $id) => (int) ($journalCountByAssignment[$id] ?? 0) === 1)
            && $matches->count() === $expectedAssignmentIds->count();
        $effectiveTeachers = $matches->map(fn (DiniyyahClassJournal $journal) => $journal->effectiveTeacher())->filter()->unique('id')->values();
        $normalizable = $matches->filter(fn (DiniyyahClassJournal $journal) => strtolower((string) $journal->session_hour) !== 'tafsir')->values();
        $groupKey = $date.'|'.$group['key'];
        $activeNormalizations = $normalizations
            ->filter(fn (TafsirJournalNormalization $normalization) => $normalization->group_key === $groupKey
                && $normalization->reverted_at === null
                && $matches->contains('id', $normalization->diniyyah_class_journal_id))
            ->values();
        $status = 'Sudah sesuai';
        $canNormalize = false;
        $canRevert = $activeNormalizations->isNotEmpty();
        if ($canRevert) {
            $status = 'Sudah dinormalisasi';
        } elseif ($normalizable->isNotEmpty()) {
            if (! $complete) {
                $status = 'Jurnal kelas belum lengkap atau ganda';
            } elseif ($effectiveTeachers->count() !== 1) {
                $status = 'Guru efektif berbeda';
            } else {
                $status = 'Siap dinormalisasi';
                $canNormalize = true;
            }
        }

        return [
            'group_key' => $groupKey,
            'date' => $date,
            'starts_at' => $group['starts_at'],
            'ends_at' => $group['ends_at'],
            'schedule_ids' => $schedules->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'classes' => $schedules->map(fn ($schedule) => $schedule->teacherAssignment?->classSubject?->classroomTerm?->name)->filter()->unique()->values()->all(),
            'subjects' => $schedules->map(fn ($schedule) => $schedule->teacherAssignment?->classSubject?->subject?->name)->filter()->unique()->values()->all(),
            'original_teacher_name' => $schedules->first()?->teacherAssignment?->teacher?->name ?? '-',
            'effective_teacher_name' => $effectiveTeachers->first()?->name ?? '-',
            'journal_count' => $matches->count(),
            'expected_journal_count' => $expectedAssignmentIds->count(),
            'jp_before' => (int) $matches->sum('jp_count'),
            'jp_after' => $effectiveTeachers->count() === 1 && $matches->isNotEmpty() ? 1 : 0,
            'original_sessions' => $matches->pluck('session_hour')->filter()->unique()->values()->all(),
            'status' => $status,
            'can_normalize' => $canNormalize,
            'can_revert' => $canRevert,
            'normalizable_journal_ids' => $normalizable->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'normalization_ids' => $activeNormalizations->pluck('id')->map(fn ($id) => (int) $id)->all(),
        ];
    }
}
