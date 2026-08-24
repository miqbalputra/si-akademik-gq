<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalShellNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_logout_redirects_to_main_login_and_invalidates_session(): void
    {
        $user = $this->userWithRole('guru');

        $this->actingAs($user)
            ->post(route('logout'), ['_token' => csrf_token()])
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_filament_logout_redirects_to_main_login_and_invalidates_session(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)
            ->post(route('filament.admin.auth.logout'), ['_token' => csrf_token()])
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_guru_desktop_navigation_uses_categories_and_preserves_role_visibility(): void
    {
        $user = $this->userWithRole('guru');
        Teacher::create([
            'user_id' => $user->id,
            'name' => 'Guru Navigasi',
            'gender' => 'male',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('guru.performa'));

        $response->assertOk()
            ->assertSee('school-nav-dropdown', false)
            ->assertSee('Kegiatan')
            ->assertSee('Kelas &amp; Santri', false)
            ->assertSee('Laporan &amp; Arsip', false)
            ->assertSee('Input Nilai')
            ->assertSee('Performa Jurnal Saya')
            ->assertSee('Presensi Saya')
            ->assertDontSee('Tasmi\' Kelas Saya')
            ->assertDontSee('Monitoring Jurnal Kelas')
            ->assertSee(route('guru.attendance-report.index'), false);
    }

    private function userWithRole(string $role): User
    {
        $roleModel = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($roleModel);

        return $user;
    }
}
