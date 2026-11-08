<?php

namespace App\Services;

use App\Models\DiniyyahLedgerSnapshot;

/** Read only: every displayed value comes from the saved ledger, including KKM. */
class DiniyyahLedgerPresentation
{
    private const GROUPS = [
        'subjects' => ['Mata pelajaran', 'E8F4EC', '166534'],
        'suluk' => ['Suluk', 'FFF4D9', '92400E'],
        'practice' => ['Praktik Ibadah', 'F0ECFA', '5B21B6'],
        'tahfidz_exam' => ['UAS Tahfidz', 'E8F1FC', '1E40AF'],
        'tahfidz_daily' => ['Harian Tahfidz', 'E8F1FC', '1E40AF'],
        'attendance' => ['Presensi', 'F1F5F9', '334155'],
        'summary' => ['Hasil akhir', 'DDEFE5', '14532D'],
    ];

    public function build(DiniyyahLedgerSnapshot $snapshot): array
    {
        $combined = ($snapshot->snapshot_data['layout_version'] ?? '') === 'combined-v1';
        $columns = collect($snapshot->snapshot_data['columns'] ?? [])->map(function ($column) {
            $group = $this->group($column);
            $label = trim(preg_replace('/^penilaian\s+/iu', '', str_replace('_', ' ', $column['label'] ?? 'Nilai')));

            return $column + ['group' => $group, 'display_label' => $label, 'numeric' => true, 'kkm' => null, 'tested_material' => null];
        });
        $columns = $columns->concat(collect([
            ['key' => $combined ? 'combined_total' : 'legacy_total', 'display_label' => 'Total', 'kind' => 'total'],
            ['key' => $combined ? 'combined_average' : 'legacy_average', 'display_label' => 'Rata-rata', 'kind' => 'average'],
            ['key' => $combined ? 'combined_rank' : 'legacy_rank', 'display_label' => 'Peringkat', 'kind' => 'rank'],
        ])->map(fn ($c) => $c + ['group' => 'summary', 'numeric' => true, 'kkm' => null, 'tested_material' => null]));
        $groups = collect(self::GROUPS)->map(function ($style, $id) use ($columns) {
            return ['id' => $id, 'label' => $style[0], 'background' => $style[1], 'color' => $style[2], 'columns' => $columns->where('group', $id)->values()->all()];
        })->filter(fn ($g) => count($g['columns']) > 0)->values();
        $columns = $groups->flatMap(fn ($g) => $g['columns'])->values();
        $rows = $snapshot->rows->sortBy('row_number')->map(function ($row) use ($columns, $combined) {
            $stored = $row->cells->keyBy('column_key');
            $values = $columns->mapWithKeys(function ($column) use ($stored, $row) {
                $cell = $stored->get($column['key']);
                $raw = match ($column['key']) {
                    'legacy_total' => $row->total_diniyyah_score,
                    'legacy_average' => $row->average_diniyyah_score,
                    'legacy_rank' => $row->rank_in_class,
                    default => $column['numeric'] ? $cell?->value_numeric : $cell?->value_text,
                };
                $missing = $raw === null || $raw === '';

                return [$column['key'] => ['raw' => $raw, 'display' => $this->format($raw, $column['numeric'], ($column['kind'] ?? '') === 'rank' || $column['group'] === 'attendance'),
                    'missing' => $missing, 'below' => ! $missing && $column['kkm'] !== null && is_numeric($raw) && (float) $raw < (float) $column['kkm']]];
            });
            $total = $values->get($combined ? 'combined_total' : 'legacy_total');

            return ['number' => $row->row_number, 'name' => $row->student_name, 'nis' => $row->student_nis,
                'search' => mb_strtolower($row->student_name.' '.$row->student_nis), 'complete' => ! $total['missing'],
                'below' => $values->contains(fn ($v) => $v['below']),
                'below_groups' => $columns->filter(fn ($column) => $values[$column['key']]['below'])->pluck('group')->unique()->values()->all(),
                'values' => $values->all()];
        })->values();
        $panels = collect();
        $panel = [];
        $size = 0;
        foreach ($groups as $group) {
            foreach (array_chunk($group['columns'], 8) as $chunk) {
                if ($size + count($chunk) > 8 && $panel) {
                    $panels->push($panel);
                    $panel = [];
                    $size = 0;
                }
                $panel[] = array_replace($group, ['columns' => $chunk]);
                $size += count($chunk);
            }
        }
        if ($panel) {
            $panels->push($panel);
        }

        return compact('combined', 'groups', 'columns', 'rows', 'panels');
    }

    public function format(mixed $value, bool $numeric = true, bool $integer = false): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (! $numeric) {
            return (string) $value;
        }

        return $integer ? (string) (int) $value : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    private function group(array $column): string
    {
        if (($column['source_type'] ?? '') === 'student_attendance_recap') {
            return 'attendance';
        }
        if (in_array($column['key'] ?? '', ['tahfidz_juz', 'tahfidz_uas'], true)) {
            return 'tahfidz_exam';
        }
        if (($column['source_type'] ?? '') === 'tahfidz_snapshot') {
            return 'tahfidz_daily';
        }
        $subject = mb_strtolower(($column['subject_code'] ?? '').' '.($column['subject_name'] ?? '').' '.($column['label'] ?? ''));
        if (str_contains($subject, 'suluk')) {
            return 'suluk';
        }
        if (preg_match('/praktik|praktek/u', $subject) || ($column['assessment_method'] ?? '') === 'practical') {
            return 'practice';
        }

        return 'subjects';
    }
}
