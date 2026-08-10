<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ClassEnrollment;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahSubject;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Satu sumber data untuk laporan jurnal Diniyyah.
 *
 * Scope guru memakai guru efektif (guru asli atau pengganti), sedangkan
 * scope manajemen dapat memakai filter lintas guru efektif, kelas, mapel, dan tipe.
 */
class DiniyyahJournalReportService
{
    /** @return array<string, mixed> */
    public function build(array $filters = [], ?int $teacherId = null): array
    {
        $filters = $this->normalizeFilters($filters);
        $journals = $this->query($filters, $teacherId)->get();
        $rows = $this->mapRows($journals);
        $term = $filters['academic_term_id']
            ? AcademicTerm::with('academicYear')->find($filters['academic_term_id'])
            : null;

        return [
            'rows' => $rows,
            'filters' => $filters,
            'stats' => $this->buildStats($rows),
            'term' => $term,
            'filter_labels' => $this->filterLabels($filters, $term),
        ];
    }

    /** @return array<string, Collection<int, mixed>> */
    public function options(): array
    {
        return [
            'terms' => $this->academicTerms(),
            'teachers' => Teacher::query()
                ->orderBy('name')
                ->get(['id', 'name', 'niy', 'status']),
            'classes' => ClassroomTerm::query()
                ->whereHas('diniyyahClassSubjects.teacherAssignments')
                ->orderBy('name')
                ->get(['id', 'name', 'academic_term_id']),
            'subjects' => DiniyyahSubject::query()
                ->whereHas('classSubjects.teacherAssignments')
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    /** @return Collection<int, AcademicTerm> */
    public function academicTerms(): Collection
    {
        return AcademicTerm::query()
            ->with('academicYear')
            ->orderByDesc('starts_at')
            ->get();
    }

    /** @return Builder<DiniyyahClassJournal> */
    public function query(array $filters = [], ?int $teacherId = null): Builder
    {
        $filters = $this->normalizeFilters($filters);

        return DiniyyahClassJournal::query()
            ->with([
                'teacherAssignment.teacher',
                'substituteTeacher',
                'teacherAssignment.classSubject.subject',
                'teacherAssignment.classSubject.classroomTerm.classroom',
                'teacherAssignment.classSubject.classroomTerm.academicTerm.academicYear',
                'absences.classEnrollment.student',
            ])
            ->when($teacherId, function (Builder $query, int $id): void {
                $this->whereEffectiveTeacher($query, $id);
            })
            ->when($filters['academic_term_id'], function (Builder $query, int $id): void {
                $query->whereHas('teacherAssignment.classSubject.classroomTerm', function (Builder $q) use ($id): void {
                    $q->where('academic_term_id', $id);
                });
            })
            ->when($filters['date_from'], fn (Builder $query, string $date) => $query->whereDate('date', '>=', $date))
            ->when($filters['date_until'], fn (Builder $query, string $date) => $query->whereDate('date', '<=', $date))
            ->when($filters['teacher_id'], function (Builder $query, int $id): void {
                $this->whereEffectiveTeacher($query, $id);
            })
            ->when($filters['classroom_term_id'], function (Builder $query, int $id): void {
                $query->whereHas('teacherAssignment.classSubject', fn (Builder $q) => $q->where('classroom_term_id', $id));
            })
            ->when($filters['subject_id'], function (Builder $query, int $id): void {
                $query->whereHas('teacherAssignment.classSubject', fn (Builder $q) => $q->where('subject_id', $id));
            })
            ->when($filters['type'] === 'regular', fn (Builder $query) => $query->whereNull('substitute_teacher_id'))
            ->when($filters['type'] === 'substitute', fn (Builder $query) => $query->whereNotNull('substitute_teacher_id'))
            ->when($filters['search'], function (Builder $query, string $search): void {
                $like = '%'.$search.'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->whereHas('teacherAssignment.teacher', fn (Builder $q) => $q->where('name', 'like', $like))
                        ->orWhereHas('substituteTeacher', fn (Builder $q) => $q->where('name', 'like', $like))
                        ->orWhereHas('teacherAssignment.classSubject.classroomTerm', fn (Builder $q) => $q->where('name', 'like', $like))
                        ->orWhereHas('teacherAssignment.classSubject.subject', fn (Builder $q) => $q->where('name', 'like', $like))
                        ->orWhere('material', 'like', $like);
                });
            })
            ->orderBy('date')
            ->orderBy('session_starts_at')
            ->orderBy('session_hour');
    }

