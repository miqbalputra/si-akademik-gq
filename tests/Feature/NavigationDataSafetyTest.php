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
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NavigationDataSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigating_and_switching_workspaces_does_not_change_existing_academic_data(): void
    {
        $user = User::factory()->create();
        foreach (['admin', 'kabag_diniyyah', 'guru'] as $role) {
            $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
        }
        $school = School::create(['name' => 'Sekolah Pengujian']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil', 'is_active' => true, 'starts_at' => '2026-07-13', 'ends_at' => '2026-12-31']);
        $classroom = Classroom::create(['name' => 'Kelas Pengujian']);
        $class = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => 'Kelas Pengujian']);
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => 'Wali Kelas Pengujian', 'gender' => 'male', 'status' => 'active']);
        HomeroomAssignment::create(['teacher_id' => $teacher->id, 'classroom_term_id' => $class->id]);
        $student = Student::create(['name' => 'Santri Data Lama', 'nis' => '000123', 'gender' => 'male']);
        $enrollment = ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'student_id' => $student->id]);
        StudentAttendance::create(['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'class_enrollment_id' => $enrollment->id, 'student_id' => $student->id, 'attendance_date' => '2026-08-15', 'status' => 'sick']);
        DB::table('tahfidz_report_entries')->insert(['academic_term_id' => $term->id, 'student_id' => $student->id, 'version' => 1, 'juz_tested' => '30', 'uas_score' => 90, 'status' => 'final', 'input_by' => $user->id, 'recorded_by' => $user->id]);
        $subject = DB::table('diniyyah_subjects')->insertGetId(['name' => 'Fiqih', 'code' => 'FIQIH']);
        $classSubject = DB::table('diniyyah_class_subjects')->insertGetId(['classroom_term_id' => $class->id, 'subject_id' => $subject, 'assessment_method' => 'exam_only']);
        $set = DB::table('diniyyah_assessment_sets')->insertGetId(['diniyyah_class_subject_id' => $classSubject, 'title' => 'UAS Lama', 'assessment_method' => 'exam_only', 'status' => 'locked']);
        $component = DB::table('diniyyah_score_components')->insertGetId(['diniyyah_assessment_set_id' => $set, 'code' => 'UAS', 'name' => 'UAS', 'component_group' => 'exam']);
        DB::table('diniyyah_scores')->insert(['diniyyah_assessment_set_id' => $set, 'diniyyah_score_component_id' => $component, 'class_enrollment_id' => $enrollment->id, 'score' => 87.5, 'status' => 'locked']);
        $ledger = DB::table('diniyyah_ledger_snapshots')->insertGetId(['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'title' => 'Leger Lama', 'status' => 'locked']);
        DB::table('diniyyah_ledger_rows')->insert(['diniyyah_ledger_snapshot_id' => $ledger, 'class_enrollment_id' => $enrollment->id, 'student_name' => $student->name, 'student_nis' => $student->nis, 'total_diniyyah_score' => 87.5]);
        $report = DB::table('report_cards')->insertGetId(['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'class_enrollment_id' => $enrollment->id, 'student_id' => $student->id, 'status' => 'published', 'total_score' => 87.5]);
        DB::table('report_card_snapshots')->insert(['report_card_id' => $report, 'snapshot_data' => json_encode(['student_name' => $student->name, 'student_nis' => $student->nis, 'score' => 87.5]), 'generated_at' => now()]);
        $tables = ['students', 'academic_terms', 'class_enrollments', 'student_attendances', 'tahfidz_report_entries', 'diniyyah_class_subjects', 'diniyyah_assessment_sets', 'diniyyah_scores', 'diniyyah_ledger_snapshots', 'diniyyah_ledger_rows', 'report_cards', 'report_card_snapshots'];
        $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()]);

        $this->actingAs($user, 'admin')->get('/admin')->assertOk();
        $this->get(route('filament.admin.resources.report-cards.index'))->assertOk();
        $this->post(route('workspace.open-portal'), ['workspace' => 'kabag_diniyyah'])->assertRedirect();
        $this->get(route('kabag-diniyyah.dashboard'))->assertOk();
        $this->post(route('workspace.open-panel'), ['destination' => 'ReportCardResource'])->assertRedirect();
        $this->get(route('filament.admin.resources.report-cards.index'))->assertOk();

        $this->actingAs($user, 'web')->get(route('guru.performa'))->assertOk()->assertSee('Rapor Kelas Saya');
        $this->get(route('guru.calendar'))->assertOk();
        $this->get(route('guru.jadwal.riwayat'))->assertOk();
        $this->get(route('guru.tahfidz-report.index'))->assertOk();

        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson(), "Academic data changed in {$table}");
        }
    }
}
