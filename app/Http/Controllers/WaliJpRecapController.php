<?php

namespace App\Http\Controllers;

use App\Models\ClassroomTerm;
use App\Models\HomeroomAssignment;
use App\Models\HomeroomMonthlyJpConfirmation;
use App\Services\Exports\WaliJpRecapXlsxExporter;
use App\Services\WaliJpRecapService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WaliJpRecapController extends Controller
{
    public function __construct(
        private readonly WaliClassJournalMonitoringController $monitoring,
        private readonly WaliJpRecapService $recapService,
    ) {}

    public function index(Request $request)
    {
        return view('wali.jp-recap.index', $this->data($request));
    }

    public function exportPdf(Request $request)
    {
        $data = $this->data($request);

        return Pdf::loadView('wali.jp-recap.export-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download($this->filename($data, 'pdf'));
    }

    public function exportExcel(Request $request, WaliJpRecapXlsxExporter $exporter)
    {
        $data = $this->data($request);
        $content = $exporter->export($data);

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($data, 'xlsx').'"',
            'Content-Length' => strlen($content),
        ]);
    }

    public function confirm(Request $request)
    {
        $validated = $request->validate([
            'classroom_term_id' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2020,2100'],
            'teacher_id' => ['required', 'integer'],
            'mode' => ['required', 'in:normal,override'],
            'override_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $data = $this->data($request);
        $row = collect($data['recap']['teachers'])->firstWhere('teacher_id', (int) $validated['teacher_id']);
        abort_unless($row, 404);

        if ($validated['mode'] === 'normal') {
            abort_unless((int) $row['missing_count'] === 0, 422, 'Masih ada jurnal kosong. Gunakan override dan jelaskan alasannya.');
        } else {
            $request->validate(['override_reason' => ['required', 'string', 'max:2000']]);
        }

        HomeroomMonthlyJpConfirmation::updateOrCreate(
            [
                'classroom_term_id' => $data['classroomTerm']->id,
                'homeroom_teacher_id' => $data['teacher']->id,
                'teacher_id' => $row['teacher_id'],
                'period_start' => $data['periodStart']->toDateString(),
            ],
            [
                'confirmed_jp' => $row['jp_terealisasi'],
                'review_signature' => $row['review_signature'],
                'is_override' => $validated['mode'] === 'override',
                'override_reason' => $validated['mode'] === 'override' ? trim((string) $validated['override_reason']) : null,
                'confirmed_at' => now(),
            ]
        );

        return redirect()->route('wali.jp-recap.index', $this->query($data))
            ->with('success', 'Status rekap JP guru berhasil disimpan.');
    }

    /** @return array<string, mixed> */
    private function data(Request $request): array
    {
        $teacher = $request->user()?->teacher;
        abort_unless($teacher, 403, 'Akun Anda tidak terhubung dengan data guru.');

        $month = max(1, min(12, (int) $request->input('month', now('Asia/Jakarta')->month)));
        $year = max(2020, min(2100, (int) $request->input('year', now('Asia/Jakarta')->year)));
        $periodStart = Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta')->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $classroomTerms = ClassroomTerm::query()
            ->with('classroom')
            ->whereIn('id', HomeroomAssignment::query()
                ->where('teacher_id', $teacher->id)
                ->where(fn ($query) => $query->whereNull('starts_at')->orWhereDate('starts_at', '<=', $periodEnd->toDateString()))
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', $periodStart->toDateString()))
                ->select('classroom_term_id'))
            ->orderBy('name')
            ->get();
        abort_unless($classroomTerms->isNotEmpty(), 403, 'Anda tidak memiliki penugasan wali kelas pada periode ini.');

        $classroomTerm = $classroomTerms->firstWhere('id', (int) $request->input('classroom_term_id'))
            ?? $classroomTerms->first();
        abort_unless($classroomTerm, 403);

        // Reuse hasil pemantauan yang telah menjadi sumber kebenaran status slot jurnal.
        $request->merge([
            'month' => $month,
            'year' => $year,
            'classroom_term_id' => $classroomTerm->id,
        ]);
        $monitoring = $this->monitoring->monitoringData($request);
        $recap = $this->recapService->build($teacher, $classroomTerm, $periodStart, $monitoring['monitoringRows']);

        return compact('teacher', 'month', 'year', 'periodStart', 'classroomTerms', 'classroomTerm', 'recap');
    }

    /** @param array<string, mixed> $data */
    private function filename(array $data, string $extension): string
    {
        return 'rekap-jp-'.str($data['classroomTerm']->name)->slug().'-'.$data['periodStart']->format('Y-m').'.'.$extension;
    }

    /** @param array<string, mixed> $data @return array<string, int> */
    private function query(array $data): array
    {
        return [
            'classroom_term_id' => $data['classroomTerm']->id,
            'month' => $data['month'],
            'year' => $data['year'],
        ];
    }
}