    /** @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        $academicTermId = $filters['academic_term_id'] ?? $filters['term'] ?? null;
        $teacherId = $filters['teacher_id'] ?? $filters['guru'] ?? null;
        $type = $filters['type'] ?? $filters['tipe'] ?? null;

        return [
            'academic_term_id' => filled($academicTermId) ? (int) $academicTermId : null,
            'date_from' => filled($filters['date_from'] ?? null) ? (string) $filters['date_from'] : null,
            'date_until' => filled($filters['date_until'] ?? null) ? (string) $filters['date_until'] : null,
            'teacher_id' => filled($teacherId) ? (int) $teacherId : null,
            'classroom_term_id' => filled($filters['classroom_term_id'] ?? null) ? (int) $filters['classroom_term_id'] : null,
            'subject_id' => filled($filters['subject_id'] ?? null) ? (int) $filters['subject_id'] : null,
            'type' => in_array($type, ['regular', 'substitute'], true) ? $type : null,
            'search' => filled($filters['search'] ?? null) ? trim((string) $filters['search']) : null,
        ];
    }

    /** @param Collection<int, DiniyyahClassJournal> $journals */
    private function mapRows(Collection $journals): Collection
    {
        $classroomTermIds = $journals
            ->map(fn (DiniyyahClassJournal $journal) => $journal->teacherAssignment?->classSubject?->classroom_term_id)
            ->filter()
            ->unique()
            ->values();

        $activeEnrollmentCounts = $classroomTermIds->isEmpty()
            ? collect()
            : ClassEnrollment::query()
                ->whereIn('classroom_term_id', $classroomTermIds)
                ->where('status', 'active')
                ->selectRaw('classroom_term_id, count(*) as aggregate')
                ->groupBy('classroom_term_id')
                ->pluck('aggregate', 'classroom_term_id');

        return $journals->map(function (DiniyyahClassJournal $journal) use ($activeEnrollmentCounts): array {
            $assignment = $journal->teacherAssignment;
            $classSubject = $assignment?->classSubject;
            $classroomTerm = $classSubject?->classroomTerm;
            $subject = $classSubject?->subject;
            $absenceCounts = [
                'sick' => 0,
                'permission' => 0,
                'absent' => 0,
                'skipped' => 0,
            ];

            foreach ($journal->absences as $absence) {
                if (array_key_exists($absence->status, $absenceCounts)) {
                    $absenceCounts[$absence->status]++;
                }
            }

            $absenceTotal = array_sum($absenceCounts);
            $activeEnrollmentCount = (int) ($activeEnrollmentCounts[$classroomTerm?->id] ?? 0);
            $creditedTeacher = $journal->effectiveTeacher();
            $isSubstitute = $journal->substitute_teacher_id !== null;

            return [
                'journal' => $journal,
                'id' => $journal->id,
                'date' => $journal->date?->toDateString(),
                'date_label' => $journal->date?->locale('id')->translatedFormat('d M Y'),
                'session_hour' => (string) $journal->session_hour,
                'session_label' => strtolower((string) $journal->session_hour) === 'tafsir'
                    ? 'Tafsir serentak'
                    : 'Jam '.($journal->session_hour ?: '-'),
                'session_time' => $this->sessionTime($journal),
                'classroom_term_id' => $classroomTerm?->id,
                'subject_id' => $subject?->id,
                'kelas' => $classroomTerm?->name ?? '-',
                'mapel' => $subject?->name ?? '-',
                'guru_asli_id' => $assignment?->teacher_id,
                'guru_asli' => $assignment?->teacher?->name ?? '-',
                'pengganti_id' => $journal->substitute_teacher_id,
                'pengganti' => $journal->substituteTeacher?->name,
                'guru_mengajar_id' => $creditedTeacher?->id,
                'guru_mengajar' => $creditedTeacher?->name ?? '-',
                'type' => $isSubstitute ? 'substitute' : 'regular',
                'type_label' => $isSubstitute ? 'Pengganti' : 'Reguler',
                'material' => (string) ($journal->material ?? '-'),
                'jp' => (int) ($journal->jp_count ?? 0),
                'hadir' => max(0, $activeEnrollmentCount - $absenceTotal),
                'sakit' => $absenceCounts['sick'],
                'izin' => $absenceCounts['permission'],
                'alpa' => $absenceCounts['absent'],
                'bolos' => $absenceCounts['skipped'],
            ];
        })->values();
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function buildStats(Collection $rows): array
    {
        $byTeacher = $rows
            ->groupBy('guru_mengajar_id')
            ->map(function (Collection $teacherRows): array {
                return [
                    'teacher_id' => $teacherRows->first()['guru_mengajar_id'],
                    'name' => $teacherRows->first()['guru_mengajar'],
                    'journals' => $teacherRows->count(),
                    'jp' => (int) $teacherRows->sum('jp'),
                ];
            })
            ->filter(fn (array $row) => filled($row['teacher_id']))
            ->sortByDesc(fn (array $row) => [$row['journals'], $row['name']])
            ->values();

        $byClass = $rows
            ->groupBy('kelas')
            ->map(fn (Collection $classRows): array => [
                'name' => $classRows->first()['kelas'],
                'journals' => $classRows->count(),
                'jp' => (int) $classRows->sum('jp'),
            ])
            ->sortByDesc('journals')
            ->values();

        return [
            'total_jurnal' => $rows->count(),
            'total_guru' => $rows->pluck('guru_mengajar_id')->filter()->unique()->count(),
            'total_kelas' => $rows->pluck('classroom_term_id')->filter()->unique()->count(),
            'total_mapel' => $rows->pluck('subject_id')->filter()->unique()->count(),
            'total_jp' => (int) $rows->sum('jp'),
            'jurnal_reguler' => $rows->where('type', 'regular')->count(),
            'jurnal_pengganti' => $rows->where('type', 'substitute')->count(),
            'hari_tercatat' => $rows->pluck('date')->filter()->unique()->count(),
            'total_hadir' => (int) $rows->sum('hadir'),
            'total_sakit' => (int) $rows->sum('sakit'),
            'total_izin' => (int) $rows->sum('izin'),
            'total_alpa' => (int) $rows->sum('alpa'),
            'total_bolos' => (int) $rows->sum('bolos'),
            'by_teacher' => $byTeacher,
            'by_class' => $byClass,
        ];
    }

    /** @return array<string, string> */
    private function filterLabels(array $filters, ?AcademicTerm $term): array
    {
        $teacher = $filters['teacher_id'] ? Teacher::find($filters['teacher_id']) : null;
        $classroomTerm = $filters['classroom_term_id'] ? ClassroomTerm::find($filters['classroom_term_id']) : null;
        $subject = $filters['subject_id'] ? DiniyyahSubject::find($filters['subject_id']) : null;

        return [
            'Periode' => $term ? trim(($term->academicYear?->name ?? '').' - '.$term->name) : 'Semua periode',
            'Tanggal' => $filters['date_from'] || $filters['date_until']
                ? ($filters['date_from'] ?? 'awal').' s.d. '.($filters['date_until'] ?? 'akhir')
                : 'Semua tanggal',
            'Guru' => $teacher?->name ?? 'Semua guru',
            'Kelas' => $classroomTerm?->name ?? 'Semua kelas',
            'Mapel' => $subject?->name ?? 'Semua mapel',
            'Tipe jurnal' => match ($filters['type']) {
                'regular' => 'Reguler',
                'substitute' => 'Pengganti',
                default => 'Semua tipe',
            },
            'Pencarian' => $filters['search'] ?? 'Tidak ada',
        ];
    }

    private function sessionTime(DiniyyahClassJournal $journal): ?string
    {
        $startsAt = $journal->session_starts_at;
        $endsAt = $journal->session_ends_at;

        if (! $startsAt && ! $endsAt) {
            return null;
        }

        $format = static function (?string $time): ?string {
            if (! $time) {
                return null;
            }

            try {
                return Carbon::parse($time)->format('H:i');
            } catch (\Throwable) {
                return $time;
            }
        };

        return collect([$format($startsAt), $format($endsAt)])->filter()->implode(' - ') ?: null;
    }

    private function whereEffectiveTeacher(Builder $query, int $teacherId): void
    {
        $query->where(function (Builder $query) use ($teacherId): void {
            $query->where(function (Builder $query) use ($teacherId): void {
                $query->whereNull('substitute_teacher_id')
                    ->whereHas('teacherAssignment', fn (Builder $q) => $q->where('teacher_id', $teacherId));
            })->orWhere('substitute_teacher_id', $teacherId);
        });
    }
}
