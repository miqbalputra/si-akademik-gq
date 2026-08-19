<?php

namespace Tests\Feature;

use App\Filament\Pages\AttendanceIntegrationStatus;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AttendanceIntegrationStatusPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config(['services.attendance_journal.enabled' => false]);
    }

    public function test_allowed_management_roles_can_access_the_integration_page(): void
    {
        foreach (['admin', 'kabag_diniyyah', 'kepala_sekolah'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->actingAs($user);

            $this->assertTrue(AttendanceIntegrationStatus::canAccess());
            $this->get('/admin/attendance-integration-status')
                ->assertOk()
                ->assertSee('Integrasi GeoPresensi')
                ->assertSee('Integrasi nonaktif');
        }
    }

    public function test_other_roles_cannot_access_the_integration_page(): void
    {
        foreach (['guru', 'wali_santri'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->actingAs($user);

            $this->assertFalse(AttendanceIntegrationStatus::canAccess());
        }
    }

    public function test_teacher_listing_shows_cached_mapping_badge(): void
    {
        $teacherUser = User::factory()->create();
        Teacher::create([
            'user_id' => $teacherUser->id,
            'name' => 'Guru Terhubung',
            'niy' => 'GURU001',
            'status' => 'active',
        ]);

        config([
            'services.attendance_journal.enabled' => true,
            'services.attendance_journal.base_url' => 'https://geo.example.test',
            'services.attendance_journal.api_key' => 'journal-test-key',
        ]);
        Http::fake([
            'https://geo.example.test/api/v1/integrations/journal/teachers*' => Http::response([
                'success' => true,
                'data' => [['id_guru' => 'GURU001']],
            ]),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get('/admin/teachers')
            ->assertOk()
            ->assertSee('Terhubung')
            ->assertSee('GURU001')
            ->assertDontSee('journal-test-key');
    }
}
