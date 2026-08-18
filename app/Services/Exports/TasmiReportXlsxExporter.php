<?php

namespace App\Services\Exports;

use PhpOffice\PhpSpreadsheet\Style\Alignment;

class TasmiReportXlsxExporter
{
    public function __construct(private readonly SpreadsheetTheme $theme) {}

    public function export(array $report, string $scope): string
    {
        $workbook = $this->theme->workbook();
        $summary = $workbook->getActiveSheet();
        $summary->setTitle('Ringkasan');
        $this->summary($summary, $report, $scope);

        $detail = $workbook->createSheet();
        $detail->setTitle('Detail Tasmi');
        $this->detail($detail, $report);

        return $this->theme->save($workbook);
    }

    private function summary($sheet, array $report, string $scope): void
    {
        $this->theme->title(
            $sheet,
            'LAPORAN HASIL TASMI\'',
            $scope === 'management'
                ? 'Monitoring seluruh PJ Tasmi\', kelas, dan semester sesuai filter.'
                : 'Laporan hasil Tasmi\' sesuai hak akses akun Anda.',
            4,
        );

        $summary = $report['summary'] ?? [];
        $sheet->setCellValue('A5', 'RINGKASAN STATISTIK');
        $sheet->mergeCells('A5:D5');
        $sheet->getStyle('A5:D5')->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => SpreadsheetTheme::NEON_GREEN]]]);
        $labels = [
            'Total setoran' => $summary['total_records'] ?? 0,
            'Santri tercatat' => $summary['total_students'] ?? 0,
            'Kelas tercatat' => $summary['total_classes'] ?? 0,
            'Tasmi\' 1 juz' => $summary['one_juz'] ?? 0,
            'Tasmi\' 5 juz' => $summary['five_juz'] ?? 0,
        ];
        $row = 6;
        foreach ($labels as $label => $value) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $row++;
        }
        $sheet->getStyle('A6:B'.($row - 1))->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['argb' => 'FFDDE1DC']]]]);

        $row += 1;
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->setCellValue("A{$row}", 'DISTRIBUSI PREDIKAT');
        $sheet->getStyle("A{$row}:D{$row}")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFF1F5F1']]]);
        $row++;
        $this->theme->tableHeader($sheet, $row, ['Maqbul', 'Jayyid', 'Jayyid Jiddan', 'Mumtaz']);
        $row++;
        $predicates = $summary['predicates'] ?? [];
        $sheet->fromArray([[
            $predicates['maqbul'] ?? 0,
            $predicates['jayyid'] ?? 0,
            $predicates['jayyid_jiddan'] ?? 0,
            $predicates['mumtaz'] ?? 0,
        ]], null, "A{$row}");
        $this->theme->finaliseTable($sheet, $row - 1, $row, 4);

        $row += 2;
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->setCellValue("A{$row}", 'FILTER YANG DIGUNAKAN');
        $sheet->getStyle("A{$row}:D{$row}")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFF1F5F1']]]);
        foreach (($report['filter_labels'] ?? []) as $label => $value) {
            $row++;
            $sheet->setCellValue("A{$row}", $label);
            $sheet->mergeCells("B{$row}:D{$row}");
            $sheet->setCellValue("B{$row}", $value ?: 'Semua');
        }
        $this->theme->widths($sheet, [28, 22, 22, 22]);
    }

    private function detail($sheet, array $report): void
    {
        $headers = ['No', 'Tanggal', 'Hijriyah', 'Semester', 'Santri', 'NIS', 'Kelas', 'Jenis', 'Juz', 'Predikat', 'Catatan', 'PJ Tasmi\'', 'Diinput oleh', 'Waktu input', 'Terakhir diperbarui'];
        $this->theme->title($sheet, 'DETAIL HASIL TASMI\'', 'Seluruh baris mengikuti scope dan filter laporan.', count($headers));
        $this->theme->tableHeader($sheet, 5, $headers);
        $rowNumber = 5;
        foreach ($report['rows'] ?? [] as $index => $row) {
            $rowNumber++;
            $sheet->fromArray([[
                $index + 1,
                $row['date'] ?? '-', $row['hijri_date'] ?? '-', $row['term'] ?? '-', $row['student'] ?? '-', $row['nis'] ?? '-',
                $row['classroom'] ?? '-', $row['exam_type'] ?? '-', $row['juz'] ?? '-', $row['predicate'] ?? '-', $row['notes'] ?? '-',
                $row['examiner'] ?? '-', $row['input_by'] ?? '-', $row['input_at'] ?? '-', $row['updated_at'] ?? '-',
            ]], null, "A{$rowNumber}");
        }
        if ($rowNumber === 5) {
            $rowNumber++;
            $sheet->setCellValue('A6', 'Tidak ada hasil Tasmi\' untuk filter ini.');
        }
        $sheet->getStyle("A6:A{$rowNumber}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->theme->widths($sheet, [7, 14, 18, 18, 24, 14, 21, 17, 13, 17, 34, 22, 22, 20, 22]);
        $this->theme->finaliseTable($sheet, 5, $rowNumber, count($headers));
    }
}
