<?php

namespace App\Services;

use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Presents a safe, cached view of the GeoPresensi integration and the local
 * teachers' NIY mappings. API credentials never leave this service.
 */
class AttendanceIntegrationStatusService
{
    public const CACHE_KEY = 'attendance-integration-status:v1';

    private const ACTIVE_STATUS = 'active';

    /**
     * @return array{
     *   connection: array<string, mixed>,
     *   summary: array<string, int>,
     *   teachers: list<array<string, mixed>>,
     * }
     */
    public function audit(bool $force = false): array
    {
        if ($force) {
            $this->forget();
        }

        $ttl = max(1, min(60, (int) config('services.attendance_journal.cache_seconds', 60)));

        return Cache::remember(self::CACHE_KEY, now()->addSeconds($ttl), fn (): array => $this->buildAudit());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    public function statusForTeacher(Teacher $teacher): array
    {
        if ((string) $teacher->status !== self::ACTIVE_STATUS) {
            return [
                'key' => 'inactive',
                'label' => 'Tidak aktif',
                'color' => 'gray',
                'reason' => 'Guru tidak aktif tidak ikut verifikasi mapping.',
            ];
        }

        foreach ($this->audit()['teachers'] as $row) {
            if ((int) ($row['teacher_id'] ?? 0) === (int) $teacher->id) {
                return $row;
            }
        }

        return [
            'key' => 'unverified',
            'label' => 'Belum dapat diverifikasi',
            'color' => 'warning',
            'reason' => 'Status integrasi belum tersedia.',
        ];
    }

    public function badgeLabel(Teacher $teacher): string
    {
        return (string) ($this->statusForTeacher($teacher)['label'] ?? 'Belum dapat diverifikasi');
    }

    public function badgeColor(Teacher $teacher): string
    {
        return (string) ($this->statusForTeacher($teacher)['color'] ?? 'warning');
    }

    /** @return array<string, mixed> */
    private function buildAudit(): array
    {
        $teachers = Teacher::query()
            ->with('user:id,name,username,email')
            ->where('status', self::ACTIVE_STATUS)
            ->orderBy('name')
            ->get();

        $baseRows = $teachers->map(function (Teacher $teacher): array {
            $niy = trim((string) $teacher->niy);

            return [
                'teacher_id' => (int) $teacher->id,
                'name' => (string) $teacher->name,
                'account_name' => (string) ($teacher->user?->name ?? '-'),
                'username' => (string) ($teacher->user?->username ?? ''),
                'email' => (string) ($teacher->user?->email ?? ''),
                'niy' => $niy,
                'key' => null,
                'label' => null,
                'color' => null,
                'reason' => null,
            ];
        })->values();

        $connection = $this->connectionDefaults();
        $validRows = $baseRows->filter(fn (array $row): bool => $row['niy'] !== '');
        $duplicateNiy = $validRows
            ->groupBy('niy')
            ->filter(fn ($group): bool => $group->count() > 1)
            ->keys();

        $rows = $baseRows->map(function (array $row) use ($duplicateNiy): array {
            if ($row['niy'] === '') {
                return $this->withStatus($row, 'missing', 'NIY belum diisi', 'danger', 'Isi NIY dengan nilai users.id_guru di GeoPresensi.');
            }

            if ($duplicateNiy->contains($row['niy'])) {
                return $this->withStatus($row, 'duplicate', 'NIY duplikat', 'danger', 'Satu NIY hanya boleh dipakai satu guru.');
            }

            return $this->withStatus($row, 'unverified', 'Belum dapat diverifikasi', 'warning', 'Mapping belum diverifikasi ke GeoPresensi.');
        });

        $remoteIds = [];
        $apiEligibleNiy = $validRows
            ->reject(fn (array $row): bool => $duplicateNiy->contains($row['niy']))
            ->pluck('niy')
            ->unique()
            ->values()
            ->all();

        if (! (bool) config('services.attendance_journal.enabled', false)) {
            $connection['key'] = 'disabled';
            $connection['label'] = 'Integrasi nonaktif';
            $connection['message'] = 'Aktifkan ATTENDANCE_JOURNAL_INTEGRATION_ENABLED untuk melakukan verifikasi.';
        } elseif (trim((string) config('services.attendance_journal.base_url')) === ''
            || trim((string) config('services.attendance_journal.api_key')) === '') {
            $connection['key'] = 'incomplete';
            $connection['label'] = 'Konfigurasi belum lengkap';
            $connection['message'] = 'Base URL dan API key server harus diisi.';
        } else {
            [$remoteIds, $connection] = $this->fetchRemoteIds($apiEligibleNiy);
        }

        $rows = $rows->map(function (array $row) use ($remoteIds, $connection, $duplicateNiy): array {
            if ($row['niy'] === '' || $duplicateNiy->contains($row['niy'])) {
                return $row;
            }

            if (($connection['key'] ?? '') !== 'connected') {
                return $this->withStatus(
                    $row,
                    'unverified',
                    'Belum dapat diverifikasi',
                    'warning',
                    (string) ($connection['message'] ?? 'API GeoPresensi belum dapat dihubungi.'),
                );
            }

            if (in_array($row['niy'], $remoteIds, true)) {
                return $this->withStatus($row, 'connected', 'Terhubung', 'success', 'NIY ditemukan di GeoPresensi.');
            }

            return $this->withStatus($row, 'not_found', 'Tidak ditemukan di GeoPresensi', 'danger', 'Periksa kesesuaian teachers.niy dan users.id_guru.');
        });

        $counts = [
            'total_active' => $rows->count(),
            'connected' => $rows->where('key', 'connected')->count(),
            'missing_niy' => $rows->where('key', 'missing')->count(),
            'duplicate_niy' => $rows->where('key', 'duplicate')->count(),
            'not_found' => $rows->where('key', 'not_found')->count(),
            'unverified' => $rows->where('key', 'unverified')->count(),
        ];

        return [
            'connection' => $connection,
            'summary' => $counts,
            'teachers' => $rows->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function connectionDefaults(): array
    {
        return [
            'key' => 'unknown',
            'label' => 'Belum dicek',
            'message' => 'Belum ada pemeriksaan koneksi.',
            'base_url' => rtrim((string) config('services.attendance_journal.base_url'), '/'),
            'checked_at' => null,
            'latency_ms' => null,
        ];
    }

    /** @return array{0: list<string>, 1: array<string, mixed>} */
    private function fetchRemoteIds(array $niyValues): array
    {
        $idsToVerify = $niyValues !== [] ? $niyValues : ['__healthcheck__'];
        $remoteIds = [];
        $started = microtime(true);
        $timeout = max(1, (int) config('services.attendance_journal.timeout', 5));
        $baseUrl = rtrim((string) config('services.attendance_journal.base_url'), '/');
        $apiKey = trim((string) config('services.attendance_journal.api_key'));

        try {
            foreach (array_chunk($idsToVerify, 500) as $chunk) {
                $response = Http::acceptJson()
                    ->withHeaders(['X-API-Key' => $apiKey])
                    ->timeout($timeout)
                    ->get($baseUrl.'/api/v1/integrations/journal/teachers', [
                        'teacher_ids' => implode(',', $chunk),
                    ]);

                if (! $response->successful()) {
                    throw new \RuntimeException('HTTP '.$response->status());
                }

                $payload = $response->json();
                if (! is_array($payload) || ($payload['success'] ?? false) !== true || ! is_array($payload['data'] ?? null)) {
                    throw new \RuntimeException('Respons API tidak valid');
                }

                foreach ($payload['data'] as $row) {
                    if (! is_array($row) || ! array_key_exists('id_guru', $row) || ! is_string($row['id_guru'])) {
                        throw new \RuntimeException('Baris identitas guru tidak valid');
                    }

                    $id = trim($row['id_guru']);
                    if ($id !== '' && in_array($id, $chunk, true)) {
                        $remoteIds[] = $id;
                    }
                }
            }

            $connection = $this->connectionDefaults();
            $connection['key'] = 'connected';
            $connection['label'] = 'Terhubung';
            $connection['message'] = 'API GeoPresensi merespons dengan baik.';
            $connection['checked_at'] = Carbon::now('Asia/Jakarta')->toIso8601String();
            $connection['latency_ms'] = (int) round((microtime(true) - $started) * 1000);

            return [array_values(array_unique($remoteIds)), $connection];
        } catch (Throwable $exception) {
            Log::warning('GeoPresensi integration health check failed.', [
                'exception' => $exception::class,
            ]);

            $connection = $this->connectionDefaults();
            $connection['key'] = 'failed';
            $connection['label'] = 'Gagal terhubung';
            $connection['message'] = 'API GeoPresensi tidak dapat diverifikasi saat ini.';
            $connection['checked_at'] = Carbon::now('Asia/Jakarta')->toIso8601String();
            $connection['latency_ms'] = (int) round((microtime(true) - $started) * 1000);

            return [[], $connection];
        }
    }

    /** @param array<string, mixed> $row */
    private function withStatus(array $row, string $key, string $label, string $color, string $reason): array
    {
        $row['key'] = $key;
        $row['label'] = $label;
        $row['color'] = $color;
        $row['reason'] = $reason;

        return $row;
    }
}
