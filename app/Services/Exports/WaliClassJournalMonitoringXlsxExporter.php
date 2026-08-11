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
        foreach ($data['monitoringData'] as $day) {
            foreach ($day['items'] as $item) {
                $lastRow++;
                $assignment = $item['schedule']->teacherAssignment ?? null;
                $journal = $item['journal'] ?? null;
                $status = match ($item['status']) {
                    'TERISI' => 'Terisi',
                    'TERISI_TIDAK_TERJADWAL' => 'Terisi (Ekstra)',
                    'LIBUR' => 'Libur',
                    default => 'Kosong',
                };
                $time = collect([$item['session_time']['starts_at'] ?? null, $item['session_time']['ends_at'] ?? null])
                    ->filter()->map(fn ($value) => substr((string) $value, 0, 5))->implode(' - ');
                $material = '-';
                if ($journal) {
                    $absences = $journal->absences->map(fn ($absence) => ($absence->classEnrollment->student->name ?? '-') .' ('. $absence->status .')')->implode(', ');
                    $material = 'Materi: '.($journal->material ?: '-')."\nAbsensi: ".($absences ?: 'Hadir Semua');
                }
                $sheet->fromArray([[
                    $day['date']->translatedFormat('l, d/m/Y').($day['is_holiday'] ? ' - '.$day['holiday_name'] : ''),
                    $time ?: ('Jam '.($item['schedule']->classSession->session_name ?? '-')),
                    ($assignment?->classSubject?->classroomTerm?->name ?? '-')."\n".($assignment?->classSubject?->subject?->name ?? '-'),
                    $assignment?->teacher?->name ?? '-', $status, $material,
                ]], null, "A{$lastRow}");
            }
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
