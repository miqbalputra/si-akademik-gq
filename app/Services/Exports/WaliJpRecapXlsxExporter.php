<?php

namespace App\Services\Exports;

class WaliJpRecapXlsxExporter
{
    public function __construct(private readonly SpreadsheetTheme $theme) {}

    /** @param array<string, mixed> $data */
    public function export(array $data): string
    {
        $headers = ['No', 'Guru', 'Mapel/Tugas', 'JP Asli', 'JP Pengganti', 'JP Tafsir', 'Total JP', 'Jurnal Kosong', 'Verifikasi'];
        $workbook = $this->theme->workbook();
        $sheet = $workbook->getActiveSheet();
        $sheet->setTitle('Rekap JP');
        $period = $data['periodStart']->translatedFormat('F Y');
        $this->theme->title($sheet, 'REKAP JP GURU PER KELAS', $data['classroomTerm']->name.' · '.$period, count($headers));
        $sheet->setCellValue('A5', 'Wali Kelas');
        $sheet->setCellValue('B5', $data['teacher']->name);
        $this->theme->tableHeader($sheet, 7, $headers);

        $lastRow = 7;
        foreach ($data['recap']['teachers'] as $index => $row) {
            $lastRow++;
            $state = $row['confirmation'];
            $verification = $state['label'].(! empty($state['reason']) ? ': '.$state['reason'] : '');
            $missing = collect($row['missing_slots'])->pluck('label')->implode("\n") ?: '-';
            $sheet->fromArray([[
                $index + 1, $row['name'], collect($row['subjects'])->implode(', ') ?: '-',
                $row['sesi_asli'], $row['sesi_pengganti'], $row['sesi_tafsir'], $row['total_jp'], $missing, $verification,
            ]], null, "A{$lastRow}");
        }
        if ($lastRow === 7) {
            $lastRow++;
            $sheet->setCellValue('A8', 'Belum ada guru atau jurnal pada periode ini.');
        }
        $this->theme->widths($sheet, [6, 25, 30, 12, 14, 12, 12, 42, 28]);
        $this->theme->finaliseTable($sheet, 7, $lastRow, count($headers));

        return $this->theme->save($workbook);
    }
}
