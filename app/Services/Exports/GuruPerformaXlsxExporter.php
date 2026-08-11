<?php

namespace App\Services\Exports;

use App\Models\Teacher;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class GuruPerformaXlsxExporter
{
    public function __construct(private readonly SpreadsheetTheme $theme) {}

    public function export(array $performa, Teacher $teacher): string
    {
        $workbook = $this->theme->workbook();
        $summary = $workbook->getActiveSheet();
        $summary->setTitle('Ringkasan');
        $this->summary($summary, $performa, $teacher);

        $journals = $workbook->createSheet();
        $journals->setTitle('Detail Jurnal');
        $this->journals($journals, $performa);

        $emptySlots = $workbook->createSheet();
        $emptySlots->setTitle('Slot Kosong');
        $this->emptySlots($emptySlots, $performa);

        return $this->theme->save($workbook);
    }

    private function summary($sheet, array $performa, Teacher $teacher): void
    {
        $this->theme->title($sheet, 'LAPORAN PERFORMA MENGAJAR GURU', 'Ringkasan jurnal dan tugas mengajar pada periode terpilih.', 2);
        $sheet->setCellValue('A5', 'Guru');
        $sheet->setCellValue('B5', $teacher->name);
        $sheet->setCellValue('A6', 'Periode');
        $sheet->setCellValue('B6', $performa['month_label'] ?? '-');
        $sheet->mergeCells('A8:B8');
        $sheet->setCellValue('A8', 'RINGKASAN STATUS JURNAL');
        $sheet->getStyle('A8:B8')->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => SpreadsheetTheme::NEON_GREEN]]]);
        $this->theme->tableHeader($sheet, 9, ['Status', 'Jumlah']);
        $stats = $performa['stats'] ?? [];
        $rows = [
            ['Sudah diisi', $stats['sudah_diisi'] ?? 0],
            ['Kosong', $stats['kosong'] ?? 0],
            ['Digantikan', $stats['digantikan'] ?? 0],
            ['Total slot tercatat', $stats['total'] ?? 0],
            ['Total data jurnal', $stats['total_jurnal'] ?? 0],
        ];
        $sheet->fromArray($rows, null, 'A10');
        $this->theme->finaliseTable($sheet, 9, 14, 2);
        $sheet->mergeCells('A16:B16');
        $sheet->setCellValue('A16', 'CATATAN');
        $sheet->getStyle('A16:B16')->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFF1F5F1']]]);
        $sheet->mergeCells('A17:B18');
        $sheet->setCellValue('A17', 'Slot kosong berarti jadwal yang sudah lewat tetapi belum memiliki jurnal.');
        $sheet->getStyle('A17:B18')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $this->theme->widths($sheet, [34, 24]);
    }

    private function journals($sheet, array $performa): void
    {
        $headers = ['No', 'Tanggal', 'Sesi', 'Jam', 'Kelas', 'Mapel', 'Materi', 'JP', 'Guru Asli', 'Pengganti', 'Guru Mengajar', 'Status', 'Hadir', 'Sakit', 'Izin', 'Alpa', 'Bolos'];
        $this->theme->title($sheet, 'DETAIL SEMUA DATA JURNAL', 'Termasuk jurnal yang diisi guru pengganti.', count($headers));
        $this->theme->tableHeader($sheet, 5, $headers);
        $lastRow = 5;
        foreach (collect($performa['journal_rows'] ?? []) as $index => $row) {
            $lastRow++;
            $sheet->fromArray([[
                $index + 1, $row['date_label'] ?? ($row['date'] ?? '-'), $row['session_label'] ?? '-', $row['session_time'] ?? '-',
                $row['kelas'] ?? '-', $row['mapel'] ?? '-', $row['material'] ?? '-', $row['jp'] ?? 0, $row['guru_asli'] ?? '-',
                $row['pengganti'] ?? '-', $row['guru_mengajar'] ?? '-', $row['type_label'] ?? '-', $row['hadir'] ?? 0,
                $row['sakit'] ?? 0, $row['izin'] ?? 0, $row['alpa'] ?? 0, $row['bolos'] ?? 0,
            ]], null, "A{$lastRow}");
        }
        if ($lastRow === 5) {
            $lastRow++;
            $sheet->setCellValue('A6', 'Tidak ada data jurnal pada periode ini.');
        }
        $sheet->getStyle("A6:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->theme->widths($sheet, [8, 18, 18, 14, 26, 24, 42, 8, 24, 24, 24, 16, 9, 9, 9, 9, 9]);
        $this->theme->finaliseTable($sheet, 5, $lastRow, count($headers));
    }

    private function emptySlots($sheet, array $performa): void
    {
        $headers = ['No', 'Tanggal', 'Sesi', 'Jam', 'Mapel', 'Kelas', 'Keterangan'];
        $this->theme->title($sheet, 'DAFTAR SLOT JURNAL KOSONG', 'Slot yang perlu dilengkapi pada periode terpilih.', count($headers));
        $this->theme->tableHeader($sheet, 5, $headers);
        $lastRow = 5;
        foreach (collect($performa['empty_slots'] ?? []) as $index => $slot) {
            $lastRow++;
            $time = collect([$slot['starts_at'] ?? null, $slot['ends_at'] ?? null])->filter()->map(fn ($value) => substr((string) $value, 0, 5))->implode(' - ');
            $sheet->fromArray([[
                $index + 1, $slot['date_label'] ?? ($slot['date'] ?? '-'), $slot['session_label'] ?? '-', $time ?: '-',
                $slot['subject_name'] ?? '-', $slot['classroom_names'] ?? '-', ($slot['is_tafsir'] ?? false) ? 'Tafsir serentak' : 'Jurnal reguler',
            ]], null, "A{$lastRow}");
        }
        if ($lastRow === 5) {
            $lastRow++;
            $sheet->setCellValue('A6', 'Tidak ada slot jurnal kosong pada periode ini.');
        }
        $this->theme->widths($sheet, [8, 24, 20, 14, 24, 34, 20]);
        $this->theme->finaliseTable($sheet, 5, $lastRow, count($headers));
    }
}
