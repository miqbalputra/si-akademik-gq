<?php

namespace App\Services\Exports;

use ZipArchive;

/**
 * Writer XLSX ringan tanpa dependensi tambahan.
 *
 * Laporan dibuat sebagai workbook Open XML dengan sheet Ringkasan dan Detail
 * Jurnal, sehingga hasil download benar-benar .xlsx dan bisa difilter di Excel.
 */
class DiniyyahJournalReportXlsxExporter
{
    public function export(array $report, string $scope = 'management'): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'diniyyah-journal-');
        if ($temporaryPath === false) {
            throw new \RuntimeException('Tidak dapat membuat file sementara untuk ekspor XLSX.');
        }

        $zip = new ZipArchive;
        if ($zip->open($temporaryPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($temporaryPath);
            throw new \RuntimeException('Tidak dapat membuat workbook XLSX.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->summarySheetXml($report, $scope));
        $zip->addFromString('xl/worksheets/sheet2.xml', $this->detailSheetXml($report));
        $zip->close();

        $content = file_get_contents($temporaryPath);
        @unlink($temporaryPath);

        if ($content === false) {
            throw new \RuntimeException('Tidak dapat membaca hasil ekspor XLSX.');
        }

        return $content;
    }

    private function summarySheetXml(array $report, string $scope): string
    {
        $stats = $report['stats'] ?? [];
        $rows = [
            [['LAPORAN JURNAL DINIYYAH', 1]],
            [[
                $scope === 'guru' ? 'Laporan jurnal yang tercatat atas nama guru ini' : 'Laporan full data untuk kebutuhan monitoring sekolah',
                2,
            ]],
            [['Dicetak', 5], [now()->format('Y-m-d H:i'), 7]],
            [['', 0]],
            [['RINGKASAN STATISTIK', 3]],
            [['Total jurnal', 5], [$stats['total_jurnal'] ?? 0, 6]],
            [['Total guru', 5], [$stats['total_guru'] ?? 0, 6]],
            [['Total kelas', 5], [$stats['total_kelas'] ?? 0, 6]],
            [['Total mapel', 5], [$stats['total_mapel'] ?? 0, 6]],
            [['Total JP', 5], [$stats['total_jp'] ?? 0, 6]],
            [['Jurnal reguler', 5], [$stats['jurnal_reguler'] ?? 0, 6]],
            [['Jurnal pengganti', 5], [$stats['jurnal_pengganti'] ?? 0, 6]],
            [['Hari tercatat', 5], [$stats['hari_tercatat'] ?? 0, 6]],
            [['', 0]],
            [['REKAP PER GURU', 3]],
            [['Nama Guru', 4], ['Jumlah Jurnal', 4], ['Total JP', 4]],
        ];

        foreach (($stats['by_teacher'] ?? collect()) as $teacher) {
            $rows[] = [[$teacher['name'], 0], [$teacher['journals'], 6], [$teacher['jp'], 6]];
        }

        if (($stats['by_teacher'] ?? collect())->isEmpty()) {
            $rows[] = [['Tidak ada data jurnal untuk filter ini.', 0]];
        }

        $filterLabels = $report['filter_labels'] ?? [];
        $rows[] = [['', 0]];
        $filterHeadingRow = count($rows) + 1;
        $rows[] = [['FILTER YANG DIGUNAKAN', 3]];
        foreach ($filterLabels as $label => $value) {
            $rows[] = [[$label, 5], [$value ?: 'Semua', 0]];
        }

        return $this->worksheetXml($rows, [30, 16, 16], null, [
            'A1:C1',
            'A2:C2',
            'A5:C5',
            'A15:C15',
            'A'.$filterHeadingRow.':C'.$filterHeadingRow,
        ]);
    }

    private function detailSheetXml(array $report): string
    {
        $headers = [
            'No', 'Tanggal', 'Jam', 'Kelas', 'Mapel', 'Guru Asli', 'Pengganti',
            'Guru Mengajar', 'Jenis', 'Materi', 'JP', 'Hadir', 'Sakit', 'Izin', 'Alpa', 'Bolos',
        ];
        $rows = [
            [['DETAIL JURNAL DINIYYAH', 1]],
            [['Data dapat difilter menggunakan tombol filter pada baris header.', 2]],
            [['', 0]],
            array_map(fn (string $header): array => [$header, 4], $headers),
        ];

        foreach (collect($report['rows'] ?? []) as $index => $row) {
            $rows[] = [
                [$index + 1, 6],
                [$row['date'] ?? '-', 7],
                [$row['session_label'] ?? '-', 0],
                [$row['kelas'] ?? '-', 0],
                [$row['mapel'] ?? '-', 0],
                [$row['guru_asli'] ?? '-', 0],
                [$row['pengganti'] ?? '-', 0],
                [$row['guru_mengajar'] ?? '-', 0],
                [$row['type_label'] ?? '-', 0],
                [$row['material'] ?? '-', 0],
                [$row['jp'] ?? 0, 6],
                [$row['hadir'] ?? 0, 6],
                [$row['sakit'] ?? 0, 6],
                [$row['izin'] ?? 0, 6],
                [$row['alpa'] ?? 0, 6],
                [$row['bolos'] ?? 0, 6],
            ];
        }

        if (count($rows) === 4) {
            $rows[] = [['Tidak ada data jurnal untuk filter ini.', 0]];
        }

        return $this->worksheetXml($rows, [8, 14, 16, 22, 22, 22, 22, 22, 14, 42, 8, 9, 9, 9, 9, 9], 4, [
            'A1:P1',
            'A2:P2',
        ], 4);
    }

    /** @param array<int, array<int, array{0: mixed, 1: int}>> $rows */
    private function worksheetXml(array $rows, array $widths, ?int $freezeRow, array $merges = [], ?int $filterRow = null): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheetViews><sheetView workbookViewId="0">';
        if ($freezeRow !== null) {
            $topLeft = 'A'.($freezeRow + 1);
            $xml .= '<pane ySplit="'.($freezeRow).'" topLeftCell="'.$topLeft.'" activePane="bottomLeft" state="frozen"/>';
        }
        $xml .= '</sheetView></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="18"/>';
        $xml .= '<cols>';
        foreach ($widths as $index => $width) {
            $column = $index + 1;
            $xml .= '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';

        foreach ($rows as $rowIndex => $cells) {
            $excelRow = $rowIndex + 1;
            $xml .= '<row r="'.$excelRow.'">';
            foreach ($cells as $columnIndex => [$value, $style]) {
                $coordinate = $this->columnName($columnIndex + 1).$excelRow;
                $xml .= $this->cellXml($coordinate, $value, $style);
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData>';
        if ($filterRow !== null && count($rows) >= $filterRow) {
            $lastColumn = $this->columnName(count($widths));
            $lastRow = max($filterRow, count($rows));
            $xml .= '<autoFilter ref="A'.$filterRow.':'.$lastColumn.$lastRow.'"/>';
        }
        if ($merges !== []) {
            $xml .= '<mergeCells count="'.count($merges).'">';
            foreach ($merges as $merge) {
                $xml .= '<mergeCell ref="'.$merge.'"/>';
            }
            $xml .= '</mergeCells>';
        }
        $xml .= '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>';
        $xml .= '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0" paperSize="9"/>';
        $xml .= '</worksheet>';

        return $xml;
    }

    private function cellXml(string $coordinate, mixed $value, int $style): string
    {
        if (is_int($value) || is_float($value)) {
            return '<c r="'.$coordinate.'" s="'.$style.'" t="n"><v>'.$value.'</v></c>';
        }

        $text = $this->xmlText((string) ($value ?? ''));

        return '<c r="'.$coordinate.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $name = chr(65 + $remainder).$name;
            $number = intdiv($number - 1, 26);
        }

        return $name;
    }

    private function xmlText(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? $value;

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<bookViews><workbookView xWindow="0" yWindow="0" windowWidth="24000" windowHeight="12000"/></bookViews>'
            .'<sheets><sheet name="Ringkasan" sheetId="1" r:id="rId1"/><sheet name="Detail Jurnal" sheetId="2" r:id="rId2"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="yyyy-mm-dd"/></numFmts>'
            .'<fonts count="3"><font><sz val="10"/><name val="Aptos"/></font><font><b/><sz val="10"/><name val="Aptos"/></font><font><b/><sz val="14"/><color rgb="FFFFFFFF"/><name val="Aptos Display"/></font></fonts>'
            .'<fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF12304A"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F6B8F"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEAF3F7"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD6E0E8"/></left><right style="thin"><color rgb="FFD6E0E8"/></right><top style="thin"><color rgb="FFD6E0E8"/></top><bottom style="thin"><color rgb="FFD6E0E8"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="8">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0"><alignment horizontal="left" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="4" borderId="0" xfId="0"><alignment wrapText="1" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0"><alignment horizontal="left" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="4" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
