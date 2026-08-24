<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Services\AttendanceTeacherReportClient;
use App\Services\Exports\GuruAttendanceReportXlsxExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GuruAttendanceReportController extends Controller
{
    public function index(Request $request, AttendanceTeacherReportClient $client)
    {
        $teacher = $this->teacherFor($request);
        [$monthValue, $start, $end, $periodLabel] = $this->period($request);
        $result = $client->reportForTeacher($teacher, $start, $end, $request->boolean('refresh'));
        $report = $result['ok'] ? $this->presentReport($result['report'], $periodLabel) : null;

        return view('guru.attendance-report.index', compact(
            'teacher', 'monthValue', 'periodLabel', 'result', 'report',
        ));
    }

    public function export(
        Request $request,
        string $format,
        AttendanceTeacherReportClient $client,
        GuruAttendanceReportXlsxExporter $xlsxExporter,
    ) {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        $teacher = $this->teacherFor($request);
        [, $start, $end, $periodLabel] = $this->period($request);
        $result = $client->reportForTeacher($teacher, $start, $end);
        abort_unless(
            $result['ok'],
            in_array($result['code'], ['mapping_missing', 'mapping_not_found'], true) ? 422 : 503,
            $result['message'],
        );

        $report = $this->presentReport($result['report'], $periodLabel);
        $fileStem = Str::slug('rekap-presensi-'.$teacher->name.'-'.$start->format('Y-m'));

        if ($format === 'xlsx') {
            $content = $xlsxExporter->export($report);

            return response($content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$fileStem.'.xlsx"',
                'Content-Length' => strlen($content),
            ]);
        }

        return Pdf::loadView('reports.guru-attendance-report', compact('teacher', 'report'))
            ->setPaper('a4', 'portrait')
            ->download($fileStem.'.pdf');
    }

    private function teacherFor(Request $request): Teacher
    {
        abort_unless($request->user()?->hasRole('guru'), 403);
        $teacher = $request->user()->teacher;
        abort_unless($teacher, 403, 'Akses ditolak. Akun Anda belum terhubung dengan data Guru.');

        return $teacher;
    }

    /** @return array{0: string, 1: Carbon, 2: Carbon, 3: string} */
    private function period(Request $request): array
    {
        $request->validate([
            'month' => ['nullable', 'regex:/^\\d{4}-(0[1-9]|1[0-2])$/'],
            'refresh' => ['nullable', 'boolean'],
        ]);

        $now = Carbon::now('Asia/Jakarta');
        $monthValue = (string) $request->input('month', $now->format('Y-m'));
        $monthStart = Carbon::createFromFormat('!Y-m', $monthValue, 'Asia/Jakarta')->startOfMonth();
        $currentMonth = $now->copy()->startOfMonth();
        if ($monthStart->greaterThan($currentMonth)) {
            $monthStart = $currentMonth;
        }

        $start = $monthStart->copy()->startOfDay();
        $end = $monthStart->isSameMonth($now)
            ? $now->copy()->startOfDay()
            : $monthStart->copy()->endOfMonth()->startOfDay();

        return [
            $monthStart->format('Y-m'),
            $start,
            $end,
            $monthStart->copy()->locale('id')->translatedFormat('F Y'),
        ];
    }

    /** @param array<string, mixed>|null $report
     *  @return array<string, mixed>
     */
    private function presentReport(?array $report, string $periodLabel): array
    {
        $report ??= [];
        $report['period'] = array_merge((array) ($report['period'] ?? []), ['label' => $periodLabel]);
        $report['summary'] = array_merge([
            'total_hari' => 0, 'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alfa' => 0, 'persentase' => 0,
        ], (array) ($report['summary'] ?? []));
        $report['rows'] = collect($report['rows'] ?? [])
            ->filter(fn ($row): bool => is_array($row))
            ->map(function (array $row): array {
                return [
                    'tanggal' => trim((string) ($row['tanggal'] ?? '')),
                    'jam_masuk' => trim((string) ($row['jam_masuk'] ?? '')),
                    'jam_pulang' => trim((string) ($row['jam_pulang'] ?? '')),
                    'status' => trim((string) ($row['status'] ?? '')),
                    'status_label' => $this->statusLabel((string) ($row['status'] ?? '')),
                    'keterangan' => trim((string) ($row['keterangan'] ?? '')),
                ];
            })
            ->values()
            ->all();
        $report['synced_at_label'] = filled($report['synced_at'] ?? null)
            ? Carbon::parse((string) $report['synced_at'])->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i').' WIB'
            : '-';

        return $report;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'hadir' => 'Hadir',
            'hadir_terlambat' => 'Hadir terlambat',
            'hadir_izin_terlambat' => 'Hadir - izin terlambat',
            'izin' => 'Izin',
            'sakit' => 'Sakit',
            'alfa' => 'Alfa',
            'libur' => 'Libur',
            'libur_override' => 'Libur khusus',
            default => 'Tidak diketahui',
        };
    }
}
