<?php

namespace App\Services\Exports;

use PhpOffice\PhpSpreadsheet\Style\Alignment;

class GuruAttendanceReportXlsxExporter
{
    public function __construct(private readonly SpreadsheetTheme $theme) {}

    /** @param array<string, mixed> $report */
    public function export(array $report): string
    {
        $workbook = $this->theme->workbook();
        $sheet = $workbook->getActiveSheet();
        $sheet->setTitle('Rekap Presensi');

        $teacher = $report['teacher'] ?? [];
        $period = $report['period'] ?? [];
        $summary = $report['summary'] ?? [];
        $this->theme->title(
            $sheet,
            'REKAP PRESENSI SAYA',
            'Sumber data: GeoPresensi · Periode '.($period['label'] ?? '-'),
            5,
        );

        $sheet->fromArray([
            ['Nama Guru', $teacher['nama'] ?? '-'],
            ['NIY', $teacher['id_guru'] ?? '-'],
            ['Periode', $period['label'] ?? '-'],
            ['Tersinkron', $report['synced_at_label'] ?? '-'],
        ], null, 'A5');
        $sheet->getStyle('A5:A8')->getFont()->setBold(true);

        $headerRow = 10;
        $headers = ['Tanggal', 'Jam Masuk', 'Jam Pulang', 'Status', 'Keterangan'];
        $this->theme->tableHeader($sheet, $headerRow, $headers);
        $lastRow = $headerRow;
        foreach (($report['rows'] ?? []) as $row) {
            $lastRow++;
            $sheet->fromArray([[
                $row['tanggal'] ?? '-',
                $row['jam_masuk'] ?: '-',
                $row['jam_pulang'] ?: '-',
                $row['status_label'] ?? '-',
                $row['keterangan'] ?: '-',
            ]], null, "A{$lastRow}");
        }
        if ($lastRow === $headerRow) {
            $lastRow++;
            $sheet->setCellValue("A{$lastRow}", 'Tidak ada data presensi pada periode ini.');
            $sheet->mergeCells("A{$lastRow}:E{$lastRow}");
        }
        $sheet->getStyle('B'.($headerRow + 1).":D{$lastRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->theme->finaliseTable($sheet, $headerRow, $lastRow, count($headers));

        $summaryRow = $lastRow + 3;
        $sheet->mergeCells("A{$summaryRow}:E{$summaryRow}");
        $sheet->setCellValue("A{$summaryRow}", 'RINGKASAN STATISTIK');
        $sheet->getStyle("A{$summaryRow}:E{$summaryRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => SpreadsheetTheme::NEON_GREEN]],
        ]);
        $stats = [
            'Total Hari Kerja' => $summary['total_hari'] ?? 0,
            'Hadir' => $summary['hadir'] ?? 0,
            'Izin' => $summary['izin'] ?? 0,
            'Sakit' => $summary['sakit'] ?? 0,
            'Alfa' => $summary['alfa'] ?? 0,
            'Persentase Hadir' => ($summary['persentase'] ?? 0).'%',
        ];
        $row = $summaryRow;
        foreach ($stats as $label => $value) {
            $row++;
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
        }
        $sheet->getStyle('A'.($summaryRow + 1).":B{$row}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['argb' => 'FFDDE1DC']]],
        ]);
        $this->theme->widths($sheet, [20, 14, 14, 25, 48]);

        return $this->theme->save($workbook);
    }
}
