<?php

namespace Tests\Feature;

use App\Filament\Resources\TahfidzHalaqahs\Pages\CreateTahfidzHalaqah;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TahfidzHalaqahCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'kabag_tahfidz', 'kepala_sekolah', 'guru'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
    }

    private function admin(): User
    {
        $u = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('x')]);
        $u->assignRole('admin');

        return $u;
    }

    private function makePrerequisites(): AcademicTerm
    {
        $school = School::create(['name' => 'SMP GQ']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        return AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => 'Semester Ganjil',
            'semester' => 'ganjil',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_open_create_page_and_sees_academic_term_option(): void
    {
        $term = $this->makePrerequisites();

        $resp = $this->actingAs($this->admin())->get('/admin/tahfidz-halaqahs/create');

        $resp->assertOk();
        $resp->assertSee('Periode Akademik');
        $resp->assertSee('Nama Halaqah');
        $resp->assertSee('Semester Ganjil'); // pilihan dropdown dari academic_terms
    }

    public function test_admin_sees_create_button_on_halaqah_list_page(): void
    {
        $this->makePrerequisites();

        $resp = $this->actingAs($this->admin())->get('/admin/tahfidz-halaqahs');

        $resp->assertOk();
        // Filament v5 ListRecords tidak otomatis merender tombol Create; harus
        // didefinisikan via getHeaderActions(). Tombol ini link ke /create.
        $resp->assertSee('tahfidz-halaqahs/create');
    }

    public function test_kepala_sekolah_cannot_create_halaqah(): void
    {
        $u = User::create(['name' => 'KS', 'email' => 'ks@test.com', 'password' => bcrypt('x')]);
        $u->assignRole('kepala_sekolah');

        $resp = $this->actingAs($u)->get('/admin/tahfidz-halaqahs/create');

        // kepala_sekolah bisa melihat (VIEW_ROLES) tapi tidak boleh membuat (MANAGE_ROLES) -> 403
        $resp->assertForbidden();
    }

    public function test_admin_can_create_halaqah_via_filament(): void
    {
        $term = $this->makePrerequisites();

        \Livewire\Livewire::actingAs($this->admin())
            ->test(CreateTahfidzHalaqah::class)
            ->fillForm([
                'academic_term_id' => $term->id,
                'name' => 'Halaqah A',
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tahfidz_halaqahs', [
            'academic_term_id' => $term->id,
            'name' => 'Halaqah A',
            'status' => 'active',
        ]);
    }
}