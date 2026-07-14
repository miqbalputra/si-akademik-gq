<?php

namespace App\Services;

use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahLedgerRow;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\ReportCard;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReportCardGenerator
{
    public function __construct(private readonly AttendanceRecapService $attendanceRecapService) {}

    public function generateFromLedgerSnapshot(DiniyyahLedgerSnapshot $snapshot, ?int $generatedBy = null): int
    {
        $snapshot->loadMissing('rows.cells');
        $this->ensureSnapshotCanGenerateReportCards($snapshot);

        return DB::transaction(function () use ($snapshot, $generatedBy) {
            $count = 0;

            foreach ($snapshot->rows->whereNotNull('rank_in_class') as $row) {
                $this->generateFromLedgerRow($snapshot, $row, $generatedBy);
                $count++;
            }

            return $count;
        });
    }

    public function generateFromLedgerRow(DiniyyahLedgerSnapshot $snapshot, DiniyyahLedgerRow $row, ?int $generatedBy = null): ReportCard
    {
        $this->ensureSnapshotCanGenerateReportCards($snapshot);

        if ($row->rank_in_class === null || $row->total_diniyyah_score === null || $row->average_diniyyah_score === null) {
            throw new DomainException('Rapor tidak bisa dibuat dari baris leger yang belum lengkap.');
        }

        $row->loadMissing('classEnrollment.student');
        $scoreCells = $row->cells()
            ->where('source_type', 'diniyyah_assessment_set')
            ->orderBy('sort_order')
            ->get();
        $assessmentSets = DiniyyahAssessmentSet::query()
            ->whereIn('id', $scoreCells->pluck('source_id')->filter()->all())
            ->get()
            ->keyBy('id');

        // Cari rapor yang sudah ada untuk enrollment+term+type ini. Bila sudah
        // ada, segarkan HANYA data nilai (total/average/rank) tanpa menurunkan
        // status workflow — jangan mengembalikan kartu yang sudah locked/published
        // kembali ke draft, karena itu mencabut akses wali dan menghapus audit
        // trail (locked_at/published_at/locked_by/published_by). Kartu baru tetap
        // dibuat dengan status 'draft'.
        $reportCard = ReportCard::firstWhere([
            'academic_term_id' => $snapshot->academic_term_id,
            'class_enrollment_id' => $row->class_enrollment_id,
            'report_type' => 'diniyyah',
        ]);

        $scoreData = [
            'classroom_term_id' => $snapshot->classroom_term_id,
            'student_id' => $row->classEnrollment->student_id,
            'total_score' => $row->total_diniyyah_score,
            'average_score' => $row->average_diniyyah_score,
            'rank_in_class' => $row->rank_in_class,
        ];

        if ($reportCard) {
            $reportCard->fill($scoreData)->save();
        } else {
            $reportCard = ReportCard::create(array_merge($scoreData, [
                'academic_term_id' => $snapshot->academic_term_id,
                'class_enrollment_id' => $row->class_enrollment_id,
                'report_type' => 'diniyyah',
                'status' => 'draft',
            ]));
        }

        $reportCard->lines()->delete();

        foreach ($scoreCells as $cell) {
            $assessmentSet = $assessmentSets->get($cell->source_id);
            $score = $cell->value_numeric === null ? null : (float) $cell->value_numeric;
            $kkm = $assessmentSet?->kkm;

            $reportCard->lines()->create([
                'line_type' => 'subject',
                'source_type' => $cell->source_type,
                'source_id' => $cell->source_id,
                'subject_name' => $cell->label,
                'tested_material' => $assessmentSet?->tested_material,
                'kkm' => $kkm,
                'score_numeric' => $score,
                'score_letter' => $score === null ? null : $this->scorePredicate($score),
                'score_words' => $score === null ? null : $this->scoreWords($score),
                'is_passed' => $score !== null && ($kkm === null || $score >= (float) $kkm),
                'sort_order' => $cell->sort_order,
            ]);
        }

        $attendance = $this->attendanceRecapService->syncReportCardAttendance($reportCard);

        $reportCard->snapshots()->create([
            'layout_version' => 'diniyyah-v1',
            'snapshot_data' => [
                'student' => [
                    'name' => $row->student_name,
                    'nis' => $row->student_nis,
                ],
                'summary' => [
                    'total_score' => $row->total_diniyyah_score,
                    'average_score' => $row->average_diniyyah_score,
                    'rank_in_class' => $row->rank_in_class,
                    'attendance' => [
                        'sick_count' => $attendance->sick_count,
                        'permission_count' => $attendance->permission_count,
                        'absent_count' => $attendance->absent_count,
                    ],
                    'classroom_name' => $snapshot->classroomTerm?->name,
                    'academic_term_id' => $snapshot->academic_term_id,
                    'classroom_term_id' => $snapshot->classroom_term_id,
                ],
                'lines' => $reportCard->lines()->orderBy('sort_order')->get()->toArray(),
            ],
            'generated_at' => now(),
            'generated_by' => $generatedBy,
        ]);

        return $reportCard->fresh(['lines', 'attendance', 'snapshots']);
    }

    private function ensureSnapshotCanGenerateReportCards(DiniyyahLedgerSnapshot $snapshot): void
    {
        if (! in_array($snapshot->status, ['locked', 'published'], true)) {
            throw new DomainException('Rapor hanya bisa dibuat dari leger yang sudah dikunci.');
        }

        if (($snapshot->snapshot_data['summary']['blocking_issues'] ?? 0) > 0) {
            throw new DomainException('Rapor tidak bisa dibuat karena leger masih memiliki masalah kelengkapan.');
        }

        if ($snapshot->rows->whereNotNull('rank_in_class')->isEmpty()) {
            throw new DomainException('Rapor tidak bisa dibuat karena belum ada baris leger yang lengkap.');
        }
    }

    private function scorePredicate(float $score): string
    {
        return match (true) {
            $score >= 90 => 'Mumtaz',
            $score >= 80 => 'Jayyid Jiddan',
            $score >= 70 => 'Jayyid',
            $score >= 60 => 'Maqbul',
            default => 'Perlu Bimbingan',
        };
    }

    private function scoreWords(float $score): string
    {
        $rounded = (int) round($score);

        if ($rounded < 0 || $rounded > 100) {
            return (string) round($score, 2);
        }

        $words = [
            0 => 'Nol',
            1 => 'Satu',
            2 => 'Dua',
            3 => 'Tiga',
            4 => 'Empat',
            5 => 'Lima',
            6 => 'Enam',
            7 => 'Tujuh',
            8 => 'Delapan',
            9 => 'Sembilan',
            10 => 'Sepuluh',
            11 => 'Sebelas',
        ];

        if ($rounded <= 11) {
            return $words[$rounded];
        }

        if ($rounded < 20) {
            return $words[$rounded - 10].' Belas';
        }

        if ($rounded < 100) {
            $tens = intdiv($rounded, 10);
            $ones = $rounded % 10;

            return trim($words[$tens].' Puluh '.($ones > 0 ? $words[$ones] : ''));
        }

        return 'Seratus';
    }
}
