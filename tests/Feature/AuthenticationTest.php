<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_can_be_rendered(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Masuk ke akun');
    }

    public function test_wali_santri_is_redirected_to_guardian_dashboard_after_login(): void
    {
        Role::firstOrCreate(['name' => 'wali_santri', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'email' => 'wali@example.com',
            'password' => 'password',
        ]);
        $user->assignRole('wali_santri');

        $this->post(route('login.store'), [
            'email' => 'wali@example.com',
            'password' => 'password',
        ])
            ->assertRedirect(route('wali.dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_guest_wali_dashboard_redirects_to_login(): void
    {
        $this->get(route('wali.dashboard'))
            ->assertRedirect(route('login'));
    }
}
