<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ClassroomTerm;
use App\Models\Student;
use App\Models\TasmiRecord;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Builds Tasmi' reports from one authoritative query scope. Controllers use
 * this service for pages, detail guards, reminders, and exports so a filter
 * can never broaden the data visible to its current user.
 */
class TasmiReportService
{
    /** @return Builder<TasmiRecord> */
    public function forExaminer(Teacher $teacher): Builder
    {
        return $this->baseQuery()
            ->where('tasmi_records.examiner_teacher_id', $teacher->id);
    }

    /** @return Builder<TasmiRecord> */
    public function forHomeroom(Teacher $teacher): Builder
    {
        return $this->baseQuery()->whereExists(function (QueryBuilder $query) use ($teacher): void {
            $query->selectRaw('1')
                ->from('homeroom_assignments')
                ->whereColumn('homeroom_assignments.classroom_term_id', 'tasmi_records.classroom_term_id')
                ->where('homeroom_assignments.teacher_id', $teacher->id)
                ->where(function (QueryBuilder $dates): void {
                    $dates->whereNull('homeroom_assignments.starts_at')
                        ->orWhereColumn('homeroom_assignments.starts_at', '<=', 'tasmi_records.exam_date');
                })
                ->where(function (QueryBuilder $dates): void {
                    $dates->whereNull('homeroom_assignments.ends_at')
                        ->orWhereColumn('homeroom_assignments.ends_at', '>=', 'tasmi_records.exam_date');
                });
        });
    }

    /** @return Builder<TasmiRecord> */
    public function forManagement(): Builder
    {
        return $this->baseQuery();
    }

    /** @param array<string, mixed> $filters
     *  @return Builder<TasmiRecord>
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['academic_term_id'] ?? null, fn (Builder $q, $value) => $q->where('tasmi_records.academic_term_id', $value))
            ->when($filters['classroom_term_id'] ?? null, fn (Builder $q, $value) => $q->where('tasmi_records.classroom_term_id', $value))
            ->when($filters['student_id'] ?? null, fn (Builder $q, $value) => $q->where('tasmi_records.student_id', $value))
            ->when($filters['examiner_teacher_id'] ?? null, fn (Builder $q, $value) => $q->where('tasmi_records.examiner_teacher_id', $value))
            ->when($filters['exam_type'] ?? null, fn (Builder $q, $value) => $q->where('tasmi_records.exam_type', $value))
            ->when($filters['predicate'] ?? null, fn (Builder $q, $value) => $q->where('tasmi_records.predicate', $value))
            ->when($filters['juz'] ?? null, function (Builder $q, $value): void {
                $q->where('tasmi_records.juz_start', '<=', $value)
                    ->where('tasmi_records.juz_end', '>=', $value);
            })
            ->when($filters['date_from'] ?? null, fn (Builder $q, $value) => $q->whereDate('tasmi_records.exam_date', '>=', $value))
            ->when($filters['date_until'] ?? null, fn (Builder $q, $value) => $q->whereDate('tasmi_records.exam_date', '<=', $value))
            ->when($filters['search'] ?? null, function (Builder $q, $value): void {
                $search = trim((string) $value);
                $q->whereHas('student', function (Builder $students) use ($search): void {
                    $students->where('name', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%");
                });
            });
    }

    /** @return array{summary: array<string, mixed>, records: LengthAwarePaginator} */
    public function paginate(Builder $query, int $perPage = 20): array
    {
        $summary = $this->summary($query);
        $records = (clone $query)
            ->latest('tasmi_records.exam_date')
            ->latest('tasmi_records.id')
            ->paginate($perPage)
            ->withQueryString();

        return compact('summary', 'records');
    }

    /** @return Collection<int, TasmiRecord> */
    public function recordsForExport(Builder $query): Collection
    {
        return (clone $query)
            ->latest('tasmi_records.exam_date')
            ->latest('tasmi_records.id')
            ->get();
    }

    /** @return array<string, Collection> */
    public function options(Builder $query, bool $includeExaminers = false): array
    {
        $termIds = (clone $query)->reorder()->distinct()->pluck('tasmi_records.academic_term_id');
        $classroomTermIds = (clone $query)->reorder()->distinct()->pluck('tasmi_records.classroom_term_id')->filter();
        $studentIds = (clone $query)->reorder()->distinct()->pluck('tasmi_records.student_id');
        $examinerIds = (clone $query)->reorder()->distinct()->pluck('tasmi_records.examiner_teacher_id');

        return [
            'terms' => AcademicTerm::query()->with('academicYear')->whereIn('id', $termIds)->orderByDesc('starts_at')->get(),
            'classroomTerms' => ClassroomTerm::query()->with('classroom')->whereIn('id', $classroomTermIds)->orderBy('name')->get(),
            'students' => Student::query()->whereIn('id', $studentIds)->orderBy('name')->get(),
            'examiners' => $includeExaminers
                ? Teacher::query()->whereIn('id', $examinerIds)->orderBy('name')->get()
                : collect(),
        ];
    }

