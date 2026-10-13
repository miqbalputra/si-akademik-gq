<?php

namespace App\Services\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClassAttendanceReportXlsxExporter
{
    public function __construct(private readonly SpreadsheetTheme $theme) {}

    /** @param array<string, mixed> $report */
    public function export(array $report): string
    {
        $book = $this->theme->workbook();
        foreach ($report['classes'] as $index => $class) {
            $summary = $index === 0 ? $book->getActiveSheet() : $book->createSheet();
            $summary->setTitle($this->sheetName('Rekap', $class));
            $this->heading($summary, $class, $report, 13, 'REKAP PRESENSI KELAS');
            $headers = ['No.', 'NIS', 'Nama Santri', 'Status', 'Tanggal Keluar', 'Hari Dihitung', 'Hadir (H)', 'Sakit (S)', 'Izin (I)', 'Alpa (A)', 'Libur Khusus (L)', 'Hadir Bawaan (H*)', 'Kehadiran'];
            $this->theme->tableHeader($summary, 12, $headers);
            $row = 12;
            foreach ($class['students'] as $student) {
                $row++;
                $summary->setCellValue("A{$row}", $student['number']);
                foreach (['B' => $student['nis'], 'C' => $student['name'], 'D' => $student['status_label']] as $column => $text) {
                    $summary->setCellValueExplicit($column.$row, $text, DataType::TYPE_STRING);
                }
                if ($student['departure_date']) {
                    $summary->setCellValue("E{$row}", Date::PHPToExcel(new \DateTimeImmutable($student['departure_date'])));
                    $summary->getStyle("E{$row}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                } else {
                    $summary->setCellValueExplicit("E{$row}", '—', DataType::TYPE_STRING);
                }
                $summary->fromArray([[$student['eligible_days'], $student['counts']['H'], $student['counts']['S'], $student['counts']['I'], $student['counts']['A'], $student['counts']['L'], $student['assumed_present']]], null, "F{$row}", true);
                if ($student['percentage'] !== null) {
                    $summary->setCellValue("M{$row}", $student['percentage']);
                    $summary->getStyle("M{$row}")->getNumberFormat()->setFormatCode('0.0%');
                } else {
                    $summary->setCellValueExplicit("M{$row}", '—', DataType::TYPE_STRING);
                }
                $summary->getRowDimension($row)->setRowHeight(max(28, 15 * (int) ceil(mb_strwidth($student['name']) / 33)));
            }
            if ($row === 12) {
                $row++;
                $summary->mergeCells("A{$row}:M{$row}");
                $summary->setCellValue("A{$row}", 'Tidak ada santri pada kelas/periode ini.');
            }
            $this->theme->finaliseTable($summary, 12, $row, 13);
            $summary->freezePane('D13');
            $summary->getStyle("E13:M{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $this->theme->widths($summary, [6, 16, 36, 22, 17, 12, 11, 11, 11, 11, 13, 14, 14]);
            $this->notes($summary, $row + 2, 13, $report);
            $summary->getPageSetup()->setPrintArea('A1:M'.($row + 6));

            $daily = $book->createSheet();
            $daily->setTitle($this->sheetName('Harian', $class));
            $this->heading($daily, $class, $report, 35, 'RINCIAN PRESENSI HARIAN');
            $this->theme->tableHeader($daily, 12, array_merge(['No.', 'Bulan', 'NIS', 'Nama Santri'], range(1, 31)));
            $row = 12;
            foreach ($class['months'] as $month) {
                foreach ($class['students'] as $student) {
                    $row++;
                    $daily->setCellValue("A{$row}", $student['number']);
                    foreach (['B' => $month['label'], 'C' => $student['nis'], 'D' => $student['name']] as $column => $text) {
                        $daily->setCellValueExplicit($column.$row, $text, DataType::TYPE_STRING);
                    }
                    for ($dayNumber = 1; $dayNumber <= 31; $dayNumber++) {
                        $column = Coordinate::stringFromColumnIndex($dayNumber + 4);
                        $date = $month['key'].'-'.str_pad((string) $dayNumber, 2, '0', STR_PAD_LEFT);
                        $code = $student['daily'][$date] ?? '—';
                        $daily->setCellValueExplicit($column.$row, $code, DataType::TYPE_STRING);
                        $color = match ($code) {
                            'H' => 'FFE8F5EC', 'H*' => 'FFEFF6FF', 'S' => 'FFFEF3C7',
                            'I' => 'FFE0F2FE', 'A' => 'FFFEE2E2', default => 'FFF1F5F9',
                        };
                        $daily->getStyle($column.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($color);
                    }
                    $daily->getRowDimension($row)->setRowHeight(max(26, 15 * (int) ceil(mb_strwidth($student['name']) / 33)));
                }
            }
            if ($row === 12) {
                $row++;
                $daily->mergeCells("A{$row}:AI{$row}");
                $daily->setCellValue("A{$row}", 'Tidak ada santri pada kelas/periode ini.');
            }
            $this->theme->finaliseTable($daily, 12, $row, 35);
            $daily->freezePane('E13');
            $daily->getStyle("E13:AI{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $this->theme->widths($daily, array_merge([6, 22, 16, 36], array_fill(0, 31, 5)));
            $daily->getPageSetup()->setColumnsToRepeatAtLeftByStartAndEnd('A', 'D');
            $this->notes($daily, $row + 2, 35, $report);
            $daily->getPageSetup()->setPrintArea('A1:AI'.($row + 6));
        }
        $book->setActiveSheetIndex(0);

        return $this->theme->save($book);
    }

    private function sheetName(string $prefix, array $class): string
    {
        $name = preg_replace('/[\\\\\/\[\]:*?]/u', ' ', $class['name']);

        return mb_substr($prefix.' '.$class['id'].' '.$name, 0, 31);
    }

    private function heading(Worksheet $sheet, array $class, array $report, int $columns, string $title): void
    {
        $last = Coordinate::stringFromColumnIndex($columns);
        $this->theme->title($sheet, $title, $class['school'].' · '.$class['name'], $columns);
        $sheet->setCellValue('A3', 'Diterbitkan: '.$report['generated_at']);
        $lines = [
            5 => 'Kelas: '.$class['name'].' | '.$class['academic_year'].' | '.$class['semester'],
            6 => 'Wali kelas: '.$class['homeroom'],
            7 => 'Periode: '.$report['period']['label'].' ('.$report['period']['start'].' s.d. '.$report['period']['end'].')',
            8 => 'Hari efektif dalam pilihan: '.$class['effective_days'].' | Sudah berlangsung: '.$class['elapsed_days'].' | Batas perhitungan: '.$report['period']['as_of'].' WIB',
            9 => implode("\n", array_map(fn ($month) => $month['label'].': '.$month['full_effective_days'].' hari efektif sebulan; '.$month['selected_effective_days'].' dalam pilihan; '.$month['elapsed_effective_days'].' sudah berlangsung.', $class['months'])),
        ];
        foreach ($lines as $row => $text) {
            $sheet->mergeCells("A{$row}:{$last}{$row}");
            $sheet->setCellValueExplicit("A{$row}", $text, DataType::TYPE_STRING);
            $sheet->getStyle("A{$row}")->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($row)->setRowHeight($row === 9 ? max(20, 16 * count($class['months'])) : 22);
        }
    }

    private function notes(Worksheet $sheet, int $row, int $columns, array $report): void
    {
        $last = Coordinate::stringFromColumnIndex($columns);
        foreach ([$report['legend'], $report['formula'], 'Jumlah L pada rekap adalah libur khusus yang tersimpan pada hari efektif; akhir pekan dan Libur Sekolah sudah dikeluarkan melalui kalender.', $report['assumption'], 'Hari efektif: Senin–Jumat dalam semester, dikurangi Libur Sekolah. Agenda Tanpa KBM tetap dihitung.'] as $text) {
            $sheet->mergeCells("A{$row}:{$last}{$row}");
            $sheet->setCellValueExplicit("A{$row}", $text, DataType::TYPE_STRING);
            $sheet->getStyle("A{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getRowDimension($row)->setRowHeight(34);
            $row++;
        }
    }
}
