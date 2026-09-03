<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WorkspaceRedirectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadershipWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_kabag_tahfidz_sees_only_tahfidz_portal_navigation_and_cannot_open_diniyyah_dashboard(): void
    {
        $user = $this->userWithRoles(['kabag_tahfidz']);

        $this->actingAs($user)->get(route('kabag-tahfidz.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Kabag Tahfidz')
            ->assertSee("Laporan Tasmi'")
            ->assertSee('Penempatan Halaqah')
            ->assertDontSee('Monitoring Nilai')
            ->assertDontSee('Leger &amp; Rapor', false);
        $this->actingAs($user)->get(route('kabag-diniyyah.dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('diniyyah.monitoring.index'))->assertForbidden();
        $this->actingAs($user)->get(\App\Filament\Resources\Rpps\RppResource::getUrl())->assertForbidden();
    }

    public function test_kabag_diniyyah_gets_operational_dashboard_and_cannot_open_tahfidz_dashboard(): void
    {
        $user = $this->userWithRoles(['kabag_diniyyah']);

        $this->actingAs($user)->get(route('kabag-diniyyah.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Kabag Diniyyah')
            ->assertSee('Monitoring Input Nilai')
            ->assertSee('Monitoring RPP');
        $this->actingAs($user)->get(route('diniyyah.monitoring.index'))->assertOk();
        $this->actingAs($user)->get(\App\Filament\Resources\DiniyyahTeacherAssignments\DiniyyahTeacherAssignmentResource::getUrl())->assertOk();
        $this->actingAs($user)->get(\App\Filament\Resources\Rpps\RppResource::getUrl())->assertOk();
        $this->actingAs($user)->get(route('kabag-tahfidz.dashboard'))->assertForbidden();
    }

    public function test_multi_role_account_can_switch_between_all_leadership_workspaces_and_filament_user_menu(): void
    {
        $user = $this->userWithRoles(['guru', 'kabag_tahfidz', 'kabag_diniyyah']);

        $this->actingAs($user)->get(route('workspace.choose'))
            ->assertOk()
            ->assertSee('Portal Guru')
            ->assertSee('Kabag Tahfidz')
            ->assertSee('Kabag Diniyyah');
        $this->actingAs($user)->post(route('workspace.select'), ['workspace' => WorkspaceRedirectService::KABAG_DINIYYAH])
            ->assertRedirect(route('kabag-diniyyah.dashboard'));
        $this->actingAs($user)->get(route('guru.dashboard'))
            ->assertOk()
            ->assertSee('Ganti Ruang')
            ->assertSee('Kabag Tahfidz')
            ->assertSee('Kabag Diniyyah');
        $this->actingAs($user)->get('/admin')
            ->assertOk()
            ->assertSee('Buka Portal Guru')
            ->assertSee('Buka Kabag Tahfidz')
            ->assertSee('Buka Kabag Diniyyah');
    }

    private function userWithRoles(array $roles): User
    {
        $user = User::factory()->create();

        foreach ($roles as $role) {
            $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
        }

        return $user;
    }
}
