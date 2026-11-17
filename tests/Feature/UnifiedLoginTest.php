<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnifiedLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_panel_login_redirects_to_the_only_login_form(): void
    {
        $this->get('/admin/login')->assertRedirect(route('login'));
        $this->followingRedirects()->get('/admin/login')
            ->assertOk()->assertSee('Pintu Masuk Ruang GQ')
            ->assertSee('action="'.route('login.store').'"', false)
            ->assertDontSee('Masuk ke Portal Admin');
    }

    public function test_expired_panel_sessions_redirect_directly_to_main_login(): void
    {
        foreach (['/admin', '/admin/students', '/admin/diniyyah-class-journals'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
        $this->getJson('/admin')->assertUnauthorized()->assertHeaderMissing('Location');
    }

    public static function managementRoles(): array
    {
        return [
            'kabag diniyyah' => ['kabag_diniyyah'],
            'kabag tahfidz' => ['kabag_tahfidz'],
            'admin' => ['admin'],
            'kepala sekolah' => ['kepala_sekolah'],
        ];
    }

    #[DataProvider('managementRoles')]
    public function test_panel_logout_then_teacher_username_login_uses_only_main_login(string $role): void
    {
        $manager = $this->userWithRole($role);
        $teacherUser = $this->userWithRole('guru');
        Teacher::create(['user_id' => $teacherUser->id, 'name' => $teacherUser->name, 'gender' => 'male', 'status' => 'active']);

        $this->actingAs($manager, 'admin')->withSession(['url.intended' => url('/admin/students')])
            ->post(route('filament.admin.auth.logout'))->assertRedirect(route('login'));
        $this->assertGuest('admin');
        $this->assertGuest('web');
        $this->assertFalse(session()->has('url.intended'));

        // A stale tab or bookmark must not reopen the separate admin form.
        $this->get('/admin/login')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Pintu Masuk Ruang GQ');
        $this->post(route('login.store'), ['login' => $teacherUser->username, 'password' => 'password'])
            ->assertRedirect(route('guru.dashboard'));

        $this->assertAuthenticatedAs($teacherUser, 'web');
        $this->assertGuest('admin');
        $this->get(route('guru.dashboard'))->assertOk();
    }

    public function test_kabag_portal_logout_then_teacher_email_login_succeeds(): void
    {
        $kabag = $this->userWithRole('kabag_diniyyah');
        $teacher = $this->userWithRole('guru');

        $this->post(route('login.store'), ['login' => $kabag->username, 'password' => 'password'])
            ->assertRedirect(route('kabag-diniyyah.dashboard'));
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->get('/admin/login')->assertRedirect(route('login'));
        $this->post(route('login.store'), ['email' => $teacher->email, 'password' => 'password'])
            ->assertRedirect(route('guru.dashboard'));
        $this->assertAuthenticatedAs($teacher, 'web');
        $this->assertGuest('admin');
    }

    public function test_main_login_still_authenticates_admin_on_its_independent_guard(): void
    {
        $admin = $this->userWithRole('admin');

        $this->post(route('login.store'), ['login' => $admin->username, 'password' => 'password'])
            ->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('web');
        $this->get('/admin')->assertOk();
    }

    public static function logoutRoutes(): array
    {
        return ['portal logout' => ['logout'], 'panel logout' => ['filament.admin.auth.logout']];
    }

    #[DataProvider('logoutRoutes')]
    public function test_logout_clears_both_guards_and_remember_cookies(string $logoutRoute): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('guru');
        auth('web')->login($teacher, true);
        auth('admin')->login($admin, true);
        $this->withCookies([
            auth('web')->getRecallerName() => cookie()->queued(auth('web')->getRecallerName())->getValue(),
            auth('admin')->getRecallerName() => cookie()->queued(auth('admin')->getRecallerName())->getValue(),
        ]);

        $response = $this->post(route($logoutRoute));
        $response->assertRedirect(route('login'));
        $response->assertCookieExpired(auth('web')->getRecallerName());
        $response->assertCookieExpired(auth('admin')->getRecallerName());
        $this->assertGuest('web');
        $this->assertGuest('admin');
    }

    public function test_wrong_password_is_rejected_at_main_login_without_authenticating_either_guard(): void
    {
        $teacher = $this->userWithRole('guru');

        $this->from(route('login'))->post(route('login.store'), ['login' => $teacher->username, 'password' => 'incorrect'])
            ->assertRedirect(route('login'))->assertSessionHasErrors('login');
        $this->assertGuest('web');
        $this->assertGuest('admin');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['username' => fake()->unique()->userName()]);
        $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

        return $user;
    }
}
