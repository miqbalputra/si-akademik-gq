<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Services\JournalReminderImageRenderer;
use App\Services\JournalReminderPdfRenderer;
use App\Services\JournalReminderReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class JournalReminderReportController extends Controller
{
    public function index(Request $request, JournalReminderReportService $service)
    {
        $this->authorize($request);
        $filters = $this->filters($request);
        $report = $service->build($filters['term']->id, $filters['start'], $filters['end']);
        $terms = AcademicTerm::query()->with('academicYear')->orderByDesc('starts_at')->get();

        return view('admin.journal-reminders.index', compact('filters', 'report', 'terms'));
    }

    public function export(Request $request, string $format, JournalReminderReportService $service, JournalReminderPdfRenderer $pdf, JournalReminderImageRenderer $image)
    {
        $this->authorize($request);
        abort_unless(in_array($format, ['pdf', 'png', 'jpg'], true), 404);
        $filters = $this->filters($request);
        $report = $service->build($filters['term']->id, $filters['start'], $filters['end']);
        $teacherId = $request->validate([
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
        ])['teacher_id'] ?? null;
        if ($teacherId) {
            $report = $service->forTeacher($report, (int) $teacherId) ?? abort(404);
        }
        $filename = $this->filename($report, $teacherId ? (int) $teacherId : null);

        try {
            if ($format === 'pdf') {
                $content = $pdf->render($report);

                return response($content, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
                    'Content-Length' => (string) strlen($content),
                ]);
            }

            $content = $image->render($report, $format);

            return response($content, 200, [
                'Content-Type' => $format === 'jpg' ? 'image/jpeg' : 'image/png',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.'.$format.'"',
                'Content-Length' => (string) strlen($content),
            ]);
        } catch (Throwable $exception) {
            Log::error('Journal reminder export failed.', [
                'format' => $format,
                'academic_term_id' => $report['term']->id,
                'start' => $report['start']->toDateString(),
                'end' => $report['end']->toDateString(),
                'exception' => $exception,
            ]);

            return back()->withErrors(['export' => $exception->getMessage() ?: 'File pengingat belum dapat dibuat. Silakan coba lagi.']);
        }
    }

    /** @return array{term: AcademicTerm, start: Carbon, end: Carbon} */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
            'date_from' => ['nullable', 'date'],
            'date_until' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $term = isset($data['academic_term_id'])
            ? AcademicTerm::with('academicYear')->findOrFail($data['academic_term_id'])
            : AcademicTerm::with('academicYear')->where('is_active', true)->firstOrFail();
        $today = now('Asia/Jakarta')->startOfDay();
        $minimum = $term->starts_at?->copy()->startOfDay();
        $maximum = $term->ends_at?->copy()->startOfDay() ?? $today;
        if ($maximum->gt($today)) {
            $maximum = $today;
        }

        $defaultStart = $today->copy()->startOfMonth();
        if ($minimum && $defaultStart->lt($minimum)) {
            $defaultStart = $minimum->copy();
        }
        if ($defaultStart->gt($maximum)) {
            $defaultStart = $minimum?->copy() ?? $maximum->copy();
        }
        $start = isset($data['date_from']) ? Carbon::parse($data['date_from'], 'Asia/Jakarta')->startOfDay() : $defaultStart;
        $end = isset($data['date_until']) ? Carbon::parse($data['date_until'], 'Asia/Jakarta')->startOfDay() : $maximum->copy();

        if ($minimum && $start->lt($minimum)) {
            $start = $minimum->copy();
        }
        if ($end->gt($maximum)) {
            $end = $maximum->copy();
        }
        if ($start->gt($end)) {
            throw ValidationException::withMessages([
                'date_from' => 'Rentang tanggal harus berada dalam periode ajaran dan tidak boleh melampaui hari ini.',
            ]);
        }

        return compact('term', 'start', 'end');
    }

    private function authorize(Request $request): void
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'kabag_diniyyah']), 403);
    }

    /** @param array<string, mixed> $report */
    private function filename(array $report, ?int $teacherId = null): string
    {
        $name = 'pengingat-jurnal-kbm';
        if ($teacherId) {
            $teacher = $report['teachers']->first();
            $name .= '-'.($teacher['teacher_name'] ?? 'guru-'.$teacherId).'-'.($teacher['niy'] ?? $teacherId);
        }

        return Str::slug($name.'-'.$report['start']->format('Ymd').'-'.$report['end']->format('Ymd'));
    }
}
