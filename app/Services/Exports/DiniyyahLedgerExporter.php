<?php

namespace App\Services\Exports;

use App\Models\DiniyyahLedgerSnapshot;
use App\Services\DiniyyahLedgerPresentation;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DiniyyahLedgerExporter
{
    public function __construct(private readonly SpreadsheetTheme $theme) {}

    public function export(?int $snapshotId, bool $includeArchivedStudents = false): string
    {
        $snapshot = DiniyyahLedgerSnapshot::with(['rows.cells', 'rows.classEnrollment.student', 'classroomTerm', 'academicTerm.academicYear'])->findOrFail($snapshotId);
        if (! $includeArchivedStudents) {
            $snapshot->setRelation('rows', $snapshot->rows->filter(fn ($row) => $row->classEnrollment?->student?->status === 'active')->values());
        }
        $ledger = app(DiniyyahLedgerPresentation::class)->build($snapshot);
        $columns = $ledger['columns'];
        $columnCount = $columns->count() + 3;
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $workbook = $this->theme->workbook();
        $sheet = $workbook->getActiveSheet()->setTitle('Leger');
        $subtitle = $snapshot->classroomTerm?->name.' · '.$snapshot->academicTerm?->academicYear?->name.' '.$snapshot->academicTerm?->name;
        $this->theme->title($sheet, $ledger['combined'] ? 'LEGER NILAI DINIYYAH & TAHFIDZ' : 'LEGER NILAI DINIYYAH', $subtitle, $columnCount);
        $this->text($sheet, 'A2', $subtitle);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->getStartColor()->setARGB('FF14532D');
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->mergeCells("A4:{$lastColumn}4");
        $this->text($sheet, 'A4', '— = nilai belum ada. Jingga = di bawah KKM. Juz dan presensi tidak masuk total nilai.');
        $sheet->getStyle("A4:{$lastColumn}4")->getFont()->setSize(9)->getColor()->setARGB('FF64748B');
        $sheet->getRowDimension(4)->setRowHeight(20);
        $sheet->setCellValue('A5', 'Status');
        $sheet->mergeCells('B5:D5');
        $this->text($sheet, 'B5', in_array($snapshot->status, ['locked', 'published'], true) ? 'FINAL' : 'DRAF — belum menjadi leger final');
        $sheet->setCellValue('A6', 'Data per');
        $sheet->mergeCells('B6:D6');
        $this->text($sheet, 'B6', $snapshot->generated_at?->format('d-m-Y H:i') ?? '—');

        // A group row stays outside the filter. KKM shares the column label so filtering never hides a KKM row.
        $headers = array_merge(['No', 'Nama santri', 'NIS'], $columns->map(function ($column) {
            $label = $column['display_label'];
            if ($column['tested_material'] && $column['tested_material'] !== '-') {
                $label .= "\n".$column['tested_material'];
            }
            if ($column['kkm'] !== null) {
                $label .= "\nKKM ".(float) $column['kkm'];
            } elseif (($column['source_type'] ?? '') === 'diniyyah_assessment_set') {
                $label .= "\nMenunggu ACC";
            }

            return $label;
        })->all());
        $this->theme->tableHeader($sheet, 9, $headers);
        foreach ($headers as $index => $label) {
            $this->text($sheet, Coordinate::stringFromColumnIndex($index + 1).'9', $label);
        }
        $sheet->mergeCells('A8:C8');
        $this->text($sheet, 'A8', 'Identitas santri');
        $this->groupStyle($sheet, 'A8:C9', 'F1F5F9', '334155');
        $start = 4;
        foreach ($ledger['groups'] as $group) {
            $end = $start + count($group['columns']) - 1;
            $first = Coordinate::stringFromColumnIndex($start);
            $last = Coordinate::stringFromColumnIndex($end);
            if ($end > $start) {
                $sheet->mergeCells("{$first}8:{$last}8");
            }
            $this->text($sheet, "{$first}8", $group['label']);
            $this->groupStyle($sheet, "{$first}8:{$last}9", $group['background'], $group['color']);
            $start = $end + 1;
        }
        $sheet->getRowDimension(8)->setRowHeight(26);
        $headerLines = max(array_map(fn ($label) => array_sum(array_map(fn ($line) => max(1, ceil(mb_strlen($line) / 12)), explode("\n", $label))), $headers));
        $sheet->getRowDimension(9)->setRowHeight(max(72, $headerLines * 13 + 10));
        $lastRow = 9;
        foreach ($ledger['rows'] as $row) {
            $lastRow++;
            $this->text($sheet, "B{$lastRow}", $row['name']);
            $this->text($sheet, "C{$lastRow}", $row['nis'] ?? '');
            $sheet->setCellValue("A{$lastRow}", (int) $row['number']);
            $sheet->getStyle("A{$lastRow}:{$lastColumn}{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($lastRow % 2 ? 'FFF5F9F6' : 'FFFFFFFF');
            foreach ($columns as $index => $column) {
                $address = Coordinate::stringFromColumnIndex($index + 4).$lastRow;
                $value = $row['values'][$column['key']];
                $raw = $value['missing'] ? '—' : ($column['numeric'] ? (float) $value['raw'] : (string) $value['raw']);
                $sheet->setCellValueExplicit($address, $raw, is_float($raw) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                $sheet->getStyle($address)->getNumberFormat()->setFormatCode(($column['kind'] ?? '') === 'rank' || $column['group'] === 'attendance' ? '0' : '0.00');
                if ($column['group'] === 'summary') {
                    $this->groupStyle($sheet, $address, 'EDF6F0', '14532D');
                }
                if ($value['missing'] || $value['below']) {
                    $this->groupStyle($sheet, $address, $value['missing'] ? 'FFFBEB' : 'FFF3E5', $value['missing'] ? 'A16207' : '9A3412');
                }
            }
            $longest = max(array_merge([1, ceil(mb_strlen($row['name']) / 28)], $columns->filter(fn ($c) => ! $c['numeric'])->map(fn ($c) => ceil(mb_strlen($row['values'][$c['key']]['display']) / 18))->all()));
            $sheet->getRowDimension($lastRow)->setRowHeight(max(28, 14 * $longest + 8));
        }
        if ($lastRow === 9) {
            $lastRow = 10;
            $sheet->mergeCells("A10:{$lastColumn}10");
            $this->text($sheet, 'A10', 'Belum ada data santri pada leger ini.');
        }
        $this->theme->finaliseTable($sheet, 9, $lastRow, $columnCount);
        $sheet->freezePane('D10');
        $sheet->getStyle("A10:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B10:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("C10:C{$lastRow}")->getNumberFormat()->setFormatCode('@');
        $widths = array_merge([7, 32, 16], $columns->map(fn ($c) => ! $c['numeric'] ? 24 : (($c['kind'] ?? '') === 'rank' ? 11 : 13))->all());
        $this->theme->widths($sheet, $widths);

        $summaryRow = $lastRow + 2;
        $sheet->mergeCells("A{$summaryRow}:D{$summaryRow}");
        $this->text($sheet, "A{$summaryRow}", 'RINGKASAN KELAS');
        $this->groupStyle($sheet, "A{$summaryRow}:D{$summaryRow}", 'E8F4EC', '166534');
        foreach ([
            'Jumlah santri' => $ledger['rows']->count(),
            'Nilai lengkap' => $ledger['rows']->where('complete', true)->count(),
            'Belum lengkap' => $ledger['rows']->where('complete', false)->count(),
            'Ada nilai di bawah KKM' => $ledger['rows']->where('below', true)->count(),
        ] as $label => $count) {
            $summaryRow++;
            $sheet->mergeCells("A{$summaryRow}:C{$summaryRow}");
            $this->text($sheet, "A{$summaryRow}", $label);
            $sheet->setCellValue("D{$summaryRow}", $count);
            $sheet->getRowDimension($summaryRow)->setRowHeight(20);
        }
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(8, 9)->setColumnsToRepeatAtLeftByStartAndEnd('A', 'C')
            ->setPaperSize($columnCount > 14 ? PageSetup::PAPERSIZE_A3 : PageSetup::PAPERSIZE_A4)
            ->setPrintArea("A1:{$lastColumn}{$summaryRow}")->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getHeaderFooter()->setOddFooter('&L'.str_replace('&', '&&', $subtitle).'&RHalaman &P / &N');
        $sheet->getSheetView()->setZoomScale(85);

        return $this->theme->save($workbook);
    }

    private function text(Worksheet $sheet, string $cell, string $text): void
    {
        $sheet->setCellValueExplicit($cell, $text, DataType::TYPE_STRING);
    }

    private function groupStyle(Worksheet $sheet, string $range, string $background, string $color): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.$background]],
            'font' => ['bold' => true, 'color' => ['argb' => 'FF'.$color]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
    }
}
