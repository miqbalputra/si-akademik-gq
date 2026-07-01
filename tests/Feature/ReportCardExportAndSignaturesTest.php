<?php

namespace Tests\Feature;

use App\Models\ReportCard;
use App\Models\ReportCardSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportCardExportAndSignaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (["admin", "wali_santri", "kepala_sekolah", "guru", "kabag_diniyyah"] as $role) {
            Role::firstOrCreate(["name" => $role, "guard_name" => "web"]);
        }
    }

    public function test_admin_can_download_report_card_pdf(): void
    {
        $admin = $this->adminUser();
        $reportCard = $this->createPublishedReportCard();

        $response = $this->actingAs($admin)
            ->get(route('report-cards.download-pdf', $reportCard));

        $response->assertOk();
        $contentType = $response->headers->get('content-type');
        $this->assertTrue(
            str_contains($contentType, 'pdf') || str_contains($contentType, 'application/pdf') || str_contains($contentType, 'text/html'),
            "Expected PDF or HTML content-type, got: $contentType"
        );
    }

    public function test_guardian_can_download_own_published_report_card_pdf(): void
    {
        [$guardian, $student] = $this->guardianWithStudent();
        $reportCard = $this->createPublishedReportCard(['student_id' => $student->id]);

        $response = $this->actingAs($guardian->user)
            ->get(route('report-cards.download-pdf', $reportCard));

        $response->assertOk();
    }

    public function test_guardian_cannot_download_other_student_report_card_pdf(): void
    {
        [$guardian] = $this->guardianWithStudent();
        $reportCard = $this->createPublishedReportCard();

        $response = $this->actingAs($guardian->user)
            ->get(route('report-cards.download-pdf', $reportCard));

        $response->assertForbidden();
    }

    public function test_report_card_signature_can_be_created_and_attached(): void
    {
        $admin = $this->adminUser();
        $reportCard = $this->createPublishedReportCard();

        $signature = ReportCardSignature::create([
            'report_card_id' => $reportCard->id,
            'role_label' => 'Wali Kelas',
            'person_name' => 'Ustadz Ahmad',
            'sort_order' => 10,
        ]);

        $this->assertSame('Wali Kelas', $signature->role_label);
        $this->assertSame('Ustadz Ahmad', $signature->person_name);
        $this->assertSame($reportCard->id, $signature->report_card_id);
    }

    private function adminUser()
    {
        $user = \App\Models\User::create([
            'name' => 'Test Admin',
            'email' => 'test-admin-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('admin');

        return $user;
    }

    private function guardianWithStudent(): array
    {
        $user = \App\Models\User::create([
            'name' => 'Test Wali',
            'email' => 'test-wali-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('wali_santri');

        $guardian = \App\Models\Guardian::create([
            'user_id' => $user->id,
            'name' => 'Bapak Test',
            'nik' => '3173' . str_pad(random_int(0, 99999999999), 11, '0'),
            'gender' => 'male',
            'phone' => '081200000000',
            'status' => 'active',
        ]);

        $student = \App\Models\Student::create([
            'name' => 'Anak Test',
            'gender' => 'male',
            'nis' => 'TEST-' . uniqid(),
            'status' => 'active',
        ]);

        $guardian->students()->attach($student->id, [
            'relationship' => 'father',
            'is_primary' => true,
            'can_login' => true,
        ]);

        return [$guardian, $student];
    }

    private function createPublishedReportCard(array $overrides = []): ReportCard
    {
        $school = \App\Models\School::create(['name' => 'Test School']);
        $year = \App\Models\AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = \App\Models\AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil']);
        $classroom = \App\Models\Classroom::create(['name' => 'Test Class']);
        $classroomTerm = \App\Models\ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => 'Test Class']);

        $student = isset($overrides['student_id'])
            ? \App\Models\Student::find($overrides['student_id'])
            : \App\Models\Student::create(['name' => 'Student Test', 'gender' => 'male', 'nis' => 'TEST-RC-' . uniqid(), 'status' => 'active']);

        $enrollment = \App\Models\ClassEnrollment::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'student_id' => $student->id,
        ]);

        return ReportCard::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'class_enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'report_type' => 'diniyyah',
            'status' => 'published',
            'total_score' => 85.00,
            'average_score' => 85.00,
            'rank_in_class' => 1,
            'published_at' => now(),
        ]);
    }
}