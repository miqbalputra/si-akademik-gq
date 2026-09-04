<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Services\AdminMonthlyJpPdfRenderer;
use App\Services\AdminMonthlyJpReportService;
use App\Services\Exports\AdminMonthlyJpReportXlsxExporter;
use App\Services\TafsirJournalAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AdminMonthlyJpReportController extends Controller
{
    public function index(Request $request, AdminMonthlyJpReportService $service, TafsirJournalAuditService $tafsirAudit)
    {
        $this->authorize($request);
        $report = $service->build($this->termId($request), $this->month($request), $this->year($request));
        $terms = AcademicTerm::with('academicYear')->orderByDesc('starts_at')->get();

        $tafsirAuditRows = $tafsirAudit->candidates($report['term']->id, $report['start'], $report['end']);

        return view('admin.monthly-jp-report.index', compact('report', 'terms', 'tafsirAuditRows'));
    }

    public function export(Request $request, string $format, AdminMonthlyJpReportService $service, AdminMonthlyJpReportXlsxExporter $xlsx, AdminMonthlyJpPdfRenderer $pdf)
    {
        $this->authorize($request);
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $report = $service->build($this->termId($request), $this->month($request), $this->year($request));
        $file = 'rekap-penggajian-jp-'.$report['year'].'-'.str_pad((string) $report['month'], 2, '0', STR_PAD_LEFT);
        if ($format === 'pdf') {
            try {
                $content = $pdf->render($report);

                return response($content, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="'.$file.'.pdf"',
                    'Content-Length' => (string) strlen($content),
                ]);
            } catch (Throwable $exception) {
                Log::error('Admin monthly JP PDF export failed.', [
                    'academic_term_id' => $report['term']->id,
                    'month' => $report['month'],
                    'year' => $report['year'],
                    'teacher_count' => $report['teachers']->count(),
                    'realized_count' => $report['realized']->count(),
                    'missing_count' => $report['missing']->count(),
                    'exception' => $exception,
                ]);

                return back()->withErrors(['pdf' => 'PDF belum dapat dibuat. Detail gangguan telah dicatat; silakan gunakan Excel sementara atau hubungi admin aplikasi.']);
            }
        }
        $content = $xlsx->export($report);

        return response($content, 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition' => 'attachment; filename="'.$file.'.xlsx"', 'Content-Length' => strlen($content)]);
    }

    public function normalizeTafsir(Request $request, TafsirJournalAuditService $tafsirAudit)
    {
        $this->authorize($request);
        $data = $request->validate([
            'academic_term_id' => ['required', 'integer', 'exists:academic_terms,id'],
            'date' => ['required', 'date'],
            'schedule_id' => ['required', 'integer', 'exists:diniyyah_teaching_schedules,id'],
        ]);

        try {
            $tafsirAudit->normalize((int) $data['academic_term_id'], $data['date'], (int) $data['schedule_id'], $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['tafsir_audit' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['tafsir_audit' => 'Normalisasi belum dapat disimpan. Silakan coba lagi atau hubungi admin aplikasi.']);
        }

        return back()->with('success', 'Jurnal Tafsir terpilih telah dinormalisasi dan riwayat audit tersimpan.');
    }

    public function revertTafsirNormalization(Request $request, TafsirJournalAuditService $tafsirAudit)
    {
        $this->authorize($request);
        $data = $request->validate([
            'academic_term_id' => ['required', 'integer', 'exists:academic_terms,id'],
            'date' => ['required', 'date'],
            'schedule_id' => ['required', 'integer', 'exists:diniyyah_teaching_schedules,id'],
        ]);

        try {
            $tafsirAudit->revert((int) $data['academic_term_id'], $data['date'], (int) $data['schedule_id'], $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['tafsir_audit' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['tafsir_audit' => 'Pemulihan belum dapat disimpan. Silakan coba lagi atau hubungi admin aplikasi.']);
        }

        return back()->with('success', 'Penanda jurnal telah dipulihkan ke sesi asal. Riwayat normalisasi tetap tersimpan.');
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
