<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WorkspaceRedirectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkspaceSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_login_for_guru_and_kabag_tahfidz_shows_workspace_choice(): void
    {
        $user = $this->userWithRoles(['guru', 'kabag_tahfidz']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('workspace.choose'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_google_login_for_guru_and_kabag_tahfidz_shows_workspace_choice(): void
    {
        $user = $this->userWithRoles(['guru', 'kabag_tahfidz']);
        $provider = \Mockery::mock(Provider::class);

        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
        $provider->shouldReceive('user')->once()->andReturn(SocialiteUser::fake([
            'id' => 'google-dual-role-user',
            'email' => $user->email,
            'email_verified' => true,
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('workspace.choose'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'google_id' => 'google-dual-role-user']);
    }

    public function test_single_role_users_are_sent_directly_to_their_workspace(): void
    {
        $guru = $this->userWithRoles(['guru']);
        $kabag = $this->userWithRoles(['kabag_tahfidz']);

        $this->post(route('login.store'), ['email' => $guru->email, 'password' => 'password'])
            ->assertRedirect(route('guru.dashboard'));
        $this->post(route('logout'));

        $this->post(route('login.store'), ['email' => $kabag->email, 'password' => 'password'])
            ->assertRedirect(route('kabag-tahfidz.dashboard'));
    }

    public function test_workspace_choice_validates_the_role_owned_by_user(): void
    {
        $guru = $this->userWithRoles(['guru']);
        $dualRole = $this->userWithRoles(['guru', 'kabag_tahfidz']);

        $this->actingAs($guru)
            ->post(route('workspace.select'), ['workspace' => WorkspaceRedirectService::KABAG_TAHFIDZ])
            ->assertForbidden();

        $this->actingAs($dualRole)
            ->post(route('workspace.select'), ['workspace' => WorkspaceRedirectService::KABAG_TAHFIDZ])
            ->assertRedirect(route('kabag-tahfidz.dashboard'));
    }

    public function test_dual_role_user_can_keep_using_intended_tasmi_report_url_after_login(): void
    {
        $user = $this->userWithRoles(['guru', 'kabag_tahfidz']);

        $this->get(route('admin.tasmi-report.index'))->assertRedirect(route('login'));

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('admin.tasmi-report.index'));
    }

    public function test_monitoring_shortcut_only_appears_for_kabag_tahfidz_in_guru_portal(): void
    {
        $dualRole = $this->userWithRoles(['guru', 'kabag_tahfidz']);
        $guru = $this->userWithRoles(['guru']);

        $this->actingAs($dualRole)->get(route('guru.dashboard'))
            ->assertOk()
            ->assertSee("Monitoring Tasmi' Semua Kelas")
            ->assertSee(route('admin.tasmi-report.index'), false);

        $this->actingAs($guru)->get(route('guru.dashboard'))
            ->assertOk()
            ->assertDontSee("Monitoring Tasmi' Semua Kelas");
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
