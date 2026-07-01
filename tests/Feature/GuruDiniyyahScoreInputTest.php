<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahScoreComponent;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruDiniyyahScoreInputTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_teacher_can_open_score_input_page(): void
    {
        [$assessmentSet, $teacher] = $this->makeInputContext();

        $this->actingAs($teacher->user)
            ->get(route('guru.diniyyah-scores.edit', $assessmentSet))
            ->assertOk()
            ->assertSee('Fiqih')
            ->assertSee('Santri 1');
    }

    public function test_unassigned_teacher_cannot_open_score_input_page(): void
    {
        [$assessmentSet] = $this->makeInputContext();
        $otherTeacher = $this->makeTeacher('Guru Lain');

        $this->actingAs($otherTeacher->user)
            ->get(route('guru.diniyyah-scores.edit', $assessmentSet))
            ->assertForbidden();
    }

    public function test_assigned_teacher_can_store_scores(): void
    {
        [$assessmentSet, $teacher, $enrollment, $dailyComponent, $examComponent] = $this->makeInputContext();

        $this->actingAs($teacher->user)
            ->put(route('guru.diniyyah-scores.update', $assessmentSet), [
                'scores' => [
                    $enrollment->id => [
                        $dailyComponent->id => 80,
                        $examComponent->id => 90,
                    ],
                ],
            ])
            ->assertRedirect(route('guru.diniyyah-scores.edit', $assessmentSet));

        $this->assertDatabaseHas('diniyyah_scores', [
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'diniyyah_score_component_id' => $dailyComponent->id,
            'class_enrollment_id' => $enrollment->id,
            'score' => 80,
        ]);
        $this->assertDatabaseHas('diniyyah_assessment_results', [
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'class_enrollment_id' => $enrollment->id,
            'final_score' => 86,
            'is_complete' => true,
        ]);
    }

    public function test_assigned_teacher_can_submit_complete_scores(): void
    {
        [$assessmentSet, $teacher, $enrollment, $dailyComponent, $examComponent] = $this->makeInputContext();

        $this->actingAs($teacher->user)
            ->put(route('guru.diniyyah-scores.update', $assessmentSet), [
                'scores' => [
                    $enrollment->id => [
                        $dailyComponent->id => 80,
                        $examComponent->id => 90,
                    ],
                ],
            ]);

        $this->actingAs($teacher->user)
            ->post(route('guru.diniyyah-scores.submit', $assessmentSet))
            ->assertRedirect(route('guru.diniyyah-scores.index'));

        $this->assertSame('submitted', $assessmentSet->refresh()->status);
        $this->assertSame(2, $assessmentSet->scores()->where('status', 'submitted')->count());
    }

    public function test_assigned_teacher_cannot_submit_incomplete_scores(): void
    {
        [$assessmentSet, $teacher] = $this->makeInputContext();

        $this->actingAs($teacher->user)
            ->post(route('guru.diniyyah-scores.submit', $assessmentSet))
            ->assertRedirect(route('guru.diniyyah-scores.edit', $assessmentSet))
            ->assertSessionHasErrors('scores');

        $this->assertSame('active', $assessmentSet->refresh()->status);
    }

    public function test_assigned_teacher_cannot_update_submitted_scores(): void
    {
        [$assessmentSet, $teacher, $enrollment, $dailyComponent] = $this->makeInputContext();
        $assessmentSet->update(['status' => 'submitted']);

        $this->actingAs($teacher->user)
            ->put(route('guru.diniyyah-scores.update', $assessmentSet), [
                'scores' => [
                    $enrollment->id => [
                        $dailyComponent->id => 80,
                    ],
                ],
            ])
            ->assertForbidden();
    }

    private function makeTeacher(string $name): Teacher
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('guru');

        return Teacher::create(['user_id' => $user->id, 'name' => $name]);
    }

    /** @return array{DiniyyahAssessmentSet, Teacher, ClassEnrollment, DiniyyahScoreComponent, DiniyyahScoreComponent} */
    private function makeInputContext(): array
    {
        $teacher = $this->makeTeacher('Guru Fiqih');
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
        $subject = DiniyyahSubject::create([
            'code' => 'fiqih',
            'name' => 'Fiqih',
            'default_assessment_method' => 'weighted',
        ]);
        $classSubject = DiniyyahClassSubject::create([
            'classroom_term_id' => $classroomTerm->id,
            'subject_id' => $subject->id,
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
        ]);
        DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $classSubject->id,
            'teacher_id' => $teacher->id,
            'assignment_role' => 'primary',
        ]);
        $assessmentSet = DiniyyahAssessmentSet::create([
            'diniyyah_class_subject_id' => $classSubject->id,
            'title' => 'Fiqih',
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
            'status' => 'active',
        ]);
        $dailyComponent = DiniyyahScoreComponent::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'code' => 'harian',
            'name' => 'Harian',
            'component_group' => 'daily',
            'is_required' => true,
        ]);
        $examComponent = DiniyyahScoreComponent::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'code' => 'ujian',
            'name' => 'Ujian',
            'component_group' => 'exam',
            'is_required' => true,
        ]);

        return [$assessmentSet, $teacher, $enrollment, $dailyComponent, $examComponent];
    }
}
