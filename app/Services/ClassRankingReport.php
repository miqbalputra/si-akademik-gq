<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahLedgerSnapshot;

/** Read-only projection of saved ledgers. Never assigns or recalculates ranks. */
class ClassRankingReport
{
    public function build(AcademicTerm $term, ?int $classroomId = null, bool $includeArchived = false): array
    {
        $classrooms = ClassroomTerm::where('academic_term_id', $term->id)->when($classroomId, fn ($q) => $q->whereKey($classroomId))->orderBy('name')->orderBy('id')->get();
        $snapshots = DiniyyahLedgerSnapshot::where('academic_term_id', $term->id)->whereIn('classroom_term_id', $classrooms->pluck('id'))
            ->with('rows.cells', 'rows.classEnrollment.student')->orderByDesc('id')->get()->unique('classroom_term_id')->keyBy('classroom_term_id');
        $classes = $classrooms->map(function ($classroom) use ($snapshots, $includeArchived) {
            $snapshot = $snapshots->get($classroom->id);
            $combined = ($snapshot?->snapshot_data['layout_version'] ?? '') === 'combined-v1';
            $rows = ($snapshot?->rows ?? collect())->filter(fn ($row) => $includeArchived || $row->classEnrollment?->student?->status === 'active')
                ->map(function ($row) use ($combined) {
                    $cells = $row->cells->keyBy('column_key');
                    $total = $combined ? $cells->get('combined_total')?->value_numeric : $row->total_diniyyah_score;
                    $average = $combined ? $cells->get('combined_average')?->value_numeric : $row->average_diniyyah_score;
                    $rank = $combined ? $cells->get('combined_rank')?->value_numeric : $row->rank_in_class;
                    $rank = $rank !== null && (int) $rank > 0 ? (int) $rank : null;
                    $tahfidz = collect(['tahfidz_uas', 'tahfidz_sabaq', 'tahfidz_sabqi', 'tahfidz_manzil'])->map(fn ($key) => $cells->get($key)?->value_numeric);

                    return ['name' => $row->student_name, 'nis' => $row->student_nis, 'number' => $row->row_number,
                        'rank' => $rank, 'total' => $total, 'average' => $average,
                        'diniyyah_total' => $row->total_diniyyah_score,
                        'tahfidz_total' => $combined && $tahfidz->every(fn ($v) => $v !== null) ? round($tahfidz->sum(), 2) : null,
                        'complete' => $total !== null && $average !== null && $rank !== null,
                        'archived' => $row->classEnrollment?->student?->status !== 'active'];
                })->sortBy(fn ($row) => [$row['rank'] ?? PHP_INT_MAX, $row['number'], $row['name']])->values();

            return ['id' => $classroom->id, 'name' => $classroom->name, 'snapshot_id' => $snapshot?->id,
                'status' => $snapshot?->status, 'generated_at' => $snapshot?->generated_at?->format('d/m/Y H:i'),
                'scope_label' => ! $snapshot ? 'Belum ada leger' : ($combined ? 'Diniyyah + Tahfidz' : 'Diniyyah saja (leger lama)'),
                'combined' => $combined, 'rows' => $rows, 'complete' => $rows->where('complete', true)->count(),
                'incomplete' => $rows->where('complete', false)->count()];
        })->all();

        return ['term' => $term->loadMissing('academicYear'), 'classes' => $classes, 'include_archived' => $includeArchived,
            'summary' => ['classes' => count($classes), 'ledgers' => collect($classes)->whereNotNull('snapshot_id')->count(),
                'students' => collect($classes)->sum(fn ($class) => $class['rows']->count()),
                'complete' => collect($classes)->sum('complete'), 'incomplete' => collect($classes)->sum('incomplete')]];
    }
}
