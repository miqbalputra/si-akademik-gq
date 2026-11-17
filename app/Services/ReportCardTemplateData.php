<?php

namespace App\Services;

use App\Models\DiniyyahLedgerRow;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\ReportCard;

/** Freeze printable values from the ledger. Rendering never reads live scores. */
class ReportCardTemplateData
{
    public function capture(DiniyyahLedgerSnapshot $snapshot, DiniyyahLedgerRow $row, ReportCard $card): array
    {
        $snapshot->loadMissing('rows.cells');
        $columns = collect($snapshot->snapshot_data['columns'] ?? [])->keyBy('assessment_set_id');
        $subjects = [];
        $practice = [];
        foreach ($card->lines()->orderBy('sort_order')->get() as $line) {
            $column = $columns->get($line->source_id, []);
            if (array_key_exists('appears_on_report', $column) && ! $column['appears_on_report']) {
                continue;
            }
            $scores = $snapshot->rows->flatMap(fn ($r) => $r->cells->where('source_type', 'diniyyah_assessment_set')->where('source_id', $line->source_id))->pluck('value_numeric')->filter(fn ($v) => $v !== null);
            $entry = [
                'source_id' => $line->source_id,
                'name' => $column['subject_name'] ?? $line->subject_name,
                'arabic_name' => $column['subject_arabic_name'] ?? $line->subject_arabic_name,
                'material' => $column['tested_material'] ?? $line->tested_material,
                'kkm' => $line->kkm,
                'kkm_revision_id' => $line->kkm_revision_id,
                'score' => $line->score_numeric === null ? null : round((float) $line->score_numeric, 1),
                'score_raw' => $line->score_numeric === null ? null : (float) $line->score_numeric,
                'class_average' => $scores->isEmpty() ? null : round($scores->avg(), 1),
            ];
            $isPractice = ($column['assessment_method'] ?? '') === 'practical'
                || preg_match('/praktik|praktek/iu', ($column['subject_code'] ?? '').' '.$entry['name']);
            if ($isPractice) {
                $practice[] = $entry;
            } else {
                $subjects[] = $entry;
            }
        }
        $cells = $row->cells->keyBy('column_key');
        $tahfidz = [];
        foreach (['tahfidz_juz', 'tahfidz_uas', 'tahfidz_sabaq', 'tahfidz_sabqi', 'tahfidz_manzil'] as $key) {
            $tahfidz[$key] = $key === 'tahfidz_juz' ? $cells->get($key)?->value_text : $cells->get($key)?->value_numeric;
        }
        $snapshot->loadMissing('academicTerm.academicYear', 'classroomTerm.homeroomAssignments.teacher');
        $teachers = $snapshot->classroomTerm->homeroomAssignments
            ->filter(fn ($a) => (! $a->starts_at || $a->starts_at->lte(today())) && (! $a->ends_at || $a->ends_at->gte(today())))
            ->pluck('teacher.name')->filter()->unique()->values();

        return [
            'ledger_snapshot_id' => $snapshot->id,
            'date_policy' => 'admin-period-v1',
            'note_policy' => 'top-three-v2',
            'semester' => $snapshot->academicTerm->semester,
            'student_name' => $row->student_name,
            'student_nis' => $row->student_nis,
            'classroom_name' => $snapshot->classroomTerm->name,
            'academic_year' => preg_replace('/^(\d{4})\s*[-\/]\s*(\d{4})$/', '$1/$2', trim($snapshot->academicTerm->academicYear?->name ?? '')),
            'subjects' => $subjects, 'practice' => $practice, 'tahfidz' => $tahfidz,
            'subject_average' => count($subjects) ? round(collect($subjects)->avg('score_raw'), 2) : null,
            'total' => $cells->get('combined_total')?->value_numeric ?? $row->total_diniyyah_score,
            'rank' => $cells->get('combined_rank')?->value_numeric ?? $row->rank_in_class,
            'attendance' => $card->attendance->only(['sick_count', 'permission_count', 'absent_count']),
            'options' => [
                'issue_date' => $snapshot->academicTerm->report_issue_date?->format('Y-m-d'),
                'issue_place' => config('report-cards.issue_place'),
                'homeroom_name' => $teachers->count() === 1 ? $teachers->first() : null,
                'principal_name' => config('report-cards.principal_name'),
                'principal_title' => config('report-cards.principal_title'),
                'note' => $card->homeroom_note,
                'promotion_status' => null, 'next_class' => null,
            ],
        ];
    }

    public function words(?float $score): string
    {
        if ($score === null) {
            return '—';
        }
        $tenths = (int) round($score * 10);
        $whole = intdiv($tenths, 10);
        $decimal = $tenths % 10;
        $digits = ['Nol', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
        $words = match (true) {
            $whole <= 11 => $digits[$whole],
            $whole < 20 => $digits[$whole - 10].' Belas',
            $whole < 100 => trim($digits[intdiv($whole, 10)].' Puluh '.($whole % 10 ? $digits[$whole % 10] : '')),
            $whole === 100 => 'Seratus',
            default => (string) $whole,
        };

        return $words.($decimal ? ' Koma '.$digits[$decimal] : '');
    }

    public function missing(array $data): array
    {
        $missing = [];
        if (! in_array($data['semester'], ['ganjil', 'genap'], true)) {
            $missing[] = 'Semester yang valid';
        }
        if (empty($data['academic_year'])) {
            $missing[] = 'Tahun ajaran';
        }
        foreach (['issue_date' => 'Tanggal rapor', 'homeroom_name' => 'Wali kelas aktif yang tunggal', 'principal_name' => 'Nama kepala', 'principal_title' => 'Jabatan kepala', 'issue_place' => 'Tempat penerbitan'] as $key => $label) {
            if (empty($data['options'][$key])) {
                $missing[] = $label;
            }
        }
        foreach ($data['tahfidz'] as $key => $value) {
            if ($value === null || $value === '') {
                $missing[] = str_replace('_', ' ', $key);
            }
        }
        if (! count($data['subjects'])) {
            $missing[] = 'Mata pelajaran';
        }
        foreach (array_merge($data['subjects'], $data['practice']) as $line) {
            if ($line['score'] === null || $line['kkm'] === null) {
                $missing[] = 'Nilai / KKM '.$line['name'];
            }
        }
        if ($data['semester'] === 'genap') {
            if (empty($data['options']['promotion_status'])) {
                $missing[] = 'Keputusan kenaikan kelas';
            }
            if (($data['options']['promotion_status'] ?? '') === 'promoted' && empty($data['options']['next_class'])) {
                $missing[] = 'Kelas tujuan';
            }
        }

        return $missing;
    }
}
