<?php

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentResult;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahLedgerSnapshot;
use DomainException;
use Illuminate\Support\Facades\DB;

class DiniyyahLedgerGenerator
{
    public function __construct(private readonly AttendanceRecapService $attendanceRecapService) {}

    public function generate(ClassroomTerm $classroomTerm, ?int $generatedBy = null): DiniyyahLedgerSnapshot
    {
        return DB::transaction(function () use ($classroomTerm, $generatedBy) {
            $classroomTerm->loadMissing('academicTerm');

            $existingSnapshot = DiniyyahLedgerSnapshot::query()
                ->where('academic_term_id', $classroomTerm->academic_term_id)
                ->where('classroom_term_id', $classroomTerm->id)
                ->first();

            if ($existingSnapshot && in_array($existingSnapshot->status, ['locked', 'published'], true)) {
                throw new DomainException('Leger sudah dikunci dan tidak bisa digenerate ulang.');
            }

            $assessmentSets = DiniyyahAssessmentSet::query()
                ->with('classSubject.subject')
                ->whereHas('classSubject', function ($query) use ($classroomTerm) {
                    $query->where('classroom_term_id', $classroomTerm->id)
                        ->where('appears_on_ledger', true)
                        ->where('is_active', true);
                })
                ->where('appears_on_ledger', true)
                ->orderBy('sort_order')
                ->get();

            $scoreColumns = $assessmentSets->map(fn (DiniyyahAssessmentSet $set) => [
                'key' => $this->columnKey($set),
                'label' => $set->title,
                'subject_name' => $set->classSubject?->subject?->name,
                'source_type' => 'diniyyah_assessment_set',
                'assessment_set_id' => $set->id,
                'status' => $set->status,
            ])->values();
            $attendanceColumns = collect($this->attendanceColumns());
            $columns = $scoreColumns->merge($attendanceColumns)->values();

            $snapshot = DiniyyahLedgerSnapshot::updateOrCreate(
                [
                    'academic_term_id' => $classroomTerm->academic_term_id,
                    'classroom_term_id' => $classroomTerm->id,
                ],
                [
                    'title' => 'Leger Diniyyah '.$classroomTerm->name,
                    'status' => 'draft',
                    'generated_at' => now(),
                    'generated_by' => $generatedBy,
                    'snapshot_data' => [
                        'columns' => $columns->all(),
                    ],
                ],
            );

            $snapshot->rows()->delete();

            $enrollments = ClassEnrollment::query()
                ->with('student')
                ->where('classroom_term_id', $classroomTerm->id)
                ->where('status', 'active')
                ->orderBy('roll_number')
                ->orderBy('student_id')
                ->get();

            $rankPayload = [];
            $issues = [];
            $completeRows = 0;
            $incompleteRows = 0;
            $missingCells = 0;

            if ($assessmentSets->isEmpty()) {
                $issues[] = [
                    'level' => 'blocking',
                    'code' => 'no_assessment_columns',
                    'message' => 'Belum ada mapel atau set penilaian yang muncul di leger.',
                ];
            }

            if ($enrollments->isEmpty()) {
                $issues[] = [
                    'level' => 'blocking',
                    'code' => 'no_active_students',
                    'message' => 'Belum ada santri aktif pada kelas ini.',
                ];
            }

            foreach ($assessmentSets->where('status', '!=', 'validated') as $assessmentSet) {
                $issues[] = [
                    'level' => 'blocking',
                    'code' => 'assessment_not_validated',
                    'assessment_set_id' => $assessmentSet->id,
                    'message' => "Set penilaian {$assessmentSet->title} belum tervalidasi.",
                ];
            }

            foreach ($enrollments as $index => $enrollment) {
                $row = $snapshot->rows()->create([
                    'class_enrollment_id' => $enrollment->id,
                    'row_number' => $index + 1,
                    'student_name' => $enrollment->student->name,
                    'student_nis' => $enrollment->student->nis,
                ]);

                $numericScores = [];
                $isRowComplete = $assessmentSets->isNotEmpty();

                foreach ($assessmentSets as $assessmentSet) {
                    $result = DiniyyahAssessmentResult::query()
                        ->where('diniyyah_assessment_set_id', $assessmentSet->id)
                        ->where('class_enrollment_id', $enrollment->id)
                        ->first();

                    $score = $result?->final_score !== null ? (float) $result->final_score : null;
                    $isCellComplete = $result?->is_complete === true && $score !== null;

                    if ($isCellComplete) {
                        $numericScores[] = $score;
                    } else {
                        $isRowComplete = false;
                        $missingCells++;
                    }

                    $row->cells()->create([
                        'column_key' => $this->columnKey($assessmentSet),
                        'label' => $assessmentSet->title,
                        'source_type' => 'diniyyah_assessment_set',
                        'source_id' => $assessmentSet->id,
                        'value_numeric' => $score,
                        'value_text' => $score === null ? null : (string) $score,
                        'sort_order' => $assessmentSet->sort_order,
                    ]);
                }

                $attendanceRecap = $this->attendanceRecapService->recapForEnrollment($classroomTerm->academic_term_id, $enrollment->id);

                foreach ($this->attendanceColumns() as $column) {
                    $value = $attendanceRecap[$column['count_key']];

                    $row->cells()->create([
                        'column_key' => $column['key'],
                        'label' => $column['label'],
                        'source_type' => 'student_attendance_recap',
                        'source_id' => null,
                        'value_numeric' => $value,
                        'value_text' => (string) $value,
                        'sort_order' => $column['sort_order'],
                    ]);
                }

                $total = $isRowComplete ? round(array_sum($numericScores), 2) : null;
                $average = $isRowComplete && count($numericScores) > 0 ? round($total / count($numericScores), 2) : null;

                $row->update([
                    'total_diniyyah_score' => $total,
                    'average_diniyyah_score' => $average,
                ]);

                if ($isRowComplete) {
                    $completeRows++;
                    $rankPayload[] = ['row' => $row, 'total' => $total];
                } else {
                    $incompleteRows++;
                    $issues[] = [
                        'level' => 'blocking',
                        'code' => 'student_scores_incomplete',
                        'class_enrollment_id' => $enrollment->id,
                        'student_name' => $enrollment->student->name,
                        'message' => "Nilai {$enrollment->student->name} belum lengkap.",
                    ];
                }
            }

            collect($rankPayload)
                ->sortByDesc('total')
                ->values()
                ->each(fn (array $payload, int $index) => $payload['row']->update(['rank_in_class' => $index + 1]));

            $snapshot->update([
                'snapshot_data' => [
                    'columns' => $columns->all(),
                    'summary' => [
                        'total_columns' => $assessmentSets->count(),
                        'score_columns' => $assessmentSets->count(),
                        'attendance_columns' => $attendanceColumns->count(),
                        'total_students' => $enrollments->count(),
                        'complete_rows' => $completeRows,
                        'incomplete_rows' => $incompleteRows,
                        'missing_cells' => $missingCells,
                        'blocking_issues' => collect($issues)->where('level', 'blocking')->count(),
                    ],
                    'issues' => $issues,
                ],
            ]);

            return $snapshot->fresh(['rows.cells']);
        });
    }

    private function columnKey(DiniyyahAssessmentSet $assessmentSet): string
    {
        return 'assessment_'.$assessmentSet->id;
    }

    /** @return list<array{key: string, label: string, source_type: string, count_key: string, sort_order: int}> */
    private function attendanceColumns(): array
    {
        return [
            [
                'key' => 'attendance_sick',
                'label' => 'Presensi Sakit',
                'source_type' => 'student_attendance_recap',
                'count_key' => 'sick_count',
                'sort_order' => 10010,
            ],
            [
                'key' => 'attendance_permission',
                'label' => 'Presensi Izin',
                'source_type' => 'student_attendance_recap',
                'count_key' => 'permission_count',
                'sort_order' => 10020,
            ],
            [
                'key' => 'attendance_absent',
                'label' => 'Presensi Alpa',
                'source_type' => 'student_attendance_recap',
                'count_key' => 'absent_count',
                'sort_order' => 10030,
            ],
        ];
    }
}
