<?php

namespace App\Services;

use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read-only server-to-server client for GeoPresensi teacher attendance.
 *
 * The returned map is keyed by the local Teacher ID so callers never need to
 * join application databases or expose the integration key to a browser.
 */
class AttendanceStatusClient
{
    public const EXEMPT_STATUSES = ['izin', 'sakit'];
    public const PRESENT_STATUSES = ['hadir', 'hadir_terlambat', 'hadir_izin_terlambat'];

    private const VALID_STATUSES = [
        ...self::PRESENT_STATUSES,
        'izin',
        'sakit',
    ];

    /**
     * @param  iterable<Teacher>  $teachers
     * @return array<string, array{available: bool, mapped: bool, external_id: ?string, statuses: array<string, string>}>
     */
    public function statusesForTeachers(iterable $teachers, Carbon $startDate, Carbon $endDate, bool $verifyMappings = false): array
    {
        $teachers = collect($teachers)
            ->filter(fn ($teacher): bool => $teacher instanceof Teacher)
            ->values();

        $result = $teachers->mapWithKeys(function (Teacher $teacher): array {
            return [(string) $teacher->id => [
                'available' => false,
                'mapped' => false,
                'external_id' => null,
                'statuses' => [],
            ]];
        })->all();

        if (! (bool) config('services.attendance_journal.enabled', false) || $teachers->isEmpty()) {
            return $result;
        }

        $mappedTeachers = $teachers->filter(function (Teacher $teacher): bool {
            return trim((string) $teacher->niy) !== '';
        });

        $duplicateExternalIds = $mappedTeachers
            ->groupBy(fn (Teacher $teacher): string => trim((string) $teacher->niy))
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->keys();

        if ($duplicateExternalIds->isNotEmpty()) {
            Log::warning('Attendance mapping has duplicate teacher NIY values; exemptions disabled for those teachers.', [
                'niy' => $duplicateExternalIds->values()->all(),
            ]);
            $mappedTeachers = $mappedTeachers->reject(
                fn (Teacher $teacher): bool => $duplicateExternalIds->contains(trim((string) $teacher->niy)),
            );
        }

        if ($mappedTeachers->isEmpty()) {
            Log::warning('No valid teacher NIY mapping available for attendance integration.', [
                'teacher_ids' => $teachers->pluck('id')->values()->all(),
            ]);

            return $result;
        }

        $externalIds = $mappedTeachers
            ->map(fn (Teacher $teacher): string => trim((string) $teacher->niy))
            ->unique()
            ->sort()
            ->values();
        if ($verifyMappings) {
            try {
                $knownExternalIds = $this->knownTeacherIds($externalIds);
            } catch (Throwable $exception) {
                Log::warning('Attendance teacher mapping could not be verified; journal reconciliation disabled for this request.', [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                return $result;
            }

            $mappedTeachers = $mappedTeachers->filter(
                fn (Teacher $teacher): bool => $knownExternalIds->contains(trim((string) $teacher->niy)),
            )->values();
            $externalIds = $mappedTeachers
                ->map(fn (Teacher $teacher): string => trim((string) $teacher->niy))
                ->unique()
                ->sort()
                ->values();
            if ($mappedTeachers->isEmpty()) {
                return $result;
            }

            // Preserve a verified NIY even when the subsequent attendance
            // request fails, so reconciliation can accurately say that the
            // attendance service (not the identity mapping) is unavailable.
            foreach ($mappedTeachers as $teacher) {
                $result[(string) $teacher->id]['mapped'] = true;
                $result[(string) $teacher->id]['external_id'] = trim((string) $teacher->niy);
            }
        }
        $start = $startDate->copy()->setTimezone('Asia/Jakarta')->toDateString();
        $end = $endDate->copy()->setTimezone('Asia/Jakarta')->toDateString();
        $cacheKey = 'attendance-journal:'.sha1($externalIds->implode(',').'|'.$start.'|'.$end);

        try {
            $rows = Cache::remember(
                $cacheKey,
                now()->addSeconds(max(0, (int) config('services.attendance_journal.cache_seconds', 60))),
                function () use ($externalIds, $start, $end): array {
                    $baseUrl = rtrim((string) config('services.attendance_journal.base_url'), '/');
                    $apiKey = trim((string) config('services.attendance_journal.api_key'));

                    if ($baseUrl === '' || $apiKey === '') {
                        throw new \RuntimeException('Attendance integration URL or API key is not configured.');
                    }

                    $response = Http::acceptJson()
                        ->withHeaders(['X-API-Key' => $apiKey])
                        ->timeout(max(1, (int) config('services.attendance_journal.timeout', 5)))
                        ->get($baseUrl.'/api/v1/integrations/journal/attendance', [
                            'teacher_ids' => $externalIds->implode(','),
                            'start_date' => $start,
                            'end_date' => $end,
                        ]);

                    if (! $response->successful()) {
                        throw new \RuntimeException('GeoPresensi returned HTTP '.$response->status().'.');
                    }

                    $payload = $response->json();
                    if (! is_array($payload) || ($payload['success'] ?? false) !== true || ! is_array($payload['data'] ?? null)) {
                        throw new \RuntimeException('GeoPresensi returned an invalid response shape.');
                    }

                    return $payload['data'];
                },
            );
        } catch (Throwable $exception) {
            Log::warning('Attendance API unavailable; journal exemptions disabled for this request.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'start_date' => $start,
                'end_date' => $end,
            ]);

            return $result;
        }

        foreach ($mappedTeachers as $teacher) {
            $result[(string) $teacher->id] = [
                'available' => true,
                'mapped' => true,
                'external_id' => trim((string) $teacher->niy),
                'statuses' => [],
            ];
        }

        $knownIds = $externalIds->flip();
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $externalId = trim((string) ($row['id_guru'] ?? ''));
            $date = trim((string) ($row['tanggal'] ?? ''));
            $status = strtolower(trim((string) ($row['status'] ?? '')));
            if ($externalId === '' || ! $knownIds->has($externalId)
                || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                || $date < $start || $date > $end
                || ! in_array($status, self::VALID_STATUSES, true)) {
                Log::warning('Ignoring invalid attendance integration row.', [
                    'external_id' => $externalId,
                    'date' => $date,
                    'status' => $status,
                ]);

                continue;
            }

            foreach ($mappedTeachers->filter(fn (Teacher $teacher): bool => trim((string) $teacher->niy) === $externalId) as $teacher) {
                $result[(string) $teacher->id]['statuses'][$date] = $status;
            }
        }

        return $result;
    }

