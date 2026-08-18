<?php

namespace App\Http\Controllers;

use App\Models\HomeroomAssignment;
use App\Models\PanelNotification;
use App\Models\ScoreChangeLog;
use App\Models\TasmiRecord;
use App\Models\Teacher;
use App\Services\TasmiReportDownloadService;
use App\Services\TasmiReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WaliKelasTasmiController extends Controller
{
    public function __construct(
        private readonly TasmiReportService $reportService,
        private readonly TasmiReportDownloadService $downloadService,
    ) {}

    /**
     * Tampilkan data tasmi' untuk santri di kelas yang diwalikan guru ini.
     * Read-only — wali kelas hanya melihat, tidak bisa input/edit.
     */
    public function index(Request $request): View
    {
        $teacher = $this->waliTeacher($request);
        $filters = $this->validatedReportFilters($request);
        $baseQuery = $this->reportService->forHomeroom($teacher);
        $options = $this->reportService->options($baseQuery);
        $report = $this->reportService->paginate($this->reportService->applyFilters($baseQuery, $filters));

        return view('guru.tasmi.report', [
            'report' => $report,
            'options' => $options,
            'filters' => $filters,
            'scope' => 'homeroom',
            'pageTitle' => "Hasil Tasmi' Kelas Saya",
            'pageDescription' => 'Hasil Tasmi\' santri pada kelas yang Anda ampuh pada tanggal ujian.',
            'backUrl' => route('guru.dashboard'),
            'backLabel' => 'Dashboard Guru',
            'exportRoute' => 'guru.tasmi-wali.export',
            'resetRoute' => 'guru.tasmi-wali.index',
            'canEdit' => false,
        ]);
    }

    public function show(Request $request, TasmiRecord $tasmi_record): View
    {
        $teacher = $this->waliTeacher($request);
        $record = $this->reportService->forHomeroom($teacher)
            ->whereKey($tasmi_record->id)
            ->firstOrFail();

        PanelNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('notification_type', ['tasmi_created', 'tasmi_updated'])
            ->where('link_url', route('guru.tasmi-wali.show', $record))
            ->where('status', 'unread')
            ->update([
                'status' => 'read',
                'read_at' => now(),
            ]);

        return view('guru.tasmi.detail', [
            'record' => $record,
            'auditLogs' => $this->auditLogs($record),
            'backUrl' => route('guru.tasmi-wali.index'),
            'backLabel' => "Hasil Tasmi' Kelas Saya",
            'portalLabel' => 'Portal Guru',
            'breadcrumb' => "Detail Hasil Tasmi'",
            'readOnly' => true,
        ]);
    }

    public function export(Request $request, string $format)
    {
        $teacher = $this->waliTeacher($request);
        $filters = $this->validatedReportFilters($request);
        $baseQuery = $this->reportService->forHomeroom($teacher);
        $options = $this->reportService->options($baseQuery);
        $report = $this->reportService->exportReport(
            $this->reportService->applyFilters($baseQuery, $filters),
            $filters,
            $options,
        );
        $report['filter_labels']['Wali Kelas'] = $teacher->name;

        return $this->downloadService->download($report, $format, "Laporan Tasmi' Kelas - {$teacher->name}", 'homeroom');
    }

    private function waliTeacher(Request $request): Teacher
    {
        $user = $request->user();
        $teacher = $user?->teacher;
        abort_unless($user?->hasRole('guru') && $teacher, 403, 'Akun Anda belum terhubung dengan data Guru.');
        abort_unless(HomeroomAssignment::query()->where('teacher_id', $teacher->id)->exists(), 403, 'Anda tidak memiliki penugasan wali kelas.');

        return $teacher;
    }

    /** @return array<string, mixed> */
    private function validatedReportFilters(Request $request): array
    {
        return $request->validate([
            'academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
            'classroom_term_id' => ['nullable', 'integer', 'exists:classroom_terms,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'exam_type' => ['nullable', Rule::in(array_keys(TasmiRecord::examTypeOptions()))],
            'juz' => ['nullable', 'integer', 'min:1', 'max:30'],
            'predicate' => ['nullable', Rule::in(array_keys(TasmiRecord::predicateOptions()))],
            'date_from' => ['nullable', 'date'],
            'date_until' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);
    }

    private function auditLogs(TasmiRecord $record)
    {
        return ScoreChangeLog::query()
            ->with('changer')
            ->where('score_table', $record->getTable())
            ->where('score_id', $record->id)
            ->latest('changed_at')
            ->get();
    }
}
