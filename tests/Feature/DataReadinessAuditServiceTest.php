<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherRole;
use App\Models\User;
use App\Services\DataReadinessAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataReadinessAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_reports_missing_relationships_and_setup(): void
    {
        $this->makeAcademicContext();

        Student::create(['name' => 'Santri Tanpa Wali', 'gender' => 'male', 'nis' => '001']);
        Guardian::create(['name' => 'Wali Tanpa Akun', 'status' => 'active']);
        $teacherNoAssignment = Teacher::create(['name' => 'Guru Tanpa Tugas', 'email' => 'guru@example.com', 'status' => 'active']);
        TeacherRole::create(['teacher_id' => $teacherNoAssignment->id, 'role_type' => 'diniyyah_subject_teacher']);

        $audit = app(DataReadinessAuditService::class)->audit();
        $sections = collect($audit['sections'])->keyBy('key');

        $this->assertSame(1, $sections['students_without_guardians']['count']);
        $this->assertSame(1, $sections['students_without_active_enrollment']['count']);
        $this->assertSame(1, $sections['guardians_without_users']['count']);
        $this->assertSame(1, $sections['guardians_without_students']['count']);
        $this->assertSame(1, $sections['teachers_without_users']['count']);
        $this->assertSame(1, $sections['teachers_without_diniyyah_assignments']['count']);
        $this->assertSame(1, $sections['classroom_terms_without_enrollments']['count']);
        $this->assertSame(1, $sections['classroom_terms_without_subjects']['count']);
        $this->assertSame('needs_attention', $audit['status']);
    }

    public function test_audit_marks_ready_when_core_diniyyah_setup_is_complete(): void
    {
        [$term, $classroomTerm] = $this->makeAcademicContext();
        $user = User::factory()->create(['email' => 'akun@example.com']);
        $student = Student::create(['name' => 'Santri Lengkap', 'gender' => 'male', 'nis' => '001']);
        $guardian = Guardian::create(['user_id' => $user->id, 'name' => 'Wali Lengkap', 'status' => 'active']);
        $guardian->students()->attach($student->id, ['relationship' => 'ayah', 'is_primary' => true, 'can_login' => true]);
        ClassEnrollment::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);

        $teacherUser = User::factory()->create(['email' => 'guru@example.com']);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'name' => 'Guru Lengkap', 'email' => 'guru@example.com', 'status' => 'active']);
        $subject = DiniyyahSubject::create(['code' => 'AKD', 'name' => 'Akidah Akhlak']);
        $classSubject = DiniyyahClassSubject::create([
            'classroom_term_id' => $classroomTerm->id,
            'subject_id' => $subject->id,
            'assessment_method' => 'weighted',
            'kkm' => 75,
            'is_active' => true,
        ]);
        $classSubject->teacherAssignments()->create(['teacher_id' => $teacher->id]);
        $assessmentSet = DiniyyahAssessmentSet::create([
            'diniyyah_class_subject_id' => $classSubject->id,
            'title' => 'Akidah Akhlak',
            'assessment_method' => 'weighted',
            'kkm' => 75,
            'status' => 'active',
        ]);
        $assessmentSet->components()->create([
            'code' => 'nilai_akhir',
            'name' => 'Nilai Akhir',
            'component_group' => 'final',
            'sort_order' => 10,
            'is_required' => true,
        ]);

        $audit = app(DataReadinessAuditService::class)->audit();

        $this->assertSame(0, $audit['total_issues']);
        $this->assertSame('ready', $audit['status']);
        $this->assertSame(100.0, $audit['readiness_percentage']);
    }

    /** @return array{AcademicTerm, ClassroomTerm} */
    private function makeAcademicContext(): array
    {
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Semester Ganjil', 'semester' => 'ganjil']);
        $classroom = Classroom::create(['name' => 'Mustawa 1 Ikhwan']);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => 'Mustawa 1 Ikhwan',
            'status' => 'active',
        ]);

        return [$term, $classroomTerm];
    }
}