    /**
     * @return array{available: bool, mapped: bool, external_id: ?string, statuses: array<string, string>}
     */
    public function statusesForTeacher(Teacher $teacher, Carbon $startDate, Carbon $endDate, bool $verifyMappings = false): array
    {
        return $this->statusesForTeachers([$teacher], $startDate, $endDate, $verifyMappings)[(string) $teacher->id]
            ?? ['available' => false, 'mapped' => false, 'external_id' => null, 'statuses' => []];
    }

    public function isExempt(?string $status): bool
    {
        return in_array(strtolower(trim((string) $status)), self::EXEMPT_STATUSES, true);
    }

    public function isPresent(?string $status): bool
    {
        return in_array(strtolower(trim((string) $status)), self::PRESENT_STATUSES, true);
    }

    /** @param Collection<int, string> $externalIds
     *  @return Collection<int, string>
     */
    private function knownTeacherIds(Collection $externalIds): Collection
    {
        $cacheKey = 'attendance-journal:teachers:'.sha1($externalIds->implode(','));

        return Cache::remember(
            $cacheKey,
            now()->addSeconds(max(0, (int) config('services.attendance_journal.cache_seconds', 60))),
            function () use ($externalIds): Collection {
                $baseUrl = rtrim((string) config('services.attendance_journal.base_url'), '/');
                $apiKey = trim((string) config('services.attendance_journal.api_key'));
                if ($baseUrl === '' || $apiKey === '') {
                    throw new \RuntimeException('Attendance integration URL or API key is not configured.');
                }

                $response = Http::acceptJson()
                    ->withHeaders(['X-API-Key' => $apiKey])
                    ->timeout(max(1, (int) config('services.attendance_journal.timeout', 5)))
                    ->get($baseUrl.'/api/v1/integrations/journal/teachers', [
                        'teacher_ids' => $externalIds->implode(','),
                    ]);
                if (! $response->successful()) {
                    throw new \RuntimeException('GeoPresensi teacher mapping returned HTTP '.$response->status().'.');
                }

                $payload = $response->json();
                if (! is_array($payload) || ($payload['success'] ?? false) !== true || ! is_array($payload['data'] ?? null)) {
                    throw new \RuntimeException('GeoPresensi returned an invalid teacher mapping response.');
                }

                return collect($payload['data'])
                    ->filter(fn ($row): bool => is_array($row))
                    ->map(fn (array $row): string => trim((string) ($row['id_guru'] ?? '')))
                    ->filter(fn (string $id): bool => $id !== '' && $externalIds->contains($id))
                    ->unique()
                    ->values();
            },
        );
    }
}
