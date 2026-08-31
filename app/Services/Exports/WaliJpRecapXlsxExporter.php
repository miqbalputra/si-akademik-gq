<?php

namespace App\Services\Exports;

class WaliJpRecapXlsxExporter
{
    public function __construct(private readonly SpreadsheetTheme $theme) {}

    /** @param array<string, mixed> $data */
    public function export(array $data): string
    {
        $workbook = $this->theme->workbook();
        $realized = $workbook->getActiveSheet();
        $realized->setTitle('JP Terealisasi');
        $this->realizedJp($realized, $data);

        $missingJournals = $workbook->createSheet();
        $missingJournals->setTitle('Jurnal Kosong');
        $this->missingJournals($missingJournals, $data);

        return $this->theme->save($workbook);
    }

    /** @param array<string, mixed> $data */
    private function realizedJp($sheet, array $data): void
    {
        $headers = ['No', 'Guru', 'Mapel/Tugas', 'JP Asli', 'JP Pengganti', 'JP Tafsir', 'JP Terealisasi', 'Verifikasi'];
        $period = $data['periodStart']->translatedFormat('F Y');
        $this->theme->title($sheet, 'REKAP JP TEREALISASI GURU PER KELAS', $data['classroomTerm']->name.' · '.$period.' · Dasar data penggajian dari jurnal yang terisi.', count($headers));
        $sheet->setCellValue('A5', 'Wali Kelas');
        $sheet->setCellValue('B5', $data['teacher']->name);
        $this->theme->tableHeader($sheet, 7, $headers);

        $lastRow = 7;
        foreach ($data['recap']['teachers'] as $index => $row) {
            $lastRow++;
            $state = $row['confirmation'];
            $verification = $state['label'].(! empty($state['reason']) ? ': '.$state['reason'] : '');
            $sheet->fromArray([[
                $index + 1, $row['name'], collect($row['subjects'])->implode(', ') ?: '-',
                $row['sesi_asli'], $row['sesi_pengganti'], $row['sesi_tafsir'], $row['jp_terealisasi'], $verification,
            ]], null, "A{$lastRow}");
        }
        if ($lastRow === 7) {
            $lastRow++;
            $sheet->setCellValue('A8', 'Belum ada guru atau jurnal pada periode ini.');
        }
        $this->theme->widths($sheet, [6, 25, 30, 12, 14, 12, 18, 28]);
        $this->theme->finaliseTable($sheet, 7, $lastRow, count($headers));

        $totalRow = $lastRow + 2;
        $sheet->mergeCells("A{$totalRow}:F{$totalRow}");
        $sheet->setCellValue("A{$totalRow}", 'TOTAL JP TEREALISASI');
        $sheet->setCellValue("G{$totalRow}", $data['recap']['stats']['jp_terealisasi']);
        $sheet->getStyle("A{$totalRow}:G{$totalRow}")->getFont()->setBold(true);
    }

    /** @param array<string, mixed> $data */
    private function missingJournals($sheet, array $data): void
    {
        $headers = ['No', 'Guru', 'NIY', 'Kelas', 'Mapel', 'Tanggal', 'Sesi', 'Jam'];
        $period = $data['periodStart']->translatedFormat('F Y');
        $this->theme->title($sheet, 'DAFTAR JURNAL KOSONG', $data['classroomTerm']->name.' · '.$period.' · Slot jadwal yang belum memiliki jurnal.', count($headers));
        $this->theme->tableHeader($sheet, 5, $headers);
        $lastRow = 5;

        foreach (collect($data['recap']['missing_journal_rows'] ?? []) as $index => $row) {
            $lastRow++;
            $sheet->fromArray([[
                $index + 1,
                $row['teacher_name'],
                $row['niy'] ?: '-',
                $row['classroom_name'],
                $row['subject_name'],
                $row['date_label'],
                $row['session_name'],
                $row['session_time'],
            ]], null, "A{$lastRow}");
        }

        if ($lastRow === 5) {
            $lastRow++;
            $sheet->setCellValue('A6', 'Tidak ada jurnal kosong pada periode ini.');
        }

        $this->theme->widths($sheet, [6, 28, 14, 26, 24, 28, 14, 16]);
        $this->theme->finaliseTable($sheet, 5, $lastRow, count($headers));
    }
}
