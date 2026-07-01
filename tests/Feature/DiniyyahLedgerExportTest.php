<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use Spatie\Permission\Models\Role;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\DiniyyahScore;
use App\Models\DiniyyahScoreComponent;
use App\Models\DiniyyahSubject;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\School;
use App\Models\Student;
use App\Services\DiniyyahLedgerGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiniyyahLedgerExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (["admin", "wali_santri", "kepala_sekolah", "guru", "kabag_diniyyah"] as $role) {
            Role::firstOrCreate(["name" => $role, "guard_name" => "web"]);
        }
    }

    public function test_admin_can_export_ledger_as_excel(): void
    {
        $admin = $this->adminUser();
        $snapshot = $this->createLedgerSnapshot();

        $response = $this->actingAs($admin)
            ->get(route('diniyyah.ledger.export-excel', $snapshot));

        $response->assertOk();
        $contentType = $response->headers->get('content-type');
        $this->assertTrue(
            str_contains($contentType, 'excel') || str_contains($contentType, 'application/vnd'),
            "Expected Excel content-type, got: $contentType"
        );
    }

    public function test_kepala_sekolah_can_export_ledger(): void
    {
        $kepala = $this->kepalaSekolahUser();
        $snapshot = $this->createLedgerSnapshot();

        $response = $this->actingAs($kepala)
            ->get(route('diniyyah.ledger.export-excel', $snapshot));

        $response->assertOk();
    }

    public function test_guru_cannot_export_ledger(): void
    {
        $guru = $this->guruUser();
        $snapshot = $this->createLedgerSnapshot();

        $response = $this->actingAs($guru)
            ->get(route('diniyyah.ledger.export-excel', $snapshot));

        $response->assertForbidden();
    }

    private function adminUser()
    {
        $user = \App\Models\User::create([
            'name' => 'Admin',
            'email' => 'admin-export-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('admin');

        return $user;
    }

    private function kepalaSekolahUser()
    {
        $user = \App\Models\User::create([
            'name' => 'Kepala',
            'email' => 'kepala-export-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('kepala_sekolah');

        return $user;
    }

    private function guruUser()
    {
        $user = \App\Models\User::create([
            'name' => 'Guru',
            'email' => 'guru-export-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('guru');

        return $user;
    }

    private function createLedgerSnapshot(): DiniyyahLedgerSnapshot
    {
        $school = School::create(['name' => 'Test School']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil']);
        $classroom = Classroom::create(['name' => 'M1 Test']);
        $classroomTerm = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => 'M1 Test']);

        $student = Student::create(['name' => 'Santri Export', 'gender' => 'male', 'nis' => 'EXP-' . uniqid(), 'status' => 'active']);
        ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $classroomTerm->id, 'student_id' => $student->id]);

        $subject = DiniyyahSubject::create(['code' => 'fiqih', 'name' => 'Fiqih', 'default_assessment_method' => 'weighted']);
        $classSubject = DiniyyahClassSubject::create([
            'classroom_term_id' => $classroomTerm->id,
            'subject_id' => $subject->id,
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
            'appears_on_ledger' => true,
            'appears_on_report' => true,
            'is_active' => true,
        ]);

        $set = DiniyyahAssessmentSet::create([
            'diniyyah_class_subject_id' => $classSubject->id,
            'title' => 'Fiqih Export',
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
            'appears_on_ledger' => true,
            'appears_on_report' => true,
            'sort_order' => 10,
            'status' => 'validated',
        ]);

        // Create components and scores to make it complete
        $enrollment = ClassEnrollment::first();
        $components = [
            ['keaktifan', 'daily', 10, 80],
            ['ujian', 'exam', 20, 85],
        ];

        foreach ($components as [$code, $group, $sort, $score]) {
            $comp = DiniyyahScoreComponent::create([
                'diniyyah_assessment_set_id' => $set->id,
                'code' => $code,
                'name' => ucfirst($code),
                'component_group' => $group,
                'sort_order' => $sort,
                'is_required' => true,
            ]);

            DiniyyahScore::create([
                'diniyyah_assessment_set_id' => $set->id,
                'diniyyah_score_component_id' => $comp->id,
                'class_enrollment_id' => $enrollment->id,
                'score' => $score,
                'status' => 'validated',
            ]);
        }

        $generator = app(DiniyyahLedgerGenerator::class);

        return $generator->generate($classroomTerm);
    }
}