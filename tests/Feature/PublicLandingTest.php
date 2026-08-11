<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_landing_has_static_interactive_academic_flow_and_portal_ctas(): void
    {
        $this->get(route('login'))->assertOk();

        $this->get('/')
            ->assertOk()
            ->assertSee('DEMO AMAN')
            ->assertSee('data-factory-stage', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('INPUT GURU')
            ->assertSee('VALIDASI MANAJEMEN')
            ->assertSee('RINGKASAN WALI')
            ->assertSee('ARSIP RAPOR')
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.route('guru.dashboard').'"', false)
            ->assertSee('href="'.route('wali.dashboard').'"', false)
            ->assertSee('href="'.url('/admin').'"', false);
    }

    public function test_authenticated_users_receive_their_matching_dashboard_cta(): void
    {
        foreach ([
            'guru' => route('guru.dashboard'),
            'wali_santri' => route('wali.dashboard'),
            'admin' => url('/admin'),
        ] as $roleName => $dashboardUrl) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $user = User::factory()->create();
            $user->assignRole($roleName);

            $this->actingAs($user)
                ->get('/')
                ->assertOk()
                ->assertSee('Buka dashboard')
                ->assertSee('href="'.$dashboardUrl.'"', false);
        }
    }
}
