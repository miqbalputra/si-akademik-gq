<?php

namespace App\Services\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SpreadsheetTheme
{
    public const NEON_GREEN = 'FF00DF66';

    private const INK = 'FF0B0D0C';

    private const MUTED = 'FF5E6961';

    private const GRID = 'FFDDE1DC';

    public function workbook(): Spreadsheet
    {
        $workbook = new Spreadsheet;
        $workbook->getProperties()
            ->setCreator("SIAKAD Griya Qur'an")
            ->setTitle('Ekspor SIAKAD Griya Qur\'an')
            ->setSubject('Laporan akademik');
        $workbook->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        return $workbook;
    }

    public function title(Worksheet $sheet, string $title, string $subtitle, int $columns): void
    {
        $lastColumn = $this->column($columns);
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->setCellValue('A1', $title);
        $sheet->setCellValue('A2', $subtitle);
        $sheet->setCellValue('A3', 'Dicetak: '.now()->translatedFormat('d F Y, H:i'));

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => self::INK]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::NEON_GREEN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['argb' => self::MUTED]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A3')->getFont()->setSize(8)->setColor(new Color(self::MUTED));
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->setShowGridlines(false);
        $sheet->getPageMargins()->setTop(0.35)->setBottom(0.35)->setLeft(0.3)->setRight(0.3);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
    }

    /** @param array<int, string> $headers */
    public function tableHeader(Worksheet $sheet, int $row, array $headers): void
    {
        $sheet->fromArray($headers, null, "A{$row}");
        $lastColumn = $this->column(count($headers));
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::INK]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => $this->border(),
        ]);
        $sheet->getRowDimension($row)->setRowHeight(30);
    }

    public function finaliseTable(Worksheet $sheet, int $headerRow, int $lastRow, int $columns): void
    {
        $lastColumn = $this->column($columns);
        if ($lastRow > $headerRow) {
            $sheet->getStyle('A'.($headerRow + 1).":{$lastColumn}{$lastRow}")->applyFromArray([
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                'borders' => $this->border(),
            ]);
            $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}{$lastRow}");
        }
        $sheet->freezePane('A'.($headerRow + 1));
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
    }

    /** @param array<int, float|int> $widths */
    public function widths(Worksheet $sheet, array $widths): void
    {
        foreach ($widths as $index => $width) {
            $sheet->getColumnDimension($this->column($index + 1))->setWidth($width);
        }
    }

    public function save(Spreadsheet $workbook): string
    {
        $path = tempnam(sys_get_temp_dir(), 'siakad-xlsx-');
        if ($path === false) {
            throw new \RuntimeException('Tidak dapat membuat file sementara untuk ekspor Excel.');
        }

        try {
            $writer = new Xlsx($workbook);
            $writer->setPreCalculateFormulas(false);
            $writer->setRestrictMaxColumnWidth(true);
            $writer->save($path);
            $content = file_get_contents($path);
            if ($content === false) {
                throw new \RuntimeException('Tidak dapat membaca hasil ekspor Excel.');
            }

            return $content;
        } finally {
            @unlink($path);
            $workbook->disconnectWorksheets();
        }
    }

    private function column(int $number): string
    {
        $column = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $column = chr(65 + $remainder).$column;
            $number = intdiv($number - 1, 26);
        }

        return $column;
    }

    /** @return array<string, array<string, array<string, string>>> */
    private function border(): array
    {
        return [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::GRID]],
        ];
    }
}
