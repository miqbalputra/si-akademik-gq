<?php

namespace App\Http\Controllers;

use App\Services\DiniyyahJournalReportService;
use App\Services\Exports\DiniyyahJournalReportXlsxExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DiniyyahJournalReportController extends Controller
{
    public function guru(Request $request, DiniyyahJournalReportService $service)
    {
        $teacher = $request->user()->teacher;
        abort_unless($teacher, 403, 'Akun Anda belum terhubung dengan data Guru.');

        $filters = $this->validatedFilters($request, false);
        $report = $service->build($filters, $teacher->id);
        $report['filter_labels']['Guru'] = $teacher->name;
        $terms = $service->academicTerms();

        return view('guru.diniyyah-journals.report', compact('teacher', 'report', 'terms'));
    }

    public function guruExport(
        Request $request,
        string $format,
        DiniyyahJournalReportService $service,
    ) {
        $teacher = $request->user()->teacher;
        abort_unless($teacher, 403, 'Akun Anda belum terhubung dengan data Guru.');

        $filters = $this->validatedFilters($request, false);
        $report = $service->build($filters, $teacher->id);
        $report['filter_labels']['Guru'] = $teacher->name;
        $title = 'Laporan Jurnal Saya - '.$teacher->name;

        return $this->download($report, $format, $title, 'guru');
    }

    public function management(Request $request, DiniyyahJournalReportService $service)
    {
        $this->authorizeManagement($request);

        $filters = $this->validatedFilters($request, true);
        $report = $service->build($filters);
        $options = $service->options();

        return view('admin.diniyyah-journals.report', compact('report', 'options'));
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless(
            $request->user()?->hasAnyRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']),
            403,
        );
    }

    /** @return array<string, mixed> */
    private function validatedFilters(Request $request, bool $management): array
    {
        $rules = [
            'academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
            'date_from' => ['nullable', 'date'],
            'date_until' => ['nullable', 'date', 'after_or_equal:date_from'],
            'type' => ['nullable', 'in:regular,substitute'],
            'search' => ['nullable', 'string', 'max:120'],
        ];

        if ($management) {
            $rules += [
                'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
                'classroom_term_id' => ['nullable', 'integer', 'exists:classroom_terms,id'],
                'subject_id' => ['nullable', 'integer', 'exists:diniyyah_subjects,id'],
            ];
        }

        return $request->validate($rules);
    }

    private function download(array $report, string $format, string $title, string $scope)
    {
        $safeTitle = Str::slug($title);
        $dateLabel = now()->format('Ymd-His');

        if ($format === 'xlsx') {
            $content = app(DiniyyahJournalReportXlsxExporter::class)->export($report, $scope);

            return response($content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$safeTitle.'-'.$dateLabel.'.xlsx"',
                'Content-Length' => strlen($content),
            ]);
        }

        abort_unless($format === 'pdf', 404);

        return Pdf::loadView('reports.diniyyah-journal', [
            'report' => $report,
            'title' => $title,
            'scope' => $scope,
        ])
            ->setPaper('a3', 'landscape')
            ->download($safeTitle.'-'.$dateLabel.'.pdf');
    }
}
