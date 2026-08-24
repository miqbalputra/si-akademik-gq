<?php

namespace App\Services;

use App\Models\Teacher;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Server-to-server client for a single teacher's canonical GeoPresensi report.
 *
 * The browser never receives the integration key.  Callers must provide the
 * local Teacher resolved from the authenticated Edu account; this class only
 * uses its NIY as the external identity.
 */
class AttendanceTeacherReportClient
{
    /**
     * @return array{ok: bool, code: string, message: string, report: ?array<string, mixed>}
     */
    public function reportForTeacher(
        Teacher $teacher,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        bool $refresh = false,
    ): array {
        if (! (bool) config('services.attendance_journal.enabled', false)) {
            return $this->failure('integration_disabled', 'Integrasi GeoPresensi belum diaktifkan oleh admin.');
        }

        $idGuru = trim((string) $teacher->niy);
        if ($idGuru === '') {
            return $this->failure('mapping_missing', 'NIY Anda belum terhubung ke GeoPresensi. Hubungi admin untuk melengkapi data.');
        }

        $start = $startDate->copy()->setTimezone('Asia/Jakarta')->toDateString();
        $end = $endDate->copy()->setTimezone('Asia/Jakarta')->toDateString();
        $cacheKey = 'attendance-teacher-report:v1:'.sha1($idGuru.'|'.$start.'|'.$end);

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        try {
            /** @var array{ok: bool, code: string, message: string, report: ?array<string, mixed>} $result */
            $result = Cache::remember(
                $cacheKey,
                now()->addSeconds(max(1, (int) config('services.attendance_journal.cache_seconds', 60))),
                function () use ($idGuru, $start, $end): array {
                    $baseUrl = rtrim((string) config('services.attendance_journal.base_url'), '/');
                    $apiKey = trim((string) config('services.attendance_journal.api_key'));

                    if ($baseUrl === '' || $apiKey === '') {
                        return $this->failure('integration_unconfigured', 'Konfigurasi GeoPresensi belum lengkap.');
                    }

                    $response = Http::acceptJson()
                        ->withHeaders(['X-API-Key' => $apiKey])
                        ->timeout(max(1, (int) config('services.attendance_journal.timeout', 5)))
                        ->get($baseUrl.'/api/v1/integrations/journal/teacher-report', [
                            'id_guru' => $idGuru,
                            'start_date' => $start,
                            'end_date' => $end,
                        ]);

                    if ($response->status() === 404) {
                        return $this->failure('mapping_not_found', 'NIY Anda tidak ditemukan sebagai guru aktif di GeoPresensi. Hubungi admin.');
                    }

                    if (! $response->successful()) {
                        throw new \RuntimeException('GeoPresensi returned HTTP '.$response->status().'.');
                    }

                    $payload = $response->json();
                    $report = is_array($payload) ? ($payload['data'] ?? null) : null;
                    if (($payload['success'] ?? false) !== true || ! is_array($report)
                        || ! is_array($report['teacher'] ?? null)
                        || trim((string) ($report['teacher']['id_guru'] ?? '')) !== $idGuru
                        || ! is_array($report['summary'] ?? null)
                        || ! is_array($report['rows'] ?? null)) {
                        throw new \RuntimeException('GeoPresensi returned an invalid teacher report payload.');
                    }

                    return [
                        'ok' => true,
                        'code' => 'ok',
                        'message' => 'Data presensi berhasil disinkronkan.',
                        'report' => $report,
                    ];
                },
            );

            return $result;
        } catch (Throwable $exception) {
            Log::warning('GeoPresensi teacher report unavailable.', [
                'teacher_id' => $teacher->id,
                'start_date' => $start,
                'end_date' => $end,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->failure('source_unavailable', 'GeoPresensi belum dapat dihubungi. Silakan coba lagi beberapa saat.');
        }
    }

    /** @return array{ok: false, code: string, message: string, report: null} */
    private function failure(string $code, string $message): array
    {
        return ['ok' => false, 'code' => $code, 'message' => $message, 'report' => null];
    }
}
