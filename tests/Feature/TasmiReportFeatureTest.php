<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\HomeroomAssignment;
use App\Models\School;
use App\Models\Student;
use App\Models\TasmiExaminerAssignment;
use App\Models\TasmiRecord;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TasmiReportFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'kabag_tahfidz', 'guru'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function context(): array
    {
        $school = School::create(['name' => 'GQ']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $previousTerm = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Genap', 'semester' => 'genap', 'starts_at' => '2026-01-01', 'ends_at' => '2026-06-30', 'is_active' => false]);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil', 'starts_at' => '2026-07-01', 'ends_at' => '2026-12-31', 'is_active' => true]);
        $classroom = Classroom::create(['name' => 'Mustawa 1 Ikhwan', 'gender_group' => 'male', 'level_name' => 'M1', 'sort_order' => 1, 'is_active' => true]);
        $classroomTerm = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => $classroom->name, 'status' => 'active']);
        $previousClassroomTerm = ClassroomTerm::create(['academic_term_id' => $previousTerm->id, 'classroom_id' => $classroom->id, 'name' => $classroom->name, 'status' => 'inactive']);

        $pjUser = User::factory()->create(['name' => 'PJ Tasmi']);
        $pjUser->assignRole('guru');
        $pj = Teacher::create(['user_id' => $pjUser->id, 'name' => 'PJ Tasmi', 'gender' => 'male', 'niy' => 'PJ-01', 'status' => 'active']);
        TasmiExaminerAssignment::create(['academic_term_id' => $term->id, 'teacher_id' => $pj->id, 'status' => 'active']);

        $otherPjUser = User::factory()->create(['name' => 'PJ Lain']);
        $otherPjUser->assignRole('guru');
        $otherPj = Teacher::create(['user_id' => $otherPjUser->id, 'name' => 'PJ Lain', 'gender' => 'male', 'niy' => 'PJ-02', 'status' => 'active']);

        $waliUser = User::factory()->create(['name' => 'Wali Kelas']);
        $waliUser->assignRole('guru');
        $wali = Teacher::create(['user_id' => $waliUser->id, 'name' => 'Wali Kelas', 'gender' => 'male', 'niy' => 'WK-01', 'status' => 'active']);
        HomeroomAssignment::create(['classroom_term_id' => $classroomTerm->id, 'teacher_id' => $wali->id, 'starts_at' => '2026-07-01']);
        HomeroomAssignment::create(['classroom_term_id' => $previousClassroomTerm->id, 'teacher_id' => $wali->id, 'starts_at' => '2026-01-01', 'ends_at' => '2026-06-30']);

        $kabag = User::factory()->create(['name' => 'Kabag Tahfidz']);
        $kabag->assignRole('kabag_tahfidz');

        return compact('term', 'previousTerm', 'classroomTerm', 'previousClassroomTerm', 'pjUser', 'pj', 'otherPjUser', 'otherPj', 'waliUser', 'wali', 'kabag');
    }

    private function record(array $ctx, Student $student, array $overrides = []): TasmiRecord
    {
        $term = $overrides['academic_term'] ?? $ctx['term'];
        $classroomTerm = $overrides['classroom_term'] ?? $ctx['classroomTerm'];
        $examiner = $overrides['examiner'] ?? $ctx['pj'];
        $inputBy = $overrides['input_by'] ?? $ctx['pjUser'];
        $enrollment = ClassEnrollment::firstOrCreate([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'student_id' => $student->id,
        ], ['status' => 'active']);

        return TasmiRecord::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'class_enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'examiner_teacher_id' => $examiner->id,
            'exam_type' => $overrides['exam_type'] ?? '1_juz',
            'juz_start' => $overrides['juz_start'] ?? 30,
            'juz_end' => $overrides['juz_end'] ?? 30,
            'exam_date' => $overrides['exam_date'] ?? '2026-08-15',
            'predicate' => $overrides['predicate'] ?? 'mumtaz',
            'notes' => $overrides['notes'] ?? null,
            'input_by' => $inputBy->id,
            'input_at' => now(),
            'last_updated_by' => $inputBy->id,
        ]);
    }

    public function test_examiner_report_covers_own_history_and_exports_without_other_examiner_data(): void
    {
        $ctx = $this->context();
        $ownCurrent = Student::create(['name' => 'Santri PJ Kini', 'gender' => 'male', 'nis' => '101', 'status' => 'active']);
        $ownPast = Student::create(['name' => 'Santri PJ Lama', 'gender' => 'male', 'nis' => '102', 'status' => 'active']);
        $other = Student::create(['name' => 'Santri PJ Lain', 'gender' => 'male', 'nis' => '103', 'status' => 'active']);
        $this->record($ctx, $ownCurrent);
        $this->record($ctx, $ownPast, ['academic_term' => $ctx['previousTerm'], 'classroom_term' => $ctx['previousClassroomTerm'], 'exam_date' => '2026-06-15']);
        $this->record($ctx, $other, ['examiner' => $ctx['otherPj'], 'input_by' => $ctx['otherPjUser'], 'exam_date' => '2026-08-16']);

        $this->actingAs($ctx['pjUser'])->get(route('guru.tasmi.records'))
            ->assertOk()
            ->assertSee('Santri PJ Kini')
            ->assertSee('Santri PJ Lama')
            ->assertDontSee('Santri PJ Lain');

        $this->actingAs($ctx['pjUser'])->get(route('guru.tasmi.export', 'xlsx'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_wali_class_sees_only_records_within_homeroom_assignment_period_and_can_dismiss_reminder(): void
    {
        $ctx = $this->context();
        $visible = Student::create(['name' => 'Santri Wali', 'gender' => 'male', 'nis' => '201', 'status' => 'active']);
        $visibleRecord = $this->record($ctx, $visible);

        $otherClass = Classroom::create(['name' => 'Mustawa 2 Ikhwan', 'gender_group' => 'male', 'level_name' => 'M2', 'sort_order' => 2, 'is_active' => true]);
        $otherClassTerm = ClassroomTerm::create(['academic_term_id' => $ctx['term']->id, 'classroom_id' => $otherClass->id, 'name' => $otherClass->name, 'status' => 'active']);
        $hidden = Student::create(['name' => 'Santri Bukan Wali', 'gender' => 'male', 'nis' => '202', 'status' => 'active']);
        $this->record($ctx, $hidden, ['classroom_term' => $otherClassTerm, 'exam_date' => '2026-08-16']);

        $this->actingAs($ctx['waliUser'])->get(route('guru.tasmi-wali.index'))
            ->assertOk()
            ->assertSee('Santri Wali')
            ->assertDontSee('Santri Bukan Wali');

        $this->actingAs($ctx['waliUser'])->get(route('guru.dashboard'))
            ->assertOk()
            ->assertSee("Hasil Tasmi' baru", false);

        $this->actingAs($ctx['waliUser'])->postJson(route('guru.tasmi-wali.reminder.dismiss'))
            ->assertOk();
        $this->assertDatabaseHas('panel_user_preferences', ['user_id' => $ctx['waliUser']->id, 'panel_key' => 'guru-tasmi-wali-reminder']);

        $this->actingAs($ctx['waliUser'])->get(route('guru.dashboard'))
            ->assertOk()
            ->assertDontSee("Hasil Tasmi' baru", false)
            ->assertSee("hasil Tasmi' belum dibuka", false);

        $this->travel(1)->seconds();
        $visibleRecord->update(['predicate' => 'jayyid']);

        $this->actingAs($ctx['waliUser'])->get(route('guru.dashboard'))
            ->assertOk()
            ->assertSee("Hasil Tasmi' baru", false)
            ->assertSee('Santri Wali');

        $this->actingAs($ctx['waliUser'])->get(route('guru.tasmi-wali.show', $visibleRecord))
            ->assertOk()
            ->assertSee('Santri Wali');
        $this->assertDatabaseHas('panel_notifications', [
            'user_id' => $ctx['waliUser']->id,
            'link_url' => route('guru.tasmi-wali.show', $visibleRecord),
            'status' => 'read',
        ]);
    }

    public function test_kabag_can_read_and_export_all_reports_but_cannot_create_tasmi_record(): void
    {
        $ctx = $this->context();
        $first = Student::create(['name' => 'Santri Laporan Satu', 'gender' => 'male', 'nis' => '301', 'status' => 'active']);
        $second = Student::create(['name' => 'Santri Laporan Dua', 'gender' => 'male', 'nis' => '302', 'status' => 'active']);
        $firstRecord = $this->record($ctx, $first);
        $this->record($ctx, $second, ['examiner' => $ctx['otherPj'], 'input_by' => $ctx['otherPjUser'], 'exam_date' => '2026-08-16']);

        $this->actingAs($ctx['kabag'])->get(route('admin.tasmi-report.index'))
            ->assertOk()
            ->assertSee('Santri Laporan Satu')
            ->assertSee('Santri Laporan Dua');
        $this->actingAs($ctx['kabag'])->get(route('admin.tasmi-report.show', $firstRecord))->assertOk()->assertSee('Riwayat perubahan');
        $this->actingAs($ctx['kabag'])->get(route('admin.tasmi-report.export', 'pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($ctx['kabag'])->get('/admin/tasmi-records/create')->assertForbidden();
    }
}
