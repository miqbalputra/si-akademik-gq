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

class DiniyyahScoreCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_weighted_assessment_calculates_final_score(): void
    {
        [$assessmentSet, $enrollment] = $this->makeAssessmentContext('weighted');

        $components = [
            ['keaktifan_presensi', 'Keaktifan/Presensi', 'daily', 10, 80],
            ['ulangan_harian_1', 'Ulangan Harian 1', 'daily', 20, 90],
            ['ulangan_harian_2', 'Ulangan Harian 2', 'daily', 30, 70],
            ['nilai_tugas', 'Nilai Tugas', 'daily', 40, 100],
            ['nilai_ujian_mentah', 'Nilai Ujian', 'exam', 50, 85],
        ];

        foreach ($components as [$code, $name, $group, $sortOrder, $score]) {
            $component = DiniyyahScoreComponent::create([
                'diniyyah_assessment_set_id' => $assessmentSet->id,
                'code' => $code,
                'name' => $name,
                'component_group' => $group,
                'sort_order' => $sortOrder,
                'is_required' => true,
            ]);

            DiniyyahScore::create([
                'diniyyah_assessment_set_id' => $assessmentSet->id,
                'diniyyah_score_component_id' => $component->id,
                'class_enrollment_id' => $enrollment->id,
                'score' => $score,
            ]);
        }

        $result = app(DiniyyahScoreCalculator::class)->calculate($assessmentSet, $enrollment);

        $this->assertTrue($result->is_complete);
        $this->assertTrue($result->is_passed);
        $this->assertSame('85.00', $result->daily_raw_score);
        $this->assertSame('85.00', $result->exam_raw_score);
        $this->assertSame('34.00', $result->daily_weighted_score);
        $this->assertSame('51.00', $result->exam_weighted_score);
        $this->assertSame('85.00', $result->final_score);
    }

    public function test_direct_final_assessment_uses_final_component(): void
    {
        [$assessmentSet, $enrollment] = $this->makeAssessmentContext('direct_final');

        $component = DiniyyahScoreComponent::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'code' => 'nilai_akhir',
            'name' => 'Nilai Akhir',
            'component_group' => 'final',
            'sort_order' => 10,
            'is_required' => true,
        ]);

        DiniyyahScore::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'diniyyah_score_component_id' => $component->id,
            'class_enrollment_id' => $enrollment->id,
            'score' => 88,
        ]);

        $result = app(DiniyyahScoreCalculator::class)->calculate($assessmentSet, $enrollment);

        $this->assertTrue($result->is_complete);
        $this->assertSame('88.00', $result->final_score);
        $this->assertNull($result->daily_raw_score);
    }

    public function test_practical_assessment_calculates_weighted_final_score(): void
    {
        [$assessmentSet, $enrollment] = $this->makeAssessmentContext('practical');

        $components = [
            ['keaktifan_presensi', 'Keaktifan/Presensi', 'daily', 10, 80],
            ['ulangan_harian_1', 'Ulangan Harian 1', 'daily', 20, 90],
            ['ulangan_harian_2', 'Ulangan Harian 2', 'daily', 30, 70],
            ['nilai_tugas', 'Nilai Tugas', 'daily', 40, 100],
            ['nilai_ujian_mentah', 'Nilai Ujian', 'exam', 50, 85],
        ];

        foreach ($components as [$code, $name, $group, $sortOrder, $score]) {
            $component = DiniyyahScoreComponent::create([
                'diniyyah_assessment_set_id' => $assessmentSet->id,
                'code' => $code,
                'name' => $name,
                'component_group' => $group,
                'sort_order' => $sortOrder,
                'is_required' => true,
            ]);

            DiniyyahScore::create([
                'diniyyah_assessment_set_id' => $assessmentSet->id,
                'diniyyah_score_component_id' => $component->id,
                'class_enrollment_id' => $enrollment->id,
                'score' => $score,
            ]);
        }

        $result = app(DiniyyahScoreCalculator::class)->calculate($assessmentSet, $enrollment);

        $this->assertTrue($result->is_complete);
        $this->assertSame('85.00', $result->daily_raw_score);
        $this->assertSame('85.00', $result->exam_raw_score);
        $this->assertSame('34.00', $result->daily_weighted_score);
        $this->assertSame('51.00', $result->exam_weighted_score);
        $this->assertSame('85.00', $result->final_score);
        $this->assertSame('Wudhu dan Shalat', $assessmentSet->tested_material);
    }

    public function test_incomplete_weighted_assessment_has_no_final_score(): void
    {
        [$assessmentSet, $enrollment] = $this->makeAssessmentContext('weighted');

        DiniyyahScoreComponent::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'code' => 'keaktifan_presensi',
            'name' => 'Keaktifan/Presensi',
            'component_group' => 'daily',
            'sort_order' => 10,
            'is_required' => true,
        ]);

        DiniyyahScoreComponent::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'code' => 'nilai_ujian_mentah',
            'name' => 'Nilai Ujian',
            'component_group' => 'exam',
            'sort_order' => 20,
            'is_required' => true,
        ]);

        $result = app(DiniyyahScoreCalculator::class)->calculate($assessmentSet, $enrollment);

        $this->assertFalse($result->is_complete);
        $this->assertNull($result->final_score);
    }

    /**
     * @return array{DiniyyahAssessmentSet, ClassEnrollment}
     */
    private function makeAssessmentContext(string $method): array
    {
        $usesWeights = in_array($method, ['weighted', 'practical'], true);

        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Semester Genap', 'semester' => 'genap']);
        $classroom = Classroom::create(['name' => 'Mustawa 1 Ikhwan']);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => 'Mustawa 1 Ikhwan',
        ]);
        $student = Student::create(['name' => 'Ahmad', 'gender' => 'male', 'nis' => '001']);
        $enrollment = ClassEnrollment::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'student_id' => $student->id,
        ]);
        $subject = DiniyyahSubject::create([
            'code' => $method === 'practical' ? 'praktik_ibadah' : 'fiqih',
            'name' => $method === 'practical' ? 'Praktik Ibadah' : 'Fiqih',
            'default_assessment_method' => $method,
        ]);
        $classSubject = DiniyyahClassSubject::create([
            'classroom_term_id' => $classroomTerm->id,
            'subject_id' => $subject->id,
            'assessment_method' => $method,
            'kkm' => 70,
            'daily_weight' => $usesWeights ? 40 : null,
            'exam_weight' => $usesWeights ? 60 : null,
        ]);
        $assessmentSet = DiniyyahAssessmentSet::create([
            'diniyyah_class_subject_id' => $classSubject->id,
            'title' => $subject->name,
            'tested_material' => $method === 'practical' ? 'Wudhu dan Shalat' : null,
            'assessment_method' => $method,
            'kkm' => 70,
            'daily_weight' => $usesWeights ? 40 : null,
            'exam_weight' => $usesWeights ? 60 : null,
            'status' => 'active',
        ]);

        return [$assessmentSet, $enrollment];
    }
}
