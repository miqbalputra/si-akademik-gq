<?php

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
use App\Models\DiniyyahClassJournal;
use App\Services\DiniyyahJournalReportService;
use App\Services\Exports\DiniyyahJournalReportXlsxExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Ekspor lengkap seluruh jurnal diniyyah (reguler + pengganti) untuk admin,
 * kabag_diniyyah, dan kepala_sekolah. Format legacy .xls/CSV tetap tersedia,
 * dengan format XLSX dan PDF baru untuk laporan interaktif.
 *
 * Kolom "Guru Mengajar (untuk gaji)" = pengganti jika ada, else guru asli —
 * inilah kolom yang dipakai penghitungan gaji guru.
 */
class DiniyyahJournalExportController extends Controller
{
    public function export(
        Request $request,
        DiniyyahJournalReportService $reportService,
        DiniyyahJournalReportXlsxExporter $xlsxExporter,
    ) {
        abort_unless(
            $request->user()->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']),
            403
        );

        $format = $request->input('format', 'excel');
        $rangeLabel = $this->rangeLabel($request);

        if ($format === 'csv') {
            $data = $this->buildData($request);

            return $this->csvResponse($data['rows'], $rangeLabel);
        }

        if ($format === 'xlsx') {
            $report = $reportService->build($this->reportFilters($request));
            $content = $xlsxExporter->export($report, 'management');
            $fileName = 'Jurnal-Diniyyah-'.$rangeLabel.'.xlsx';

            return response($content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
                'Content-Length' => strlen($content),
            ]);
        }

        if ($format === 'pdf') {
            $report = $reportService->build($this->reportFilters($request));

            return Pdf::loadView('reports.diniyyah-journal', [
                'report' => $report,
                'title' => 'Laporan Jurnal Diniyyah - Full Data',
                'scope' => 'management',
            ])
                ->setPaper('a3', 'landscape')
                ->download('Jurnal-Diniyyah-'.$rangeLabel.'.pdf');
        }

        $data = $this->buildData($request);
        $fileName = 'Jurnal-Diniyyah-'.$rangeLabel.'.xls';

        return response(view('admin.diniyyah-journals.export-excel', $data))
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', 'attachment; filename="'.$fileName.'"');
    }

    private function buildData(Request $request): array
    {
        $query = DiniyyahClassJournal::with([
            'teacherAssignment.teacher',
            'substituteTeacher',
            'teacherAssignment.classSubject.subject',
            'teacherAssignment.classSubject.classroomTerm.classroom',
            'absences.classEnrollment.student',
        ]);

        if ($from = $request->input('date_from')) {
            $query->whereDate('date', '>=', $from);
        }
        if ($until = $request->input('date_until')) {
            $query->whereDate('date', '<=', $until);
        }
        if ($guruId = $request->input('guru')) {
            // Filter guru: baik sebagai guru asli maupun pengganti.
            $query->where(function ($q) use ($guruId) {
                $q->whereHas('teacherAssignment', fn ($qq) => $qq->where('teacher_id', $guruId))
                    ->orWhere('substitute_teacher_id', $guruId);
            });
        }
        if ($tipe = $request->input('tipe')) {
            if ($tipe === 'regular') {
                $query->whereNull('substitute_teacher_id');
            } elseif ($tipe === 'substitute') {
                $query->whereNotNull('substitute_teacher_id');
            }
        }

        $journals = $query->orderBy('date', 'asc')->orderBy('session_hour', 'asc')->get();

        // Hitung rekap kehadiran per jurnal.
        $rows = $journals->map(function (DiniyyahClassJournal $journal) {
            $absenceCounts = ['sick' => 0, 'permission' => 0, 'absent' => 0, 'skipped' => 0];
            foreach ($journal->absences as $abs) {
                if (array_key_exists($abs->status, $absenceCounts)) {
                    $absenceCounts[$abs->status]++;
                }
            }
            $absenceTotal = array_sum($absenceCounts);

            // Jumlah enrollment aktif kelas (untuk hitung "Hadir").
            $activeEnrollmentCount = ClassEnrollment::query()
                ->where('classroom_term_id', $journal->teacherAssignment->classSubject->classroom_term_id)
                ->where('status', 'active')
                ->count();
            $hadir = max(0, $activeEnrollmentCount - $absenceTotal);

            return [
                'journal' => $journal,
                'guru_asli' => $journal->teacherAssignment->teacher?->name ?? '-',
                'pengganti' => $journal->substituteTeacher?->name,
                'guru_mengajar' => $journal->effectiveTeacher()?->name ?? '-',
                'kelas' => $journal->teacherAssignment->classSubject->classroomTerm?->name ?? '-',
                'mapel' => $journal->teacherAssignment->classSubject->subject?->name ?? '-',
                'hadir' => $hadir,
                'sakit' => $absenceCounts['sick'],
                'izin' => $absenceCounts['permission'],
                'alpa' => $absenceCounts['absent'],
                'bolos' => $absenceCounts['skipped'],
            ];
        })->values();

        return [
            'rows' => $rows,
            'filters' => [
                'date_from' => $request->input('date_from'),
                'date_until' => $request->input('date_until'),
                'guru' => $request->input('guru'),
                'tipe' => $request->input('tipe'),
            ],
        ];
    }

    private function rangeLabel(Request $request): string
    {
        $from = $request->input('date_from');
        $until = $request->input('date_until');
        if ($from && $until) {
            return str_replace('-', '', $from).'_'.str_replace('-', '', $until);
        }
        if ($from) {
            return 'dari_'.str_replace('-', '', $from);
        }
        if ($until) {
            return 'sd_'.str_replace('-', '', $until);
        }

        return 'semua';
    }

    /** @return array<string, mixed> */
    private function reportFilters(Request $request): array
    {
        return [
            'academic_term_id' => $request->input('academic_term_id'),
            'date_from' => $request->input('date_from'),
            'date_until' => $request->input('date_until'),
            'teacher_id' => $request->input('teacher_id', $request->input('guru')),
            'classroom_term_id' => $request->input('classroom_term_id'),
            'subject_id' => $request->input('subject_id'),
            'type' => $request->input('type', $request->input('tipe')),
            'search' => $request->input('search'),
        ];
    }

    private function csvResponse($rows, string $rangeLabel)
    {
        $fileName = 'Jurnal-Diniyyah-'.$rangeLabel.'.csv';
        $headers = [
            'Guru Asli',
            'Pengganti',
            'Guru Mengajar (untuk gaji)',
            'Tanggal',
            'Jam',
            'Kelas',
            'Mapel',
            'Materi',
            'JP',
            'Hadir',
            'Sakit',
            'Izin',
            'Alpa',
            'Bolos',
        ];

        // Bangun CSV di memory (bukan stream) agar mudah diuji & di-cache.
        $handle = fopen('php://temp', 'r+');
        // BOM agar Excel Windows membaca UTF-8 dengan benar.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            $j = $row['journal'];
            fputcsv($handle, [
                $row['guru_asli'],
                $row['pengganti'] ?? '-',
                $row['guru_mengajar'],
                $j->date?->format('Y-m-d'),
                $j->session_hour,
                $row['kelas'],
                $row['mapel'],
                $j->material,
                $j->jp_count,
                $row['hadir'],
                $row['sakit'],
                $row['izin'],
                $row['alpa'],
                $row['bolos'],
            ]);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }
}
