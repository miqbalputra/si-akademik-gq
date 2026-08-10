<?php

namespace App\Services\Exports;

use App\Models\Teacher;
use ZipArchive;

/**
 * Writer XLSX ringan untuk laporan performa guru.
 *
 * Workbook berisi ringkasan status jurnal dan daftar slot yang masih kosong.
 */
class GuruPerformaXlsxExporter
{
    public function export(array $performa, Teacher $teacher): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'guru-performa-');
        if ($temporaryPath === false) {
            throw new \RuntimeException('Tidak dapat membuat file sementara untuk ekspor performa.');
        }

        $zip = new ZipArchive;
        if ($zip->open($temporaryPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($temporaryPath);
            throw new \RuntimeException('Tidak dapat membuat workbook XLSX performa.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->summarySheetXml($performa, $teacher));
        $zip->addFromString('xl/worksheets/sheet2.xml', $this->journalSheetXml($performa));
        $zip->addFromString('xl/worksheets/sheet3.xml', $this->emptySlotsSheetXml($performa));
        $zip->close();

        $content = file_get_contents($temporaryPath);
        @unlink($temporaryPath);

        if ($content === false) {
            throw new \RuntimeException('Tidak dapat membaca hasil ekspor XLSX performa.');
        }

        return $content;
    }

    private function summarySheetXml(array $performa, Teacher $teacher): string
    {
        $stats = $performa['stats'] ?? [];
        $rows = [
            [['LAPORAN PERFORMA MENGAJAR GURU', 1]],
            [['Guru', 5], [$teacher->name, 0]],
            [['Periode', 5], [$performa['month_label'] ?? '-', 0]],
            [['Dicetak', 5], [now()->format('Y-m-d H:i'), 0]],
            [['', 0]],
            [['RINGKASAN STATUS JURNAL', 3]],
            [['Status', 4], ['Jumlah', 4]],
            [['Sudah diisi', 0], [$stats['sudah_diisi'] ?? 0, 6]],
            [['Kosong', 0], [$stats['kosong'] ?? 0, 6]],
            [['Digantikan', 0], [$stats['digantikan'] ?? 0, 6]],
            [['Total slot tercatat', 0], [$stats['total'] ?? 0, 6]],
            [['Total data jurnal', 0], [$stats['total_jurnal'] ?? 0, 6]],
            [['', 0]],
            [['CATATAN', 3]],
            [['Slot kosong berarti jadwal yang sudah lewat tetapi belum memiliki jurnal.', 2]],
        ];

        return $this->worksheetXml($rows, [34, 24], null, ['A1:B1', 'A6:B6', 'A14:B14']);
    }

    private function journalSheetXml(array $performa): string
    {
        $headers = [
            'No', 'Tanggal', 'Sesi', 'Jam', 'Kelas', 'Mapel', 'Materi', 'JP',
            'Guru Asli', 'Pengganti', 'Guru Mengajar', 'Status', 'Hadir',
            'Sakit', 'Izin', 'Alpa', 'Bolos',
        ];
        $rows = [
            [['DETAIL SEMUA DATA JURNAL', 1]],
            [['Seluruh jurnal guru pada periode terpilih, termasuk jurnal yang diisi guru pengganti.', 2]],
            [['', 0]],
            array_map(fn (string $header): array => [$header, 4], $headers),
        ];

        foreach (collect($performa['journal_rows'] ?? []) as $index => $row) {
            $rows[] = [
                [$index + 1, 6],
                [$row['date_label'] ?? ($row['date'] ?? '-'), 0],
                [$row['session_label'] ?? '-', 0],
                [$row['session_time'] ?? '-', 0],
                [$row['kelas'] ?? '-', 0],
                [$row['mapel'] ?? '-', 0],
                [$row['material'] ?? '-', 0],
                [$row['jp'] ?? 0, 6],
                [$row['guru_asli'] ?? '-', 0],
                [$row['pengganti'] ?? '-', 0],
                [$row['guru_mengajar'] ?? '-', 0],
                [$row['type_label'] ?? '-', 0],
                [$row['hadir'] ?? 0, 6],
                [$row['sakit'] ?? 0, 6],
                [$row['izin'] ?? 0, 6],
                [$row['alpa'] ?? 0, 6],
                [$row['bolos'] ?? 0, 6],
            ];
        }

        if (count($rows) === 4) {
            $rows[] = [['Tidak ada data jurnal pada periode ini.', 0]];
        }

        return $this->worksheetXml(
            $rows,
            [8, 25, 20, 14, 26, 24, 42, 8, 24, 24, 24, 16, 9, 9, 9, 9, 9],
            4,
            ['A1:Q1', 'A2:Q2'],
            4,
        );
    }

    private function emptySlotsSheetXml(array $performa): string
    {
        $headers = ['No', 'Tanggal', 'Sesi', 'Jam', 'Mapel', 'Kelas', 'Keterangan'];
        $rows = [
            [['DAFTAR SLOT JURNAL KOSONG', 1]],
            [['Slot yang perlu dilengkapi pada periode terpilih.', 2]],
            [['', 0]],
            array_map(fn (string $header): array => [$header, 4], $headers),
        ];

        foreach (collect($performa['empty_slots'] ?? []) as $index => $slot) {
            $time = collect([$slot['starts_at'] ?? null, $slot['ends_at'] ?? null])
                ->filter()
                ->map(fn ($value): string => substr((string) $value, 0, 5))
                ->implode(' - ');

            $rows[] = [
                [$index + 1, 6],
                [$slot['date_label'] ?? ($slot['date'] ?? '-'), 0],
                [$slot['session_label'] ?? '-', 0],
                [$time ?: '-', 0],
                [$slot['subject_name'] ?? '-', 0],
                [$slot['classroom_names'] ?? '-', 0],
                [(($slot['is_tafsir'] ?? false) ? 'Tafsir serentak' : 'Jurnal reguler'), 0],
            ];
        }

        if (count($rows) === 4) {
            $rows[] = [['Tidak ada slot jurnal kosong pada periode ini.', 0]];
        }

        return $this->worksheetXml($rows, [8, 24, 20, 14, 24, 34, 20], 4, ['A1:G1', 'A2:G2'], 4);
    }

    /** @param array<int, array<int, array{0: mixed, 1: int}>> $rows */
    private function worksheetXml(array $rows, array $widths, ?int $freezeRow, array $merges = [], ?int $filterRow = null): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetViews><sheetView workbookViewId="0">';
        if ($freezeRow !== null) {
            $xml .= '<pane ySplit="'.$freezeRow.'" topLeftCell="A'.($freezeRow + 1).'" activePane="bottomLeft" state="frozen"/>';
        }
        $xml .= '</sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/><cols>';

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
        if ($filterRow !== null) {
            $lastColumn = $this->columnName(count($widths));
            $xml .= '<autoFilter ref="A'.$filterRow.':'.$lastColumn.max($filterRow, count($rows)).'"/>';
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

        return '<c r="'.$coordinate.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
            .$this->xmlText((string) ($value ?? '')).'</t></is></c>';
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
            .'<Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
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
            .'<sheets><sheet name="Ringkasan" sheetId="1" r:id="rId1"/><sheet name="Detail Jurnal" sheetId="2" r:id="rId2"/><sheet name="Slot Kosong" sheetId="3" r:id="rId3"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>'
            .'<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="3"><font><sz val="10"/><name val="Aptos"/></font><font><b/><sz val="10"/><name val="Aptos"/></font><font><b/><sz val="14"/><color rgb="FFFFFFFF"/><name val="Aptos Display"/></font></fonts>'
            .'<fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF12304A"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F6B8F"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEAF3F7"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD6E0E8"/></left><right style="thin"><color rgb="FFD6E0E8"/></right><top style="thin"><color rgb="FFD6E0E8"/></top><bottom style="thin"><color rgb="FFD6E0E8"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="7">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0"><alignment horizontal="left" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="4" borderId="0" xfId="0"><alignment wrapText="1" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0"><alignment horizontal="left" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="4" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
