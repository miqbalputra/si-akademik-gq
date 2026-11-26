<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\HomeroomAssignment;
use App\Models\School;
use App\Models\TasmiExaminerAssignment;
use App\Models\Teacher;
use App\Models\User;
use App\Services\DiniyyahLedgerAccess;
use App\Support\GuruNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(): array
    {
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']));
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => 'Guru Navigasi', 'gender' => 'male', 'status' => 'active']);

        return [$user, $teacher];
    }

    private function classroom(): ClassroomTerm
    {
        $school = School::create(['name' => 'Sekolah Navigasi']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil', 'is_active' => true]);
        $class = Classroom::create(['name' => 'Kelas Navigasi']);

        return ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $class->id, 'name' => 'Kelas Navigasi']);
    }

    public function test_all_existing_teacher_destinations_have_one_home_and_follow_existing_conditions(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->classroom();
        HomeroomAssignment::create(['teacher_id' => $teacher->id, 'classroom_term_id' => $class->id]);
        TasmiExaminerAssignment::create(['teacher_id' => $teacher->id, 'academic_term_id' => $class->academic_term_id, 'status' => 'active']);
        $user->assignRole(Role::firstOrCreate(['name' => 'kabag_tahfidz', 'guard_name' => 'web']));
        config(['rpp_sync.enabled' => false]);

        $groups = app(GuruNavigation::class)->groups($user, true);
        $this->assertSame(['Mengajar', 'Tahfidz & Tasmi', 'Kelas Saya', 'RPP & Program Semester', 'Riwayat Saya', 'Koordinasi', 'Rapor'], array_column($groups, 'label'));
        $items = collect($groups)->pluck('items')->flatten(1);
        $expectedRoutes = [
            'guru.diniyyah-journals.index', 'guru.diniyyah-substitute-journals.index',
            'guru.diniyyah-tafsir-journals.index', 'guru.diniyyah-substitute-tafsir-journals.index',
            'attendance.index', 'guru.diniyyah-scores.index', 'guru.kkm.index', 'guru.calendar',
            'guru.report-workflow', 'guru.tahfidz-report.index', 'diniyyah.ledger.index', 'report-cards.index',
            'guru.tahfidz.index', 'guru.tasmi.index', 'guru.tasmi-wali.index',
            'wali.diniyyah-journals.index', 'wali.jp-recap.index', 'guru.rpp.index',
            'guru.rpp.create', 'guru.rpp.references', 'guru.rpp.promes', 'guru.rpp.trash',
            'guru.attendance-report.index', 'guru.performa', 'guru.diniyyah-journals.riwayat',
            'guru.jadwal.riwayat', 'admin.tasmi-report.index',
        ];
        $this->assertEqualsCanonicalizing(array_map(fn ($route) => route($route), $expectedRoutes), $items->pluck('href')->all());
        $this->assertSame($items->count(), $items->unique('href')->count());
        $this->assertTrue(collect($groups)->firstWhere('label', 'Rapor')['default_open']);

        config(['rpp_sync.enabled' => true]);
        $synced = collect(app(GuruNavigation::class)->groups($user))->pluck('items')->flatten(1)->pluck('href');
        $this->assertNotContains(route('guru.rpp.create'), $synced);
        $this->assertNotContains(route('guru.rpp.trash'), $synced);
        $this->assertNotContains(route('guru.diniyyah-tafsir-journals.index'), $synced);
    }

    public function test_regular_teacher_keeps_rapor_input_without_homeroom_or_examiner_controls(): void
    {
        [$user] = $this->teacher();
        $this->classroom();
        $groups = app(GuruNavigation::class)->groups($user);
        $this->assertSame(['Alur Nilai & Rapor', 'Input Nilai Pelajaran', 'KKM Mata Pelajaran', 'Nilai Final Tahfidz Rapor'], array_column(collect($groups)->firstWhere('label', 'Rapor')['items'], 'label'));
        $links = collect($groups)->pluck('items')->flatten(1)->pluck('href');
        foreach (['diniyyah.ledger.index', 'report-cards.index', 'guru.tasmi.index', 'attendance.index', 'admin.tasmi-report.index'] as $route) {
            $this->assertNotContains(route($route), $links);
        }
        $this->actingAs($user)->get(route('guru.tahfidz-report.index'))->assertOk();
        $this->get(route('guru.tasmi.index'))->assertForbidden();
        $this->get(route('attendance.index'))->assertForbidden();
    }

    public function test_historical_homeroom_visibility_does_not_expand_destination_permissions(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->classroom();
        HomeroomAssignment::create(['teacher_id' => $teacher->id, 'classroom_term_id' => $class->id, 'ends_at' => today()->subDay()]);
        $groups = app(GuruNavigation::class)->groups($user);
        $this->assertContains('Kelas Saya', array_column($groups, 'label'));
        $this->assertFalse(app(DiniyyahLedgerAccess::class)->allows($user, $class->id));
        $this->assertNotContains(route('attendance.index'), collect($groups)->pluck('items')->flatten(1)->pluck('href'));
    }

    public function test_journal_history_and_substitute_tafsir_have_exactly_one_active_menu(): void
    {
        [$user] = $this->teacher();
        $items = collect(app(GuruNavigation::class)->groups($user, true))->pluck('items')->flatten(1);
        foreach ([
            'guru.diniyyah-journals.riwayat' => 'Riwayat Jurnal',
            'guru.diniyyah-journals.index' => 'Jurnal Mengajar',
            'guru.diniyyah-substitute-tafsir-journals.index' => 'Jurnal Pengganti Tafsir',
            'guru.tahfidz-report.monitor' => null,
        ] as $route => $expected) {
            $matches = $items->filter(fn ($item) => GuruNavigation::matches($item, $route));
            $this->assertSame($expected === null ? [] : [$expected], $matches->pluck('label')->values()->all());
        }
    }

    public function test_sidebar_storage_is_isolated_by_account_and_roles_and_no_teacher_links_are_added_without_a_teacher(): void
    {
        [$user] = $this->teacher();
        $navigation = app(GuruNavigation::class);
        $key = $navigation->storageKey($user);
        $user->assignRole(Role::firstOrCreate(['name' => 'kabag_tahfidz', 'guard_name' => 'web']));
        $this->assertNotSame($key, $navigation->storageKey($user));
        [$other] = $this->teacher();
        $this->assertNotSame($key, $navigation->storageKey($other));
        $other->teacher->delete();
        $other->unsetRelation('teacher');
        $links = collect($navigation->groups($other))->pluck('items')->flatten(1)->pluck('href');
        foreach (['guru.jadwal.riwayat', 'guru.rpp.index', 'guru.attendance-report.index', 'guru.diniyyah-substitute-journals.index'] as $route) {
            $this->assertNotContains(route($route), $links);
        }
    }
}
