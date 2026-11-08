<?php

namespace App\Services\Exports;

use App\Models\DiniyyahLedgerSnapshot;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class DiniyyahLedgerExporter
{
    public function __construct(private readonly SpreadsheetTheme $theme) {}

    public function export(?int $snapshotId, bool $includeArchivedStudents = false): string
    {
        $snapshot = DiniyyahLedgerSnapshot::with(['rows.cells', 'rows.classEnrollment.student', 'classroomTerm', 'academicTerm.academicYear'])->findOrFail($snapshotId);
        if (! $includeArchivedStudents) {
            $snapshot->setRelation('rows', $snapshot->rows
                ->filter(fn ($row): bool => $row->classEnrollment?->student?->status === 'active')
                ->values());
        }
        $columns = collect($snapshot->snapshot_data['columns'] ?? []);
        $combined = ($snapshot->snapshot_data['layout_version'] ?? '') === 'combined-v1';
        $summary = $snapshot->snapshot_data['summary'] ?? [];
        if (! $includeArchivedStudents) {
            $visibleEnrollmentIds = $snapshot->rows
                ->pluck('class_enrollment_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $issues = collect($snapshot->snapshot_data['issues'] ?? [])
                ->reject(fn (array $issue): bool => isset($issue['class_enrollment_id'])
                    && ! in_array((int) $issue['class_enrollment_id'], $visibleEnrollmentIds, true));
            $completeRows = $snapshot->rows->filter(fn ($row): bool => $combined ? $row->cells->firstWhere('column_key', 'combined_total')?->value_numeric !== null : $row->total_diniyyah_score !== null)->count();
            $summary['total_students'] = $snapshot->rows->count();
            $summary['complete_rows'] = $completeRows;
            $summary['incomplete_rows'] = $snapshot->rows->count() - $completeRows;
            $summary['blocking_issues'] = $issues->where('level', 'blocking')->count();
        }
        $headers = array_merge(['No', 'Nama', 'NIS'], $columns->pluck('label')->all(), ['Total', 'Rata-rata', 'Peringkat']);

        $workbook = $this->theme->workbook();
        $sheet = $workbook->getActiveSheet();
        $sheet->setTitle('Leger');
        $subtitle = collect([
            $snapshot->title,
            $snapshot->classroomTerm?->name,
            $snapshot->academicTerm?->name,
            $snapshot->academicTerm?->academicYear?->name,
        ])->filter()->join(' - ');
        $this->theme->title($sheet, $combined ? 'LEGER NILAI DINIYYAH & TAHFIDZ' : 'LEGER NILAI DINIYYAH', $subtitle ?: 'Rekap nilai diniyyah.', count($headers));
        $sheet->setCellValue('A5', 'Status');
        $sheet->setCellValue('B5', strtoupper((string) $snapshot->status));
        $sheet->setCellValue('A6', 'Dibuat');
        $sheet->setCellValue('B6', $snapshot->generated_at?->translatedFormat('d F Y, H:i') ?? '-');
        $headerRow = 8;
        $this->theme->tableHeader($sheet, $headerRow, $headers);
        foreach ($headers as $index => $label) {
            $sheet->setCellValueExplicit($this->lastColumn($index + 1).$headerRow, $label, DataType::TYPE_STRING);
        }

        $lastRow = $headerRow;
        foreach ($snapshot->rows->sortBy('row_number') as $row) {
            $lastRow++;
            $cells = $row->cells->keyBy('column_key');
            $values = [$row->row_number, $row->student_name, $row->student_nis ?? ''];
            foreach ($columns as $column) {
                $cell = $cells->get($column['key'] ?? '');
                $values[] = ($column['numeric'] ?? true) ? ($cell?->value_numeric === null ? '-' : (float) $cell->value_numeric) : ($cell?->value_text ?? '-');
            }
            $total = $combined ? $cells->get('combined_total')?->value_numeric : $row->total_diniyyah_score;
            $average = $combined ? $cells->get('combined_average')?->value_numeric : $row->average_diniyyah_score;
            $rank = $combined ? $cells->get('combined_rank')?->value_numeric : $row->rank_in_class;
            array_push($values, $total === null ? '-' : (float) $total, $average === null ? '-' : (float) $average, $rank === null ? '-' : (int) $rank);
            foreach ($values as $index => $value) {
                $sheet->setCellValueExplicit($this->lastColumn($index + 1).$lastRow, $value, is_int($value) || is_float($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            }
        }
        if ($lastRow === $headerRow) {
            $lastRow++;
            $sheet->setCellValue("A{$lastRow}", 'Belum ada data leger untuk snapshot ini.');
        }
        $this->theme->finaliseTable($sheet, $headerRow, $lastRow, count($headers));
        $sheet->getStyle('A'.($headerRow + 1).':A'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C'.($headerRow + 1).':'.$this->lastColumn(count($headers)).$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $summaryRow = $lastRow + 2;
        $sheet->mergeCells("A{$summaryRow}:D{$summaryRow}");
        $sheet->setCellValue("A{$summaryRow}", 'RINGKASAN KELENGKAPAN');
        $sheet->getStyle("A{$summaryRow}:D{$summaryRow}")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => SpreadsheetTheme::NEON_GREEN]]]);
        $summaryRow++;
        foreach (range($summaryRow, $summaryRow + 2) as $labelRow) {
            $sheet->mergeCells("A{$labelRow}:C{$labelRow}");
        }
        $sheet->setCellValue("A{$summaryRow}", 'Total santri');
        $sheet->setCellValue("D{$summaryRow}", $summary['total_students'] ?? 0);
        $sheet->setCellValue('A'.($summaryRow + 1), 'Data lengkap');
        $sheet->setCellValue('D'.($summaryRow + 1), $summary['complete_rows'] ?? 0);
        $sheet->setCellValue('A'.($summaryRow + 2), 'Belum lengkap');
        $sheet->setCellValue('D'.($summaryRow + 2), $summary['incomplete_rows'] ?? 0);
        if (! empty($summary['blocking_issues'])) {
            $sheet->setCellValue("E{$summaryRow}", 'Peringatan');
            $sheet->mergeCells("F{$summaryRow}:J{$summaryRow}");
            $sheet->setCellValue("F{$summaryRow}", $summary['blocking_issues'].' masalah kelengkapan perlu diperiksa.');
        }

        $widths = array_merge([8, 30, 16], array_fill(0, $columns->count(), 13), [13, 13, 12]);
        $this->theme->widths($sheet, $widths);

        return $this->theme->save($workbook);
    }

    private function lastColumn(int $number): string
    {
        $column = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $column = chr(65 + $remainder).$column;
            $number = intdiv($number - 1, 26);
        }

        return $column;
    }
}
