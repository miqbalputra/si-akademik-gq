<?php

namespace Tests\Feature;

use App\Filament\Pages\SessionTimeMatrix;
use App\Filament\Pages\SessionTimeComparison;
use App\Models\ClassSession;
use App\Models\ClassSessionTime;
use App\Models\Classroom;
use App\Models\User;
use App\Services\SessionTimeMatrixService;
use App\Support\SessionTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SessionTimeMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'kabag_diniyyah', 'kepala_sekolah', 'guru'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
    }

    // ── PROPAGASI ──────────────────────────────────────────────────────────

    public function test_apply_to_gender_copies_rows_to_same_gender_classrooms(): void
    {
        $m3i = $this->createClassroom('Mustawa 3 Ikhwan');
        $m5i = $this->createClassroom('Mustawa 5 Ikhwan');
        $m5a = $this->createClassroom('Mustawa 5 Akhwat');

        // Ubah M3 Ikhwan Senin Sesi 1 jadi 09:00/09:30.
        $this->setTime($m3i->id, 1, '1', '09:00:00', '09:30:00');

        $res = app(SessionTimeMatrixService::class)->applyToGender($m3i->id, 'ikhwan');

        $this->assertGreaterThan(0, $res['targets']);
        // M5 Ikhwan ikut berubah.
        $this->assertSame(
            ['starts_at' => '09:00:00', 'ends_at' => '09:30:00'],
            SessionTimetable::resolve($m5i->id, 1, '1'),
        );
    }

    public function test_apply_to_gender_does_not_cross_gender(): void
    {
        $m3i = $this->createClassroom('Mustawa 3 Ikhwan');
        $m5a = $this->createClassroom('Mustawa 5 Akhwat');

        $this->setTime($m3i->id, 1, '1', '09:00:00', '09:30:00');

        app(SessionTimeMatrixService::class)->applyToGender($m3i->id, 'ikhwan');

        // Akhwat tidak tersentuh — tetap 10:30/11:00.
        $this->assertSame(
            ['starts_at' => '10:30:00', 'ends_at' => '11:00:00'],
            SessionTimetable::resolve($m5a->id, 1, '1'),
        );
    }

    public function test_apply_to_gender_m1_does_not_target_m2_m6(): void
    {
        $m1i = $this->createClassroom('Mustawa 1 Ikhwan');
        $m2i = $this->createClassroom('Mustawa 2 Ikhwan');

        // M1 tidak punya tafsir Kamis; M2 punya.
        $this->assertNull(SessionTimetable::resolve($m1i->id, 4, 'tafsir'));
        $this->assertSame(
            ['starts_at' => '09:50:00', 'ends_at' => '10:20:00'],
            SessionTimetable::resolve($m2i->id, 4, 'tafsir'),
        );

        $res = app(SessionTimeMatrixService::class)->applyToGender($m1i->id, 'ikhwan');

        // Level-band scoping: M1 (level 1) tidak men-target M2 (level 2).
        $this->assertSame(0, $res['targets']);
        $this->assertNotEmpty($res['warnings']);
        // M2 tafsir Kamis tetap utuh.
        $this->assertSame(
            ['starts_at' => '09:50:00', 'ends_at' => '10:20:00'],
            SessionTimetable::resolve($m2i->id, 4, 'tafsir'),
        );
    }

    public function test_apply_to_gender_preserves_target_tafsir_not_in_source(): void
    {
        // Safety net union-merge: sumber (M5 Ikhwan) dihapus tafsir Kamis-nya,
        // propagasi TIDAK boleh menghapus tafsir di target (M2 Ikhwan).
        $m5i = $this->createClassroom('Mustawa 5 Ikhwan');
        $m2i = $this->createClassroom('Mustawa 2 Ikhwan');

        // Hapus tafsir Kamis di M5 Ikhwan.
        $tafsir = ClassSession::where('session_name', 'tafsir')->first();
        ClassSessionTime::query()
            ->where('classroom_id', $m5i->id)
            ->where('day_of_week', 4)
            ->where('class_session_id', $tafsir->id)
            ->delete();

        app(SessionTimeMatrixService::class)->applyToGender($m5i->id, 'ikhwan');

        // M2 Ikhwan tafsir Kamis tetap ada.
        $this->assertSame(
            ['starts_at' => '09:50:00', 'ends_at' => '10:20:00'],
            SessionTimetable::resolve($m2i->id, 4, 'tafsir'),
        );
    }

    // ── RESET ──────────────────────────────────────────────────────────────

    public function test_reset_to_default_restores_code_defaults(): void
    {
        $m3i = $this->createClassroom('Mustawa 3 Ikhwan');

        $this->setTime($m3i->id, 1, '1', '09:00:00', '09:30:00');
        $this->assertSame('09:00:00', SessionTimetable::resolve($m3i->id, 1, '1')['starts_at']);

        $count = app(SessionTimeMatrixService::class)->resetToDefault($m3i->id);

        $this->assertGreaterThan(0, $count);
        $this->assertSame(
            ['starts_at' => '07:40:00', 'ends_at' => '08:10:00'],
            SessionTimetable::resolve($m3i->id, 1, '1'),
        );
    }

    public function test_reset_to_default_m1_keeps_no_tafsir_kamis(): void
    {
        $m1i = $this->createClassroom('Mustawa 1 Ikhwan');

        // Suntik tafsir Kamis ke M1 (penyimpangan), lalu reset harus memulihkan
        // ke default kode (M1 tanpa tafsir Kamis).
        $this->setTime($m1i->id, 4, 'tafsir', '09:50:00', '10:20:00');
        $this->assertNotNull(SessionTimetable::resolve($m1i->id, 4, 'tafsir'));

        app(SessionTimeMatrixService::class)->resetToDefault($m1i->id);

        $this->assertNull(SessionTimetable::resolve($m1i->id, 4, 'tafsir'));
    }

    // ── COMPARISON ─────────────────────────────────────────────────────────

    public function test_comparison_detects_senin_difference(): void
    {
        $m5i = $this->createClassroom('Mustawa 5 Ikhwan');
        $m5a = $this->createClassroom('Mustawa 5 Akhwat');

        $rows = app(SessionTimeMatrixService::class)->compare($m5i->id, $m5a->id);

        $senin1 = collect($rows)->first(fn (array $r) => $r['day'] === 1 && $r['session_name'] === '1');
        $this->assertNotNull($senin1);
        $this->assertTrue($senin1['differs'], 'Senin Sesi 1 Ikhwan vs Akhwat harus BERBEDA');
        $this->assertSame('07:40:00', $senin1['ikhwan']['starts_at']);
        $this->assertSame('10:30:00', $senin1['akhwat']['starts_at']);

        $selasa1 = collect($rows)->first(fn (array $r) => $r['day'] === 2 && $r['session_name'] === '1');
        $this->assertNotNull($selasa1);
        $this->assertFalse($selasa1['differs'], 'Selasa Sesi 1 harus SAMA');
    }

    public function test_comparison_m2_kamis_tafsir_same(): void
    {
        $m2i = $this->createClassroom('Mustawa 2 Ikhwan');
        $m2a = $this->createClassroom('Mustawa 2 Akhwat');

        $rows = app(SessionTimeMatrixService::class)->compare($m2i->id, $m2a->id);

        $tafsir = collect($rows)->first(fn (array $r) => $r['day'] === 4 && $r['session_name'] === 'tafsir');
        $this->assertNotNull($tafsir);
        $this->assertFalse($tafsir['differs'], 'Kamis Tafsir M2 harus SAMA antar gender');
        $this->assertSame('09:50:00', $tafsir['ikhwan']['starts_at']);
        $this->assertSame('09:50:00', $tafsir['akhwat']['starts_at']);
    }

    // ── PAGE ACCESS ────────────────────────────────────────────────────────

    public function test_matrix_page_requires_admin(): void
    {
        $this->actingAs($this->userWithRole('guru'))
            ->get('/admin/session-time-matrix')
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->get('/admin/session-time-matrix')
            ->assertOk();
    }

    public function test_comparison_page_accessible_by_kabag_diniyyah(): void
    {
        $this->actingAs($this->userWithRole('kabag_diniyyah'))
            ->get('/admin/session-time-comparison')
            ->assertOk();

        $this->actingAs($this->userWithRole('guru'))
            ->get('/admin/session-time-comparison')
            ->assertForbidden();
    }

    // ── HELPERS ────────────────────────────────────────────────────────────

    private function createClassroom(string $name): Classroom
    {
        $classroom = Classroom::create(['name' => $name]);
        SessionTimetable::seedForClassroom($classroom);

        return $classroom;
    }

    private function setTime(int $classroomId, int $day, string $sessionName, string $start, string $end): void
    {
        $session = ClassSession::where('session_name', $sessionName)->first();
        ClassSessionTime::updateOrCreate(
            ['classroom_id' => $classroomId, 'day_of_week' => $day, 'class_session_id' => $session->id],
            ['starts_at' => $start, 'ends_at' => $end],
        );
    }

    private function admin(): User
    {
        return $this->userWithRole('admin');
    }

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }
}