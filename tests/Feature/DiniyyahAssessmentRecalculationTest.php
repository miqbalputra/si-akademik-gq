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
use App\Services\DiniyyahScoreCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiniyyahAssessmentRecalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_recalculates_all_active_students_in_assessment_class(): void
    {
        [$assessmentSet, $enrollments] = $this->makeWeightedAssessmentWithEnrollments(2);

        foreach ($assessmentSet->components as $component) {
            foreach ($enrollments as $enrollment) {
                DiniyyahScore::create([
                    'diniyyah_assessment_set_id' => $assessmentSet->id,
                    'diniyyah_score_component_id' => $component->id,
                    'class_enrollment_id' => $enrollment->id,
                    'score' => 80,
                ]);
            }
        }

        $count = app(DiniyyahScoreCalculator::class)->calculateAssessmentSet($assessmentSet);

        $this->assertSame(2, $count);
        $this->assertSame(2, $assessmentSet->results()->where('is_complete', true)->count());
        $this->assertSame(2, $assessmentSet->results()->where('final_score', 80)->count());
    }

    /**
     * @return array{DiniyyahAssessmentSet, array<int, ClassEnrollment>}
     */
    private function makeWeightedAssessmentWithEnrollments(int $studentCount): array
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
        $assessmentSet = DiniyyahAssessmentSet::create([
            'diniyyah_class_subject_id' => $classSubject->id,
            'title' => 'Fiqih',
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
            'status' => 'active',
        ]);

        foreach ([['daily', 'harian'], ['exam', 'ujian']] as $index => [$group, $code]) {
            DiniyyahScoreComponent::create([
                'diniyyah_assessment_set_id' => $assessmentSet->id,
                'code' => $code,
                'name' => ucfirst($code),
                'component_group' => $group,
                'sort_order' => ($index + 1) * 10,
                'is_required' => true,
            ]);
        }

        $enrollments = [];
        for ($i = 1; $i <= $studentCount; $i++) {
            $student = Student::create(['name' => "Santri {$i}", 'gender' => 'male', 'nis' => "00{$i}"]);
            $enrollments[] = ClassEnrollment::create([
                'academic_term_id' => $term->id,
                'classroom_term_id' => $classroomTerm->id,
                'student_id' => $student->id,
            ]);
        }

        return [$assessmentSet->load('components'), $enrollments];
    }
}
