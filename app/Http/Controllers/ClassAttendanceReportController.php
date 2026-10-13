<?php

namespace App\Http\Controllers;

use App\Services\ClassAttendanceReportService;
use App\Services\Exports\ClassAttendanceReportPdfExporter;
use App\Services\Exports\ClassAttendanceReportXlsxExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClassAttendanceReportController extends Controller
{
    public function __construct(private readonly ClassAttendanceReportService $reports) {}

    public function preview(Request $request)
    {
        $report = $this->report($request);

        return response()->json($this->reports->preview($report))->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, string $format, ClassAttendanceReportXlsxExporter $xlsx, ClassAttendanceReportPdfExporter $pdf)
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $report = $this->report($request);
        $scope = $report['all_classes'] ? 'semua-kelas' : $report['classes'][0]['name'];
        $filename = Str::slug('presensi-'.$scope.'-'.$report['period']['start'].'-'.$report['period']['end']);
        if ($format === 'xlsx') {
            $content = $xlsx->export($report);

            return response($content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.xlsx"',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return response($pdf->export($report), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function report(Request $request): array
    {
        $adminContext = $request->routeIs('admin.class-attendance-report.*');
        $user = $request->user($adminContext ? 'admin' : 'web');
        abort_unless($user, 403);

        return $this->reports->build($user, $request->query(), $adminContext);
    }
}
