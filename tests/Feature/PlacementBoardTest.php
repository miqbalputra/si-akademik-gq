<?php

namespace Tests\Feature;

use App\Filament\Pages\ClassPlacementBoard;
use App\Filament\Pages\HalaqahPlacementBoard;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\School;
use App\Models\Student;
use App\Models\TahfidzHalaqah;
use App\Models\TahfidzHalaqahMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlacementBoardTest extends TestCase
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

    private function userWithRole(string $role): User
    {
        $u = User::create(['name' => $role, 'email' => $role.'@test.com', 'password' => bcrypt('x')]);
        $u->assignRole($role);

        return $u;
    }

    private function makeTerm(): AcademicTerm
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

    private function makeStudent(string $name, string $nis, string $gender = 'male'): Student
    {
        return Student::create([
            'name' => $name,
            'nis' => $nis,
            'gender' => $gender,
        ]);
    }

    private function makeClassroomTerm(AcademicTerm $term, string $name): ClassroomTerm
    {
        $classroom = Classroom::create(['name' => $name]);

        return ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => $name,
        ]);
    }

    private function makeHalaqah(AcademicTerm $term, string $name): TahfidzHalaqah
    {
        return TahfidzHalaqah::create([
            'academic_term_id' => $term->id,
            'name' => $name,
        ]);
    }

    // ── CLASS BOARD ────────────────────────────────────────────────────────

    public function test_admin_can_place_student_to_class(): void
    {
        $term = $this->makeTerm();
        $ct = $this->makeClassroomTerm($term, 'Kelas A');
        $student = $this->makeStudent('Ahmad', 'N001');

        \Livewire\Livewire::actingAs($this->admin())
            ->test(ClassPlacementBoard::class)
            ->set('academicTermId', $term->id)
            ->call('assignToClass', $student->id, $ct->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('class_enrollments', [
            'academic_term_id' => $term->id,
            'classroom_term_id' => $ct->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);
    }

    public function test_moving_student_between_classes_keeps_single_row(): void
    {
        $term = $this->makeTerm();
        $a = $this->makeClassroomTerm($term, 'Kelas A');
        $b = $this->makeClassroomTerm($term, 'Kelas B');
        $student = $this->makeStudent('Ahmad', 'N001');

        $page = \Livewire\Livewire::actingAs($this->admin())->test(ClassPlacementBoard::class)
            ->set('academicTermId', $term->id);

        $page->call('assignToClass', $student->id, $a->id);
        $page->call('assignToClass', $student->id, $b->id);

        $this->assertSame(1, ClassEnrollment::where('student_id', $student->id)->count());
        $this->assertDatabaseHas('class_enrollments', [
            'student_id' => $student->id,
            'classroom_term_id' => $b->id,
            'status' => 'active',
        ]);
    }

    public function test_unassign_class_sets_inactive(): void
    {
        $term = $this->makeTerm();
        $a = $this->makeClassroomTerm($term, 'Kelas A');
        $student = $this->makeStudent('Ahmad', 'N001');

        $page = \Livewire\Livewire::actingAs($this->admin())->test(ClassPlacementBoard::class)
            ->set('academicTermId', $term->id);

        $page->call('assignToClass', $student->id, $a->id);
        $page->call('assignToClass', $student->id, null);

        $this->assertSame(1, ClassEnrollment::where('student_id', $student->id)->count());
        $this->assertDatabaseHas('class_enrollments', [
            'student_id' => $student->id,
            'status' => 'inactive',
        ]);
    }

    public function test_non_admin_forbidden_on_class_board(): void
    {
        $this->actingAs($this->userWithRole('kepala_sekolah'))
            ->get('/admin/class-placement-board')
            ->assertForbidden();
    }

    // ── HALAQAH BOARD ──────────────────────────────────────────────────────

    public function test_admin_can_place_student_to_halaqah(): void
    {
        $term = $this->makeTerm();
        $h = $this->makeHalaqah($term, 'Halaqah 1');
        $student = $this->makeStudent('Ahmad', 'N001');

        \Livewire\Livewire::actingAs($this->admin())
            ->test(HalaqahPlacementBoard::class)
            ->set('academicTermId', $term->id)
            ->call('assignToHalaqah', $student->id, $h->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tahfidz_halaqah_members', [
            'tahfidz_halaqah_id' => $h->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);
    }

    public function test_halaqah_member_links_active_class_enrollment(): void
    {
        $term = $this->makeTerm();
        $ct = $this->makeClassroomTerm($term, 'Kelas A');
        $h = $this->makeHalaqah($term, 'Halaqah 1');
        $student = $this->makeStudent('Ahmad', 'N001');

        $enrollment = ClassEnrollment::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $ct->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);

        \Livewire\Livewire::actingAs($this->admin())
            ->test(HalaqahPlacementBoard::class)
            ->set('academicTermId', $term->id)
            ->call('assignToHalaqah', $student->id, $h->id);

        $this->assertDatabaseHas('tahfidz_halaqah_members', [
            'tahfidz_halaqah_id' => $h->id,
            'student_id' => $student->id,
            'class_enrollment_id' => $enrollment->id,
            'status' => 'active',
        ]);
    }

    public function test_moving_halaqah_soft_moves_old_member(): void
    {
        $term = $this->makeTerm();
        $h1 = $this->makeHalaqah($term, 'Halaqah 1');
        $h2 = $this->makeHalaqah($term, 'Halaqah 2');
        $student = $this->makeStudent('Ahmad', 'N001');

        $page = \Livewire\Livewire::actingAs($this->admin())->test(HalaqahPlacementBoard::class)
            ->set('academicTermId', $term->id);

        $page->call('assignToHalaqah', $student->id, $h1->id);
        $page->call('assignToHalaqah', $student->id, $h2->id);

        // Satu member aktif saja di periode ini.
        $active = TahfidzHalaqahMember::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->whereHas('halaqah', fn ($q) => $q->where('academic_term_id', $term->id))
            ->get();
        $this->assertCount(1, $active);
        $this->assertSame($h2->id, (int) $active->first()->tahfidz_halaqah_id);

        // Member lama ditandai pindah + punya tanggal keluar.
        $this->assertDatabaseHas('tahfidz_halaqah_members', [
            'tahfidz_halaqah_id' => $h1->id,
            'student_id' => $student->id,
            'status' => 'moved',
        ]);
        $moved = TahfidzHalaqahMember::where('tahfidz_halaqah_id', $h1->id)->where('student_id', $student->id)->first();
        $this->assertNotNull($moved->left_at);
    }

    public function test_unassign_halaqah_soft_moves_without_new_member(): void
    {
        $term = $this->makeTerm();
        $h1 = $this->makeHalaqah($term, 'Halaqah 1');
        $student = $this->makeStudent('Ahmad', 'N001');

        $page = \Livewire\Livewire::actingAs($this->admin())->test(HalaqahPlacementBoard::class)
            ->set('academicTermId', $term->id);

        $page->call('assignToHalaqah', $student->id, $h1->id);
        $page->call('assignToHalaqah', $student->id, null);

        $this->assertSame(0, TahfidzHalaqahMember::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->whereHas('halaqah', fn ($q) => $q->where('academic_term_id', $term->id))
            ->count());
        $this->assertDatabaseHas('tahfidz_halaqah_members', [
            'tahfidz_halaqah_id' => $h1->id,
            'student_id' => $student->id,
            'status' => 'moved',
        ]);
    }

    public function test_kepala_sekolah_forbidden_on_halaqah_board(): void
    {
        $this->actingAs($this->userWithRole('kepala_sekolah'))
            ->get('/admin/halaqah-placement-board')
            ->assertForbidden();
    }

    public function test_kabag_tahfidz_can_access_halaqah_board(): void
    {
        $this->makeTerm(); // agar mount() menemukan periode aktif

        $this->actingAs($this->userWithRole('kabag_tahfidz'))
            ->get('/admin/halaqah-placement-board')
            ->assertOk();
    }

    public function test_admin_can_access_class_board_page(): void
    {
        $this->makeTerm();

        $this->actingAs($this->admin())
            ->get('/admin/class-placement-board')
            ->assertOk();
    }
}