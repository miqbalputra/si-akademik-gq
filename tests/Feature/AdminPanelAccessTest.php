<?php

namespace Tests\Feature;

use App\Filament\Pages\AcademicCalendar;
use App\Filament\Pages\DemoFlow;
use App\Filament\Resources\DiniyyahClassSubjects\DiniyyahClassSubjectResource;
use App\Filament\Resources\ReportCards\ReportCardResource;
use App\Filament\Resources\Students\StudentResource;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_only_internal_leadership_roles_can_access_filament_panel(): void
    {
        $this->assertTrue($this->userWithRole('admin')->canAccessPanel(Panel::make()));
        $this->assertTrue($this->userWithRole('kabag_diniyyah')->canAccessPanel(Panel::make()));
        $this->assertTrue($this->userWithRole('kepala_sekolah')->canAccessPanel(Panel::make()));

        $this->assertFalse($this->userWithRole('guru')->canAccessPanel(Panel::make()));
        $this->assertFalse($this->userWithRole('wali_santri')->canAccessPanel(Panel::make()));
    }

    public function test_kepala_sekolah_gets_read_only_resource_access(): void
    {
        $this->actingAs($this->userWithRole('kepala_sekolah'));

        $this->assertTrue(AcademicCalendar::canAccess());
        $this->assertTrue(DemoFlow::canAccess());
        $this->assertTrue(StudentResource::canAccess());
        $this->assertFalse(StudentResource::canCreate());

        $this->assertTrue(ReportCardResource::canAccess());
        $this->assertFalse(ReportCardResource::canCreate());
    }

    public function test_kabag_diniyyah_can_manage_diniyyah_but_not_core_student_data(): void
    {
        $this->actingAs($this->userWithRole('kabag_diniyyah'));

        $this->assertTrue(AcademicCalendar::canAccess());
        $this->assertTrue(DiniyyahClassSubjectResource::canAccess());
        $this->assertTrue(DiniyyahClassSubjectResource::canCreate());

        $this->assertTrue(StudentResource::canAccess());
        $this->assertFalse(StudentResource::canCreate());
    }

    public function test_admin_can_open_demo_flow_page(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/demo-flow')
            ->assertOk()
            ->assertSee('Alur Demo')
            ->assertSee('M3 Ikhwan Demo')
            ->assertSee('wali@example.com');
    }

    public function test_admin_can_open_academic_calendar_page(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/academic-calendar')
            ->assertOk()
            ->assertSee('Kalender Akademik')
            ->assertSee('Kalender Indonesia')
            ->assertSee('Senin')
            ->assertSee('Tambah Libur Sekolah');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
