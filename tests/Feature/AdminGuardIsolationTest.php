<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminGuardIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_guard_session_does_not_authenticate_the_filament_admin_guard(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin, 'web')
            ->get('/admin')
            ->assertRedirect('/admin/login');

        $this->assertGuest('admin');
    }

    public function test_admin_guard_session_does_not_authenticate_the_teacher_portal(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin, 'admin');
        config(['auth.defaults.guard' => 'web']);

        $this->get('/admin/demo-flow')->assertOk();
        $this->get(route('guru.dashboard'))->assertRedirect(route('login'));
    }

    private function userWithRole(string $role): User
    {
        $role = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