    /** @param array<string, mixed> $filters
     *  @param array<string, Collection> $options
     *  @return array<string, string>
     */
    public function filterLabels(array $filters, array $options): array
    {
        $term = $options['terms']->firstWhere('id', (int) ($filters['academic_term_id'] ?? 0));
        $classroomTerm = $options['classroomTerms']->firstWhere('id', (int) ($filters['classroom_term_id'] ?? 0));
        $student = $options['students']->firstWhere('id', (int) ($filters['student_id'] ?? 0));
        $examiner = $options['examiners']->firstWhere('id', (int) ($filters['examiner_teacher_id'] ?? 0));

        return [
            'Semester' => $term?->name ?? 'Semua semester',
            'Tanggal' => collect([$filters['date_from'] ?? null, $filters['date_until'] ?? null])->filter()->implode(' s.d. ') ?: 'Semua tanggal',
            'Kelas' => $classroomTerm?->classroom?->name ?? $classroomTerm?->name ?? 'Semua kelas',
            'Santri' => $student?->name ?? 'Semua santri',
            'Jenis Tasmi\'' => TasmiRecord::examTypeOptions()[$filters['exam_type'] ?? ''] ?? 'Semua jenis',
            'Juz' => ($filters['juz'] ?? null) ? 'Mencakup Juz '.$filters['juz'] : 'Semua juz',
            'Predikat' => TasmiRecord::predicateLabel($filters['predicate'] ?? null) ?? 'Semua predikat',
            'PJ Tasmi\'' => $examiner?->name ?? 'Semua PJ Tasmi\'',
        ];
    }

    /** @return array<string, mixed> */
    public function exportReport(Builder $query, array $filters, array $options): array
    {
        $records = $this->recordsForExport($query);

        return [
            'summary' => $this->summary($query),
            'rows' => $this->rows($records),
            'filter_labels' => $this->filterLabels($filters, $options),
        ];
    }

    /** @return array<string, mixed> */
    public function summary(Builder $query): array
    {
        $typeCounts = (clone $query)
            ->reorder()
            ->selectRaw('tasmi_records.exam_type, COUNT(*) as aggregate')
            ->groupBy('tasmi_records.exam_type')
            ->pluck('aggregate', 'exam_type');
        $predicateCounts = (clone $query)
            ->reorder()
            ->selectRaw('tasmi_records.predicate, COUNT(*) as aggregate')
            ->groupBy('tasmi_records.predicate')
            ->pluck('aggregate', 'predicate');

        return [
            'total_records' => (clone $query)->count(),
            'total_students' => (clone $query)->distinct()->count('tasmi_records.student_id'),
            'total_classes' => (clone $query)->whereNotNull('tasmi_records.classroom_term_id')->distinct()->count('tasmi_records.classroom_term_id'),
            'one_juz' => (int) ($typeCounts[TasmiRecord::EXAM_TYPE_ONE_JUZ] ?? 0),
            'five_juz' => (int) ($typeCounts[TasmiRecord::EXAM_TYPE_FIVE_JUZ] ?? 0),
            'predicates' => collect(TasmiRecord::predicateOptions())->mapWithKeys(
                fn (string $label, string $value) => [$value => (int) ($predicateCounts[$value] ?? 0)]
            )->all(),
        ];
    }

    /** @param Collection<int, TasmiRecord> $records
     *  @return Collection<int, array<string, string|int|null>>
     */
    public function rows(Collection $records): Collection
    {
        return $records->values()->map(function (TasmiRecord $record): array {
            return [
                'date' => $record->exam_date?->format('d-m-Y'),
                'hijri_date' => $record->hijri_date,
                'term' => $record->academicTerm?->name,
                'student' => $record->student?->name,
                'nis' => $record->student?->nis,
                'classroom' => $record->classroomTerm?->classroom?->name ?? $record->classroomTerm?->name,
                'exam_type' => TasmiRecord::examTypeOptions()[$record->exam_type] ?? $record->exam_type,
                'juz' => $record->juz_range_label,
                'predicate' => TasmiRecord::predicateLabel($record->predicate),
                'notes' => $record->notes,
                'examiner' => $record->examinerTeacher?->name,
                'input_by' => $record->inputBy?->name,
                'input_at' => $record->input_at?->timezone('Asia/Jakarta')->format('d-m-Y H:i'),
                'updated_at' => $record->updated_at?->timezone('Asia/Jakarta')->format('d-m-Y H:i'),
            ];
        });
    }

    /** @return Builder<TasmiRecord> */
    private function baseQuery(): Builder
    {
        return TasmiRecord::query()->with([
            'student',
            'classroomTerm.classroom',
            'examinerTeacher',
            'academicTerm.academicYear',
            'inputBy',
            'lastUpdatedBy',
        ]);
    }
}
