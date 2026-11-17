<?php

namespace App\Services\Exports;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClassRankingExporter
{
    public function __construct(private readonly SpreadsheetTheme $theme) {}

    public function export(array $report): string
    {
        $book = $this->theme->workbook();
        $period = $report['term']->academicYear?->name.' · '.$report['term']->name;
        if (count($report['classes']) > 1) {
            $summary = $book->getActiveSheet()->setTitle('Ringkasan Kelas');
            $this->heading($summary, 'PERINGKAT SEMUA KELAS', $period);
            $this->theme->tableHeader($summary, 8, ['Kelas', 'Jenis leger', 'Status leger', 'Santri', 'Lengkap', 'Belum lengkap', 'Data per', 'Sheet']);
            $r = 8;
            foreach ($report['classes'] as $i => $class) {
                $r++;
                foreach ([$class['name'], $class['scope_label'], $this->status($class), $class['rows']->count(), $class['complete'], $class['incomplete'], $class['generated_at'] ?? '—', $this->sheetTitle($class['name'], $i)] as $c => $value) {
                    $summary->getCell([$c + 1, $r])->setValueExplicit($value, is_int($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                }
                $summary->getRowDimension($r)->setRowHeight(36);
            }
            $this->finish($summary, $r, [32, 24, 22, 12, 12, 17, 22, 32]);
        }
        foreach ($report['classes'] as $i => $class) {
            $sheet = count($report['classes']) > 1 ? $book->createSheet() : $book->getActiveSheet();
            $sheet->setTitle($this->sheetTitle($class['name'], $i));
            $this->heading($sheet, 'PERINGKAT KELAS · '.$class['name'], $period);
            $this->text($sheet, 'A4', $this->status($class).' · '.$class['scope_label']);
            $sheet->mergeCells('A4:H4');
            $this->text($sheet, 'A5', 'Data leger: '.($class['generated_at'] ?? 'belum tersedia').' · '.$class['rows']->count().' santri · '.$class['incomplete'].' belum lengkap');
            $sheet->mergeCells('A5:H5');
            $this->theme->tableHeader($sheet, 8, ['Peringkat', 'Nama santri', 'NIS', 'Total Diniyyah', 'Total Tahfidz', 'Total akhir', 'Rata-rata', 'Kelengkapan']);
            $r = 8;
            foreach ($class['rows'] as $row) {
                $r++;
                foreach ([$row['rank'], $row['name'].($row['archived'] ? ' (Arsip)' : ''), $row['nis'] ?? '', $row['diniyyah_total'], $row['tahfidz_total'], $row['total'], $row['average'], $row['complete'] ? 'Lengkap' : 'Belum lengkap'] as $c => $value) {
                    $numeric = in_array($c, [0, 3, 4, 5, 6], true) && $value !== null;
                    $sheet->getCell([$c + 1, $r])->setValueExplicit($value === null ? '—' : ($numeric ? (float) $value : (string) $value), $numeric ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                }
                $sheet->getStyle('C'.$r)->getNumberFormat()->setFormatCode('@');
                $sheet->getStyle("D{$r}:G{$r}")->getNumberFormat()->setFormatCode('0.00');
                $sheet->getStyle("A{$r}:H{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($row['complete'] ? ($r % 2 ? 'FFF0F7F2' : 'FFFFFFFF') : 'FFFFF8E5');
                $sheet->getStyle('F'.$r)->getFont()->setBold(true);
                $sheet->getRowDimension($r)->setRowHeight(max(30, 15 * ceil(mb_strlen($row['name']) / 30) + 10));
            }
            if ($r === 8) {
                $r = 9;
                $sheet->mergeCells('A9:H9');
                $this->text($sheet, 'A9', $class['snapshot_id'] ? 'Belum ada santri pada leger ini sesuai filter.' : 'Leger belum dibuat oleh wali kelas.');
            }
            $this->finish($sheet, $r, [12, 34, 18, 18, 18, 18, 16, 23]);
            $noteRow = $r + 2;
            $sheet->mergeCells("A{$noteRow}:H{$noteRow}");
            $this->text($sheet, 'A'.$noteRow, 'Peringkat dan total akhir disalin dari leger, tidak dihitung ulang. — = belum tersedia. Juz dan presensi tidak dijumlahkan. '.($report['include_archived'] ? 'Termasuk santri arsip.' : 'Santri aktif saja; peringkat tersimpan dapat memiliki jeda.'));
            $sheet->getStyle("A{$noteRow}:H{$noteRow}")->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($noteRow)->setRowHeight(32);
            $sheet->getPageSetup()->setPrintArea('A1:H'.$noteRow);
        }
        $book->setActiveSheetIndex(0);

        return $this->theme->save($book);
    }

    private function heading(Worksheet $sheet, string $title, string $subtitle): void
    {
        $this->theme->title($sheet, $title, $subtitle, 8);
        $this->text($sheet, 'A1', $title);
        $this->text($sheet, 'A2', $subtitle);
        $sheet->getStyle('A1:H1')->getFill()->getStartColor()->setARGB('FF14532D');
        $sheet->getStyle('A1:H1')->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:H2')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(42);
    }

    private function finish(Worksheet $sheet, int $lastRow, array $widths): void
    {
        $this->theme->finaliseTable($sheet, 8, $lastRow, 8);
        $this->theme->widths($sheet, $widths);
        $sheet->getRowDimension(8)->setRowHeight(36);
        $sheet->freezePane('D9');
        $sheet->getStyle('A8:H'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_FOLIO)->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd(1, 8)->setPrintArea('A1:H'.$lastRow)->setHorizontalCentered(true);
        $sheet->getHeaderFooter()->setOddFooter('&LRuang GQ&RHalaman &P / &N');
    }

    private function sheetTitle(string $name, int $index): string
    {
        return mb_substr(sprintf('%02d ', $index + 1).trim(str_replace(['\\', '/', ':', '*', '?', '[', ']'], ' ', $name), " '"), 0, 31);
    }

    private function text(Worksheet $sheet, string $address, string $value): void
    {
        $sheet->setCellValueExplicit($address, $value, DataType::TYPE_STRING);
    }

    private function status(array $class): string
    {
        return ! $class['snapshot_id'] ? 'Belum ada leger' : (in_array($class['status'], ['locked', 'published'], true) ? 'FINAL' : 'DRAF — belum final');
    }
}
