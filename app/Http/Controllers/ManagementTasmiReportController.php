<?php

namespace App\Http\Controllers;

use App\Models\ScoreChangeLog;
use App\Models\TasmiRecord;
use App\Services\TasmiReportDownloadService;
use App\Services\TasmiReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ManagementTasmiReportController extends Controller
{
    public function __construct(
        private readonly TasmiReportService $reportService,
        private readonly TasmiReportDownloadService $downloadService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeManagement($request);
        $filters = $this->validatedFilters($request);
        $baseQuery = $this->reportService->forManagement();
        $options = $this->reportService->options($baseQuery, true);
        $report = $this->reportService->paginate($this->reportService->applyFilters($baseQuery, $filters));

        return view('guru.tasmi.report', [
            'report' => $report,
            'options' => $options,
            'filters' => $filters,
            'scope' => 'management',
            'pageTitle' => "Laporan Pengawasan Tasmi'",
            'pageDescription' => "Seluruh hasil Tasmi' dari semua PJ, kelas, dan semester.",
            'backUrl' => route('kabag-tahfidz.dashboard'),
            'backLabel' => 'Dashboard Kabag Tahfidz',
            'exportRoute' => 'admin.tasmi-report.export',
            'resetRoute' => 'admin.tasmi-report.index',
            'canEdit' => false,
            'portalLabel' => 'Portal Kabag Tahfidz',
        ]);
    }

    public function show(Request $request, TasmiRecord $tasmi_record): View
    {
        $this->authorizeManagement($request);
        $record = $this->reportService->forManagement()->whereKey($tasmi_record->id)->firstOrFail();
        $auditLogs = ScoreChangeLog::query()
            ->with('changer')
            ->where('score_table', $record->getTable())
            ->where('score_id', $record->id)
            ->latest('changed_at')
            ->get();

        return view('guru.tasmi.detail', [
            'record' => $record,
            'auditLogs' => $auditLogs,
            'backUrl' => route('admin.tasmi-report.index'),
            'backLabel' => "Laporan Tasmi'",
            'portalLabel' => 'Portal Kabag Tahfidz',
            'breadcrumb' => "Detail Laporan Tasmi'",
            'readOnly' => true,
        ]);
    }

    public function export(Request $request, string $format)
    {
        $this->authorizeManagement($request);
        $filters = $this->validatedFilters($request);
        $baseQuery = $this->reportService->forManagement();
        $options = $this->reportService->options($baseQuery, true);
        $report = $this->reportService->exportReport(
            $this->reportService->applyFilters($baseQuery, $filters),
            $filters,
            $options,
        );

        return $this->downloadService->download($report, $format, "Laporan Tasmi' Semua PJ", 'management');
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'kabag_tahfidz']), 403);
    }

    /** @return array<string, mixed> */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
            'classroom_term_id' => ['nullable', 'integer', 'exists:classroom_terms,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'examiner_teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'exam_type' => ['nullable', Rule::in(array_keys(TasmiRecord::examTypeOptions()))],
            'juz' => ['nullable', 'integer', 'min:1', 'max:30'],
            'predicate' => ['nullable', Rule::in(array_keys(TasmiRecord::predicateOptions()))],
            'date_from' => ['nullable', 'date'],
            'date_until' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);
    }
}
