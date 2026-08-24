<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruAttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-24 12:00:00', 'Asia/Jakarta')->setTimezone('UTC'));
        Cache::flush();
        config([
            'services.attendance_journal.enabled' => true,
            'services.attendance_journal.base_url' => 'https://geo.example.test',
            'services.attendance_journal.api_key' => str_repeat('k', 32),
            'services.attendance_journal.timeout' => 5,
            'services.attendance_journal.cache_seconds' => 60,
        ]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guru_sees_only_own_geo_report_and_the_response_is_cached(): void
    {
        $context = $this->makeGuru('Guru Edu', 'GURU-001');
        Http::fake([
            'https://geo.example.test/api/v1/integrations/journal/teacher-report*' => Http::response($this->payload('GURU-001', 'Guru Geo'), 200),
        ]);

        $first = $this->actingAs($context['user'])->get(route('guru.attendance-report.index', ['month' => '2026-08']));
        $first->assertOk()
            ->assertSee('Presensi Saya')
            ->assertSee('Guru Geo')
            ->assertSee('Hadir terlambat')
            ->assertSee('Rincian presensi harian');

        $second = $this->actingAs($context['user'])->get(route('guru.attendance-report.index', ['month' => '2026-08']));
        $second->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://geo.example.test/api/v1/integrations/journal/teacher-report?id_guru=GURU-001&start_date=2026-08-01&end_date=2026-08-24'
                && $request->hasHeader('X-API-Key', str_repeat('k', 32));
        });
    }

    public function test_guru_without_niy_sees_mapping_help_without_external_request(): void
    {
        $context = $this->makeGuru('Guru Tanpa NIY');
        Http::fake();

        $response = $this->actingAs($context['user'])->get(route('guru.attendance-report.index'));

        $response->assertOk()->assertSee('NIY Anda belum terhubung ke GeoPresensi');
        Http::assertNothingSent();
    }

    public function test_upstream_failure_is_explained_and_exports_are_not_offered(): void
    {
        $context = $this->makeGuru('Guru Gangguan', 'GURU-003');
        Http::fake([
            'https://geo.example.test/*' => Http::response(['success' => false], 503),
        ]);

        $response = $this->actingAs($context['user'])->get(route('guru.attendance-report.index'));

        $response->assertOk()
            ->assertSee('GeoPresensi belum dapat dihubungi')
            ->assertDontSee('Unduh laporan');
    }

    public function test_guru_can_download_excel_and_pdf_from_the_same_report_payload(): void
    {
        $context = $this->makeGuru('Guru Ekspor', 'GURU-004');
        Http::fake([
            'https://geo.example.test/api/v1/integrations/journal/teacher-report*' => Http::response($this->payload('GURU-004', 'Guru Ekspor Geo'), 200),
        ]);

        $xlsx = $this->actingAs($context['user'])
            ->get(route('guru.attendance-report.export', ['format' => 'xlsx', 'month' => '2026-08']));
        $xlsx->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertSame('PK', substr($xlsx->getContent(), 0, 2));
        $path = tempnam(sys_get_temp_dir(), 'attendance-report-');
        $this->assertNotFalse($path);
        file_put_contents($path, $xlsx->getContent());
        try {
            $workbook = IOFactory::load($path);
        } finally {
            @unlink($path);
        }
        $sheet = $workbook->getSheetByName('Rekap Presensi');
        $this->assertNotNull($sheet);
        $this->assertSame('2026-08-03', $sheet->getCell('A11')->getValue());
        $this->assertSame('Hadir terlambat', $sheet->getCell('D12')->getValue());
        $text = collect($sheet->toArray())->flatten()->implode("\n");
        $this->assertStringContainsString('Guru Ekspor Geo', $text);
        $this->assertStringContainsString('Hadir terlambat', $text);
        $this->assertStringContainsString('Persentase Hadir', $text);
        $workbook->disconnectWorksheets();

        $pdf = $this->actingAs($context['user'])
            ->get(route('guru.attendance-report.export', ['format' => 'pdf', 'month' => '2026-08']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_non_guru_cannot_open_an_attendance_report(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['name' => 'Admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('guru.attendance-report.index'))
            ->assertForbidden();
    }

    /** @return array{user: User, teacher: Teacher} */
    private function makeGuru(string $name, ?string $niy = null): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => $name, 'niy' => $niy]);

        return compact('user', 'teacher');
    }

    /** @return array<string, mixed> */
    private function payload(string $idGuru, string $name): array
    {
        return [
            'success' => true,
            'data' => [
                'teacher' => ['id_guru' => $idGuru, 'nama' => $name],
                'period' => ['start_date' => '2026-08-01', 'end_date' => '2026-08-24'],
                'synced_at' => '2026-08-24T12:00:00+07:00',
                'summary' => ['total_hari' => 3, 'hadir' => 1, 'izin' => 1, 'sakit' => 0, 'alfa' => 1, 'persentase' => 33.3],
                'rows' => [
                    ['tanggal' => '2026-08-03', 'jam_masuk' => '07:00:00', 'jam_pulang' => '12:30:00', 'status' => 'hadir', 'keterangan' => 'Di sekolah'],
                    ['tanggal' => '2026-08-04', 'jam_masuk' => '07:20:00', 'jam_pulang' => '', 'status' => 'hadir_terlambat', 'keterangan' => 'Terlambat'],
                    ['tanggal' => '2026-08-05', 'jam_masuk' => '', 'jam_pulang' => '', 'status' => 'izin', 'keterangan' => 'Keperluan keluarga'],
                    ['tanggal' => '2026-08-06', 'jam_masuk' => '', 'jam_pulang' => '', 'status' => 'alfa', 'keterangan' => 'Tidak presensi'],
                ],
            ],
        ];
    }
}
