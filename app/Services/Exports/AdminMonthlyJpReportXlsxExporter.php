<?php

namespace App\Services\Exports;

use Carbon\Carbon;

class AdminMonthlyJpReportXlsxExporter
{
    public function __construct(private readonly SpreadsheetTheme $theme) {}

    public function export(array $report): string
    {
        $workbook = $this->theme->workbook();
        $summary = $workbook->getActiveSheet();
        $summary->setTitle('Rekap JP');
        $this->summary($summary, $report);
        $detail = $workbook->createSheet();
        $detail->setTitle('Detail JP Terealisasi');
        $this->detail($detail, $report);
        $missing = $workbook->createSheet();
        $missing->setTitle('Jurnal Kosong');
        $this->missing($missing, $report);

        return $this->theme->save($workbook);
    }

    private function summary($sheet, array $report): void
    {
        $headers = ['No', 'Guru', 'NIY', 'Status', 'Kelas', 'Mapel', 'JP Asli', 'JP Pengganti', 'JP Tafsir', 'JP Terealisasi', 'Jurnal Kosong'];
        $this->theme->title($sheet, 'REKAP PENGGAJIAN JP GURU', $this->subtitle($report).' · Seluruh guru berakun ditampilkan.', count($headers));
        $this->theme->tableHeader($sheet, 5, $headers);
        $last = 5;
        foreach ($report['teachers'] as $index => $teacher) {
            $last++;
            $sheet->fromArray([[$index + 1, $teacher['name'], $teacher['niy'] ?: '-', $teacher['status'] ?: '-', implode(', ', $teacher['classes']) ?: '-', implode(', ', $teacher['subjects']) ?: '-', $teacher['sesi_asli'], $teacher['sesi_pengganti'], $teacher['sesi_tafsir'], $teacher['total_jp'], $teacher['missing_count']]], null, "A{$last}");
            foreach (['G' => 'sesi_asli', 'H' => 'sesi_pengganti', 'I' => 'sesi_tafsir', 'J' => 'total_jp', 'K' => 'missing_count'] as $column => $key) {
                $sheet->setCellValue("{$column}{$last}", (int) $teacher[$key]);
            }
        }
        if ($last === 5) {
            $last++;
            $sheet->setCellValue('A6', 'Tidak ada guru berakun pada periode ini.');
        }
        $this->theme->widths($sheet, [6, 25, 15, 13, 30, 26, 11, 14, 11, 16, 15]);
        $this->theme->finaliseTable($sheet, 5, $last, count($headers));
    }

    private function detail($sheet, array $report): void
    {
        $headers = ['No', 'Tanggal', 'Sesi', 'Jam', 'Kelas', 'Mapel', 'Guru Asli', 'Pengganti', 'Guru Mengajar', 'Jenis', 'Materi', 'JP'];
        $this->theme->title($sheet, 'DETAIL JP TEREALISASI', $this->subtitle($report).' · Tafsir serentak dihitung satu JP per sesi.', count($headers));
        $this->theme->tableHeader($sheet, 5, $headers);
        $last = 5;
        foreach ($report['realized'] as $index => $row) {
            $last++;
            $sheet->fromArray([[$index + 1, $row['date_label'], $row['session'], $row['session_time'], implode(', ', $row['classes']) ?: '-', implode(', ', $row['subjects']) ?: '-', $row['original_teacher'], $row['substitute_teacher'] ?: '-', $row['teacher_name'], $row['type'], $row['material'], $row['jp']]], null, "A{$last}");
            $sheet->setCellValue("L{$last}", (int) $row['jp']);
        }
        if ($last === 5) {
            $last++;
            $sheet->setCellValue('A6', 'Tidak ada JP terealisasi pada periode ini.');
        }
        $this->theme->widths($sheet, [6, 24, 18, 16, 30, 25, 24, 24, 24, 18, 42, 8]);
        $this->theme->finaliseTable($sheet, 5, $last, count($headers));
    }

    private function missing($sheet, array $report): void
    {
        $headers = ['No', 'Guru', 'NIY', 'Kelas', 'Mapel', 'Tanggal', 'Sesi', 'Jam', 'Status'];
        $this->theme->title($sheet, 'DAFTAR JURNAL KOSONG', $this->subtitle($report).' · Libur, agenda, izin, dan sakit tidak tercantum.', count($headers));
        $this->theme->tableHeader($sheet, 5, $headers);
        $last = 5;
        foreach ($report['missing'] as $index => $row) {
            $last++;
            $sheet->fromArray([[$index + 1, $row['teacher_name'], $row['niy'] ?: '-', implode(', ', $row['classes']) ?: '-', implode(', ', $row['subjects']) ?: '-', $row['date_label'], $row['session'], $row['session_time'], $row['status']]], null, "A{$last}");
        }
        if ($last === 5) {
            $last++;
            $sheet->setCellValue('A6', 'Tidak ada jurnal kosong pada periode ini.');
        }
        $this->theme->widths($sheet, [6, 28, 15, 30, 25, 26, 20, 16, 14]);
        $this->theme->finaliseTable($sheet, 5, $last, count($headers));
    }

    private function subtitle(array $report): string
    {
        return ($report['term']->academicYear?->name ?? '-').' - '.$report['term']->name.' · '.Carbon::create()->month($report['month'])->translatedFormat('F').' '.$report['year'];
    }
}
