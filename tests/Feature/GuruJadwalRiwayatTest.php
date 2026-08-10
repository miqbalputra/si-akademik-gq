<?php

namespace Tests\Feature;

use App\Models\DiniyyahScheduleChangeLog;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman "Riwayat Perubahan Jadwal Saya" (portal guru): guru hanya melihat
 * perubahan yang menyangkut dirinya — sebagai guru pemilik setelah perubahan
 * (teacher_id) maupun guru lama saat pertukaran (old_teacher_id). Guru lain
 * tidak terlihat. Non-guru (akun tanpa Teacher) ditolak 403; tamu redirect login.
 */
class GuruJadwalRiwayatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guru_sees_own_teacher_id_logs(): void
    {
        [$guruA, $teacherA] = $this->makeGuru('Ustadz Ahmad');
        $admin = $this->makeAdmin();

        DiniyyahScheduleChangeLog::create([
            'teacher_id' => $teacherA->id,
            'old_teacher_id' => null,
            'entity_type' => 'schedule',
            'event' => 'created',
            'change_summary' => 'Jadwal baru dibuat: Fiqih / Mustawa 2 Ikhwan — Senin Sesi 1, guru: Ustadz Ahmad.',
            'changed_by' => $admin->id,
        ]);

        $response = $this->actingAs($guruA)->get(route('guru.jadwal.riwayat'));

        $response->assertOk()
            ->assertSee('Riwayat Perubahan Jadwal')
            ->assertSee('Jadwal baru dibuat: Fiqih')
            ->assertSee($admin->name);
    }

    public function test_guru_sees_swap_log_where_they_are_old_teacher(): void
    {
        [$guruA, $teacherA] = $this->makeGuru('Ustadz Ahmad');
        [$guruB, $teacherB] = $this->makeGuru('Ustadz Budi');
        $admin = $this->makeAdmin();

        // Pertukaran: guru lama A → guru baru B. A muncul sebagai old_teacher_id.
        DiniyyahScheduleChangeLog::create([
            'teacher_id' => $teacherB->id,
            'old_teacher_id' => $teacherA->id,
            'entity_type' => 'schedule',
            'event' => 'updated',
            'change_summary' => 'Jadwal diubah: guru Ustadz Ahmad (Fiqih) → Ustadz Budi (Akidah), Senin Sesi 1.',
            'changed_by' => $admin->id,
        ]);

        $response = $this->actingAs($guruA)->get(route('guru.jadwal.riwayat'));

        $response->assertOk()
            ->assertSee('Ustadz Ahmad')
            ->assertSee('Ustadz Budi')
            ->assertSee('Menyangkut:');
    }

    public function test_guru_does_not_see_other_guru_private_logs(): void
    {
        [$guruA, $teacherA] = $this->makeGuru('Ustadz Ahmad');
        [$guruB, $teacherB] = $this->makeGuru('Ustadz Budi');

        // Log milik B sendiri (teacher_id=B, old_teacher_id=null) — tidak menyangkut A.
        DiniyyahScheduleChangeLog::create([
            'teacher_id' => $teacherB->id,
            'old_teacher_id' => null,
            'entity_type' => 'assignment',
            'event' => 'created',
            'change_summary' => 'Penugasan rahasia Budi: Akidah / Mustawa 3 Ikhwan.',
            'changed_by' => null,
        ]);

        $response = $this->actingAs($guruA)->get(route('guru.jadwal.riwayat'));

        $response->assertOk()
            ->assertDontSee('Penugasan rahasia Budi');
    }

    public function test_user_without_teacher_gets_403(): void
    {
        $user = User::factory()->create();
        $user->assignRole('guru'); // role guru tapi tidak ada record Teacher → 403

        $response = $this->actingAs($user)->get(route('guru.jadwal.riwayat'));

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('guru.jadwal.riwayat'));

        $response->assertRedirect(route('login'));
    }

    public function test_empty_state_when_no_changes(): void
    {
        [$guruA] = $this->makeGuru('Ustadz Ahmad');

        $response = $this->actingAs($guruA)->get(route('guru.jadwal.riwayat'));

        $response->assertOk()->assertSee('Belum ada perubahan jadwal');
    }

    // ----- Helpers -----

    private function makeAdmin(): User
    {
        $user = User::factory()->create(['name' => 'Admin Sekolah']);
        $user->assignRole('admin');

        return $user;
    }

    /** @return array{0: User, 1: Teacher} */
    private function makeGuru(string $name): array
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => $name]);

        return [$user, $teacher];
    }
}