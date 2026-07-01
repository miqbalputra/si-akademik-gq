<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahScore;
use App\Models\DiniyyahScoreComponent;
use App\Models\DiniyyahSubject;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiniyyahScoreAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_score_creation_and_update(): void
    {
        [$assessmentSet, $component, $enrollment] = $this->makeScoreContext();
        $user = User::factory()->create();

        $score = DiniyyahScore::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'diniyyah_score_component_id' => $component->id,
            'class_enrollment_id' => $enrollment->id,
            'score' => 80,
            'input_by' => $user->id,
        ]);

        $this->assertDatabaseHas('score_change_logs', [
            'score_table' => 'diniyyah_scores',
            'score_id' => $score->id,
            'old_score' => null,
            'new_score' => 80,
            'changed_by' => $user->id,
            'reason' => 'created',
        ]);

        $score->update(['score' => 90]);

        $this->assertDatabaseHas('score_change_logs', [
            'score_table' => 'diniyyah_scores',
            'score_id' => $score->id,
            'old_score' => 80,
            'new_score' => 90,
            'changed_by' => $user->id,
            'reason' => 'updated',
        ]);
    }

    public function test_it_does_not_log_when_score_value_is_not_changed(): void
    {
        [$assessmentSet, $component, $enrollment] = $this->makeScoreContext();

        $score = DiniyyahScore::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'diniyyah_score_component_id' => $component->id,
            'class_enrollment_id' => $enrollment->id,
            'score' => 80,
            'status' => 'draft',
        ]);

        $score->update(['status' => 'submitted']);

        $this->assertDatabaseCount('score_change_logs', 1);
    }

    /** @return array{DiniyyahAssessmentSet, DiniyyahScoreComponent, ClassEnrollment} */
    private function makeScoreContext(): array
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
        $student = Student::create(['name' => 'Santri 1', 'gender' => 'male', 'nis' => '001']);
        $enrollment = ClassEnrollment::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'student_id' => $student->id,
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
        $component = DiniyyahScoreComponent::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'code' => 'harian',
            'name' => 'Harian',
            'component_group' => 'daily',
        ]);

        return [$assessmentSet, $component, $enrollment];
    }
}
