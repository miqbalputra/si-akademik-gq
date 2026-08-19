<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use App\Services\AttendanceIntegrationStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AttendanceIntegrationStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'services.attendance_journal.enabled' => true,
            'services.attendance_journal.base_url' => 'https://geo.example.test',
            'services.attendance_journal.api_key' => 'journal-test-key',
            'services.attendance_journal.cache_seconds' => 60,
        ]);
    }

    public function test_audit_reports_mapping_statuses_and_uses_one_cached_batch_request(): void
    {
        $connected = $this->teacher('Guru Terhubung', 'GURU001');
        $this->teacher('Guru Tanpa NIY', null);
        $duplicateA = $this->teacher('Guru Duplikat A', 'DUP001');
        $duplicateB = $this->teacher('Guru Duplikat B', 'DUP001');
        $notFound = $this->teacher('Guru Tidak Ditemukan', 'GURU404');

        Http::fake([
            'https://geo.example.test/api/v1/integrations/journal/teachers*' => Http::response([
                'success' => true,
                'data' => [['id_guru' => 'GURU001']],
            ]),
        ]);

        $service = app(AttendanceIntegrationStatusService::class);
        $audit = $service->audit(force: true);

        $this->assertSame('connected', $audit['connection']['key']);
        $this->assertSame(5, $audit['summary']['total_active']);
        $this->assertSame(1, $audit['summary']['connected']);
        $this->assertSame(1, $audit['summary']['missing_niy']);
        $this->assertSame(2, $audit['summary']['duplicate_niy']);
        $this->assertSame(1, $audit['summary']['not_found']);
        $this->assertSame(0, $audit['summary']['unverified']);

        $rows = collect($audit['teachers'])->keyBy('teacher_id');
        $this->assertSame('connected', $rows[$connected->id]['key']);
        $this->assertSame('missing', $rows[$this->teacherId('Guru Tanpa NIY')]['key']);
        $this->assertSame('duplicate', $rows[$duplicateA->id]['key']);
        $this->assertSame('duplicate', $rows[$duplicateB->id]['key']);
        $this->assertSame('not_found', $rows[$notFound->id]['key']);

        $service->audit();
        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $ids = explode(',', (string) ($query['teacher_ids'] ?? ''));
            sort($ids);

            return $request->hasHeader('X-API-Key', 'journal-test-key')
                && $ids === ['GURU001', 'GURU404'];
        });
    }

    public function test_api_failure_keeps_valid_mappings_unverified(): void
    {
        $teacher = $this->teacher('Guru API Gagal', 'GURU001');

        Http::fake([
            'https://geo.example.test/api/v1/integrations/journal/teachers*' => Http::response([], 503),
        ]);

        $audit = app(AttendanceIntegrationStatusService::class)->audit(force: true);

        $this->assertSame('failed', $audit['connection']['key']);
        $this->assertSame(0, $audit['summary']['connected']);
        $this->assertSame(1, $audit['summary']['unverified']);
        $this->assertSame('unverified', collect($audit['teachers'])->firstWhere('teacher_id', $teacher->id)['key']);
    }

    public function test_invalid_api_payload_is_fail_closed(): void
    {
        $teacher = $this->teacher('Guru Respons Invalid', 'GURU001');

        Http::fake([
            'https://geo.example.test/api/v1/integrations/journal/teachers*' => Http::response([
                'success' => true,
                'data' => ['GURU001'],
            ]),
        ]);

        $audit = app(AttendanceIntegrationStatusService::class)->audit(force: true);

        $this->assertSame('failed', $audit['connection']['key']);
        $this->assertSame('unverified', collect($audit['teachers'])->firstWhere('teacher_id', $teacher->id)['key']);
    }

    public function test_disabled_and_incomplete_configuration_are_exposed_without_api_request(): void
    {
        $this->teacher('Guru Konfigurasi', 'GURU001');
        Http::fake();

        config(['services.attendance_journal.enabled' => false]);
        $disabled = app(AttendanceIntegrationStatusService::class)->audit(force: true);
        $this->assertSame('disabled', $disabled['connection']['key']);
        Http::assertNothingSent();

        config([
            'services.attendance_journal.enabled' => true,
            'services.attendance_journal.api_key' => '',
        ]);
        $incomplete = app(AttendanceIntegrationStatusService::class)->audit(force: true);
        $this->assertSame('incomplete', $incomplete['connection']['key']);
        Http::assertNothingSent();
    }

    private function teacher(string $name, ?string $niy): Teacher
    {
        $user = User::factory()->create();

        return Teacher::create([
            'user_id' => $user->id,
            'name' => $name,
            'niy' => $niy,
            'status' => 'active',
        ]);
    }

    private function teacherId(string $name): int
    {
        return (int) Teacher::query()->where('name', $name)->value('id');
    }
}
