<?php

namespace App\Services\Exports;

class WaliClassJournalMonitoringXlsxExporter
{
    public function __construct(private readonly SpreadsheetTheme $theme) {}

    public function export(array $data): string
    {
        $headers = ['Hari/Tanggal', 'Jam Sesi', 'Kelas & Mapel', 'Guru Pengajar', 'Status', 'Materi & Kehadiran'];
        $workbook = $this->theme->workbook();
        $sheet = $workbook->getActiveSheet();
        $sheet->setTitle('Rekap Jurnal');
        $monthName = \Carbon\Carbon::create()->month($data['month'])->translatedFormat('F');
        $this->theme->title($sheet, 'REKAPITULASI JURNAL MENGAJAR DINIYYAH', "Bulan {$monthName} {$data['year']}", count($headers));
        $sheet->setCellValue('A5', 'Wali Kelas');
        $sheet->setCellValue('B5', $data['teacher']->name);
        $this->theme->tableHeader($sheet, 7, $headers);

        $lastRow = 7;
        foreach ($data['monitoringRows'] ?? [] as $row) {
                $lastRow++;
                $journal = $row['journal'] ?? null;
                $status = match ($row['status']) {
                    'TERISI' => 'Terisi',
                    'TERISI_TIDAK_TERJADWAL' => 'Terisi (Ekstra)',
                    'LIBUR' => 'Libur',
                    'IZIN' => 'IZIN - Dibebaskan',
                    'SAKIT' => 'SAKIT - Dibebaskan',
                    default => 'Kosong',
                };
                $time = collect([$row['session_time']['starts_at'] ?? null, $row['session_time']['ends_at'] ?? null])
                    ->filter()->map(fn ($value) => substr((string) $value, 0, 5))->implode(' - ');
                $material = '-';
                if ($journal) {
                    $absences = $journal->absences->map(fn ($absence) => ($absence->classEnrollment->student->name ?? '-') .' ('. $absence->status .')')->implode(', ');
                    $material = 'Materi: '.($journal->material ?: '-')."\nAbsensi: ".($absences ?: 'Hadir Semua');
                } elseif (in_array($row['status'], ['IZIN', 'SAKIT'], true)) {
                    $material = 'Dibebaskan oleh presensi: '.strtolower($row['status']);
                }
                $sheet->fromArray([[
                    $row['date']->translatedFormat('l, d/m/Y').($row['is_holiday'] ? ' - '.$row['holiday_name'] : ''),
                    $time ?: ('Jam '.($row['session_name'] ?? '-')),
                    ($row['classroom_name'] ?? '-')."\n".($row['subject_name'] ?? '-'),
                    ($row['teacher_name'] ?? '-').($row['substitute_teacher_name'] ? "\nDiisi pengganti: {$row['substitute_teacher_name']}" : ''),
                    $status, $material,
                ]], null, "A{$lastRow}");
        }
        if ($lastRow === 7) {
            $lastRow++;
            $sheet->setCellValue('A8', 'Tidak ada data jadwal untuk periode dan filter yang dipilih.');
        }
        $this->theme->widths($sheet, [23, 16, 30, 24, 18, 52]);
        $this->theme->finaliseTable($sheet, 7, $lastRow, count($headers));

        return $this->theme->save($workbook);
    }
}
