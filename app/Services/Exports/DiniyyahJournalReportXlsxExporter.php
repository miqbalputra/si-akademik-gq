<?php

namespace App\Services\Exports;

use PhpOffice\PhpSpreadsheet\Style\Alignment;

class DiniyyahJournalReportXlsxExporter
{
    public function __construct(private readonly SpreadsheetTheme $theme) {}

    public function export(array $report, string $scope = 'management'): string
    {
        $workbook = $this->theme->workbook();
        $summary = $workbook->getActiveSheet();
        $summary->setTitle('Ringkasan');
        $this->summary($summary, $report, $scope);

        $detail = $workbook->createSheet();
        $detail->setTitle('Detail Jurnal');
        $this->detail($detail, $report);

        return $this->theme->save($workbook);
    }

    private function summary($sheet, array $report, string $scope): void
    {
        $this->theme->title(
            $sheet,
            'LAPORAN JURNAL DINIYYAH',
            $scope === 'guru' ? 'Laporan jurnal yang tercatat atas nama guru ini.' : 'Laporan full data untuk kebutuhan monitoring sekolah.',
            3,
        );
        $stats = $report['stats'] ?? [];
        $sheet->setCellValue('A5', 'RINGKASAN STATISTIK');
        $sheet->mergeCells('A5:C5');
        $sheet->getStyle('A5:C5')->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => SpreadsheetTheme::NEON_GREEN]]]);

        $labels = [
            'Total jurnal' => $stats['total_jurnal'] ?? 0,
            'Total guru' => $stats['total_guru'] ?? 0,
            'Total kelas' => $stats['total_kelas'] ?? 0,
            'Total mapel' => $stats['total_mapel'] ?? 0,
            'Total JP' => $stats['total_jp'] ?? 0,
            'Jurnal reguler' => $stats['jurnal_reguler'] ?? 0,
            'Jurnal pengganti' => $stats['jurnal_pengganti'] ?? 0,
            'Hari tercatat' => $stats['hari_tercatat'] ?? 0,
        ];
        $row = 6;
        foreach ($labels as $label => $value) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $row++;
        }
        $sheet->getStyle('A6:B'.($row - 1))->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['argb' => 'FFDDE1DC']]],
        ]);

        $row += 1;
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("A{$row}", 'REKAP PER GURU');
        $sheet->getStyle("A{$row}:C{$row}")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => SpreadsheetTheme::NEON_GREEN]]]);
        $row++;
        $teacherHeaderRow = $row;
        $this->theme->tableHeader($sheet, $teacherHeaderRow, ['Nama Guru', 'Jumlah Jurnal', 'Total JP']);
        foreach (($stats['by_teacher'] ?? collect()) as $teacher) {
            $row++;
            $sheet->fromArray([[$teacher['name'], $teacher['journals'], $teacher['jp']]], null, "A{$row}");
        }
        if ($row === 16) {
            $row++;
            $sheet->setCellValue("A{$row}", 'Tidak ada data jurnal untuk filter ini.');
        }
        $this->theme->finaliseTable($sheet, $teacherHeaderRow, $row, 3);

        $row += 2;
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("A{$row}", 'FILTER YANG DIGUNAKAN');
        $sheet->getStyle("A{$row}:C{$row}")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFF1F5F1']]]);
        foreach (($report['filter_labels'] ?? []) as $label => $value) {
            $row++;
            $sheet->setCellValue("A{$row}", $label);
            $sheet->mergeCells("B{$row}:C{$row}");
            $sheet->setCellValue("B{$row}", $value ?: 'Semua');
        }
        $this->theme->widths($sheet, [30, 18, 18]);
    }

    private function detail($sheet, array $report): void
    {
        $headers = ['No', 'Tanggal', 'Jam', 'Kelas', 'Mapel', 'Guru Asli', 'Pengganti', 'Guru Mengajar (untuk gaji)', 'Jenis', 'Materi', 'JP', 'Hadir', 'Sakit', 'Izin', 'Alpa', 'Bolos'];
        $this->theme->title($sheet, 'DETAIL JURNAL DINIYYAH', 'Data dapat difilter melalui header tabel.', count($headers));
        $this->theme->tableHeader($sheet, 5, $headers);
        $rowNumber = 5;
        foreach (collect($report['rows'] ?? []) as $index => $row) {
            $rowNumber++;
            $sheet->fromArray([[
                $index + 1, $row['date'] ?? '-', $row['session_label'] ?? '-', $row['kelas'] ?? '-', $row['mapel'] ?? '-',
                $row['guru_asli'] ?? '-', $row['pengganti'] ?? '-', $row['guru_mengajar'] ?? '-', $row['type_label'] ?? '-',
                $row['material'] ?? '-', $row['jp'] ?? 0, $row['hadir'] ?? 0, $row['sakit'] ?? 0, $row['izin'] ?? 0,
                $row['alpa'] ?? 0, $row['bolos'] ?? 0,
            ]], null, "A{$rowNumber}");
        }
        if ($rowNumber === 5) {
            $rowNumber++;
            $sheet->setCellValue('A6', 'Tidak ada data jurnal untuk filter ini.');
        }
        $sheet->getStyle("A6:A{$rowNumber}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->theme->widths($sheet, [7, 14, 16, 22, 22, 22, 22, 22, 14, 42, 8, 9, 9, 9, 9, 9]);
        $this->theme->finaliseTable($sheet, 5, $rowNumber, count($headers));
    }
}
