<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Services\AdminMonthlyJpReportService;
use App\Services\Exports\AdminMonthlyJpReportXlsxExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class AdminMonthlyJpReportController extends Controller
{
    public function index(Request $request, AdminMonthlyJpReportService $service)
    {
        $this->authorize($request);
        $report = $service->build($this->termId($request), $this->month($request), $this->year($request));
        $terms = AcademicTerm::with('academicYear')->orderByDesc('starts_at')->get();

        return view('admin.monthly-jp-report.index', compact('report', 'terms'));
    }

    public function export(Request $request, string $format, AdminMonthlyJpReportService $service, AdminMonthlyJpReportXlsxExporter $xlsx)
    {
        $this->authorize($request);
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $report = $service->build($this->termId($request), $this->month($request), $this->year($request));
        $file = 'rekap-penggajian-jp-'.$report['year'].'-'.str_pad((string) $report['month'], 2, '0', STR_PAD_LEFT);
        if ($format === 'pdf') {
            return Pdf::loadView('admin.monthly-jp-report.pdf', compact('report'))->setPaper('a3', 'landscape')->download($file.'.pdf');
        }
        $content = $xlsx->export($report);

        return response($content, 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition' => 'attachment; filename="'.$file.'.xlsx"', 'Content-Length' => strlen($content)]);
    }

    private function authorize(Request $request): void
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']), 403);
    }

    private function termId(Request $request): ?int
    {
        $request->validate(['academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id']]);

        return $request->integer('academic_term_id') ?: AcademicTerm::where('is_active', true)->value('id');
    }

    private function month(Request $request): int
    {
        return max(1, min(12, $request->integer('month', now('Asia/Jakarta')->month)));
    }

    private function year(Request $request): int
    {
        return max(2020, min(2100, $request->integer('year', now('Asia/Jakarta')->year)));
    }
}
