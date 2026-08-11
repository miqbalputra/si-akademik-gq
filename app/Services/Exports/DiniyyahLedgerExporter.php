<?php

namespace App\Services\Exports;

use App\Models\DiniyyahLedgerSnapshot;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class DiniyyahLedgerExporter
{
    public function __construct(private readonly SpreadsheetTheme $theme) {}

    public function export(?int $snapshotId): string
    {
        $snapshot = DiniyyahLedgerSnapshot::with(['rows.cells', 'classroomTerm', 'academicTerm.academicYear'])->findOrFail($snapshotId);
        $columns = collect($snapshot->snapshot_data['columns'] ?? []);
        $summary = $snapshot->snapshot_data['summary'] ?? [];
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
        $this->theme->title($sheet, 'LEGER NILAI DINIYYAH', $subtitle ?: 'Rekap nilai diniyyah.', count($headers));
        $sheet->setCellValue('A5', 'Status');
        $sheet->setCellValue('B5', strtoupper((string) $snapshot->status));
        $sheet->setCellValue('A6', 'Dibuat');
        $sheet->setCellValue('B6', $snapshot->generated_at?->translatedFormat('d F Y, H:i') ?? '-');
        $headerRow = 8;
        $this->theme->tableHeader($sheet, $headerRow, $headers);

        $lastRow = $headerRow;
        foreach ($snapshot->rows->sortBy('row_number') as $row) {
            $lastRow++;
            $cells = $row->cells->keyBy('column_key');
            $values = [$row->row_number, $row->student_name, $row->student_nis ?? ''];
            foreach ($columns as $column) {
                $value = $cells->get($column['key'] ?? '')?->value_numeric;
                $values[] = $value === null ? '-' : (float) $value;
            }
            $values[] = $row->total_diniyyah_score === null ? '-' : (float) $row->total_diniyyah_score;
            $values[] = $row->average_diniyyah_score === null ? '-' : (float) $row->average_diniyyah_score;
            $values[] = $row->rank_in_class ?? '-';
            $sheet->fromArray([$values], null, "A{$lastRow}");
        }
        if ($lastRow === $headerRow) {
            $lastRow++;
            $sheet->setCellValue("A{$lastRow}", 'Belum ada data leger untuk snapshot ini.');
        }
        $this->theme->finaliseTable($sheet, $headerRow, $lastRow, count($headers));
        $sheet->getStyle('A'.($headerRow + 1).':A'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C'.($headerRow + 1).':'.$this->lastColumn(count($headers)).$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $summaryRow = $lastRow + 2;
        $sheet->mergeCells("A{$summaryRow}:C{$summaryRow}");
        $sheet->setCellValue("A{$summaryRow}", 'RINGKASAN KELENGKAPAN');
        $sheet->getStyle("A{$summaryRow}:C{$summaryRow}")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => SpreadsheetTheme::NEON_GREEN]]]);
        $summaryRow++;
        $sheet->setCellValue("A{$summaryRow}", 'Total santri');
        $sheet->setCellValue("B{$summaryRow}", $summary['total_students'] ?? 0);
        $sheet->setCellValue("A".($summaryRow + 1), 'Data lengkap');
        $sheet->setCellValue("B".($summaryRow + 1), $summary['complete_rows'] ?? 0);
        $sheet->setCellValue("A".($summaryRow + 2), 'Belum lengkap');
        $sheet->setCellValue("B".($summaryRow + 2), $summary['incomplete_rows'] ?? 0);
        if (! empty($summary['blocking_issues'])) {
            $sheet->setCellValue("D{$summaryRow}", 'Peringatan');
            $sheet->setCellValue("E{$summaryRow}", $summary['blocking_issues'].' masalah kelengkapan perlu diperiksa.');
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
