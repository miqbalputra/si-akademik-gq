<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentResult;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahScore;
use App\Models\DiniyyahScoreComponent;
use App\Models\DiniyyahSubject;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\DiniyyahAssessmentWorkflow;
use App\Services\DiniyyahInputProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DiniyyahMonitoringAndWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_summary_counts_complete_and_incomplete_students(): void
    {
        [$assessmentSet, $enrollments] = $this->makeAssessmentWithEnrollments(2);
        DiniyyahAssessmentResult::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'class_enrollment_id' => $enrollments[0]->id,
            'final_score' => 80,
            'is_complete' => true,
        ]);

        $summary = app(DiniyyahInputProgressService::class)->summary($assessmentSet);

        $this->assertSame(2, $summary['total_students']);
        $this->assertSame(1, $summary['complete_students']);
        $this->assertSame(1, $summary['incomplete_students']);
        $this->assertSame(50.0, $summary['progress_percentage']);
    }

    public function test_kabag_diniyyah_can_open_monitoring_page(): void
    {
        Role::firstOrCreate(['name' => 'kabag_diniyyah', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('kabag_diniyyah');

        $this->actingAs($user)
            ->get(route('diniyyah.monitoring.index'))
            ->assertOk()
            ->assertSee('Monitoring Input Nilai');
    }

    public function test_guru_cannot_open_monitoring_page(): void
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('guru');

        $this->actingAs($user)
            ->get(route('diniyyah.monitoring.index'))
            ->assertForbidden();
    }

    public function test_assessment_workflow_submit_approve_and_revision(): void
    {
        [$assessmentSet, $enrollments] = $this->makeAssessmentWithEnrollments(1);
        $component = DiniyyahScoreComponent::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'code' => 'nilai_akhir',
            'name' => 'Nilai Akhir',
            'component_group' => 'final',
        ]);
        DiniyyahScore::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'diniyyah_score_component_id' => $component->id,
            'class_enrollment_id' => $enrollments[0]->id,
            'score' => 80,
            'status' => 'draft',
        ]);
        Role::firstOrCreate(['name' => 'kabag_diniyyah', 'guard_name' => 'web']);
        $validator = User::factory()->create();
        $validator->assignRole('kabag_diniyyah');

        $workflow = app(DiniyyahAssessmentWorkflow::class);
        $workflow->submit($assessmentSet);
        $this->assertSame('submitted', $assessmentSet->refresh()->status);
        $this->assertSame(1, $assessmentSet->scores()->where('status', 'submitted')->count());

        $workflow->approve($assessmentSet, $validator);
        $this->assertSame('validated', $assessmentSet->refresh()->status);
        $this->assertDatabaseHas('diniyyah_score_validations', [
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'validated_by' => $validator->id,
            'status' => 'approved',
        ]);

        $workflow->requestRevision($assessmentSet, $validator, 'Perbaiki nilai');
        $this->assertSame('needs_revision', $assessmentSet->refresh()->status);
        $this->assertDatabaseHas('diniyyah_score_validations', [
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'status' => 'needs_revision',
            'notes' => 'Perbaiki nilai',
        ]);
    }

    public function test_kabag_can_approve_submitted_assessment_from_monitoring(): void
    {
        [$assessmentSet] = $this->makeAssessmentWithEnrollments(1);
        $assessmentSet->update(['status' => 'submitted']);
        Role::firstOrCreate(['name' => 'kabag_diniyyah', 'guard_name' => 'web']);
        $validator = User::factory()->create();
        $validator->assignRole('kabag_diniyyah');

        $this->actingAs($validator)
            ->post(route('diniyyah.assessment-sets.approve', $assessmentSet), [
                'notes' => 'Sudah sesuai',
            ])
            ->assertRedirect(route('diniyyah.monitoring.index'));

        $this->assertSame('validated', $assessmentSet->refresh()->status);
        $this->assertDatabaseHas('diniyyah_score_validations', [
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'validated_by' => $validator->id,
            'status' => 'approved',
            'notes' => 'Sudah sesuai',
        ]);
    }

    public function test_kabag_can_request_revision_from_monitoring(): void
    {
        [$assessmentSet] = $this->makeAssessmentWithEnrollments(1);
        $assessmentSet->update(['status' => 'submitted']);
        Role::firstOrCreate(['name' => 'kabag_diniyyah', 'guard_name' => 'web']);
        $validator = User::factory()->create();
        $validator->assignRole('kabag_diniyyah');

        $this->actingAs($validator)
            ->post(route('diniyyah.assessment-sets.revision', $assessmentSet), [
                'notes' => 'Lengkapi nilai',
            ])
            ->assertRedirect(route('diniyyah.monitoring.index'));

        $this->assertSame('needs_revision', $assessmentSet->refresh()->status);
        $this->assertDatabaseHas('diniyyah_score_validations', [
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'validated_by' => $validator->id,
            'status' => 'needs_revision',
            'notes' => 'Lengkapi nilai',
        ]);
    }

    public function test_kabag_cannot_approve_assessment_that_has_not_been_submitted(): void
    {
        [$assessmentSet] = $this->makeAssessmentWithEnrollments(1);
        Role::firstOrCreate(['name' => 'kabag_diniyyah', 'guard_name' => 'web']);
        $validator = User::factory()->create();
        $validator->assignRole('kabag_diniyyah');

        $this->actingAs($validator)
            ->post(route('diniyyah.assessment-sets.approve', $assessmentSet))
            ->assertForbidden();

        $this->assertSame('active', $assessmentSet->refresh()->status);
    }

    /** @return array{DiniyyahAssessmentSet, array<int, ClassEnrollment>} */
    private function makeAssessmentWithEnrollments(int $studentCount): array
    {
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Semester Genap', 'semester' => 'genap']);
        $classroom = Classroom::create(['name' => 'Mustawa 1 Ikhwan']);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => 'Mustawa 1 Ikhwan',
        ]);
        $subject = DiniyyahSubject::create(['code' => 'fiqih', 'name' => 'Fiqih', 'default_assessment_method' => 'weighted']);
        $classSubject = DiniyyahClassSubject::create([
            'classroom_term_id' => $classroomTerm->id,
            'subject_id' => $subject->id,
            'assessment_method' => 'weighted',
        ]);
        $assessmentSet = DiniyyahAssessmentSet::create([
            'diniyyah_class_subject_id' => $classSubject->id,
            'title' => 'Fiqih',
            'assessment_method' => 'weighted',
            'status' => 'active',
        ]);
        $enrollments = [];

        for ($i = 1; $i <= $studentCount; $i++) {
            $student = Student::create(['name' => "Santri {$i}", 'gender' => 'male', 'nis' => "00{$i}"]);
            $enrollments[] = ClassEnrollment::create([
                'academic_term_id' => $term->id,
                'classroom_term_id' => $classroomTerm->id,
                'student_id' => $student->id,
            ]);
        }

        return [$assessmentSet, $enrollments];
    }
}
