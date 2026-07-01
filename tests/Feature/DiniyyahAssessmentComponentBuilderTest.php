<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\School;
use App\Services\DiniyyahAssessmentComponentBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiniyyahAssessmentComponentBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_weighted_default_components_without_duplicates(): void
    {
        $assessmentSet = $this->makeAssessmentSet('weighted');

        app(DiniyyahAssessmentComponentBuilder::class)->createDefaults($assessmentSet);
        app(DiniyyahAssessmentComponentBuilder::class)->createDefaults($assessmentSet);

        $this->assertSame(5, $assessmentSet->components()->count());
        $this->assertSame(4, $assessmentSet->components()->where('component_group', 'daily')->count());
        $this->assertSame(1, $assessmentSet->components()->where('component_group', 'exam')->count());
    }

    public function test_it_creates_direct_final_default_component(): void
    {
        $assessmentSet = $this->makeAssessmentSet('direct_final');

        app(DiniyyahAssessmentComponentBuilder::class)->createDefaults($assessmentSet);

        $this->assertSame(1, $assessmentSet->components()->count());
        $this->assertDatabaseHas('diniyyah_score_components', [
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'code' => 'nilai_akhir',
            'component_group' => 'final',
        ]);
    }

    private function makeAssessmentSet(string $method): DiniyyahAssessmentSet
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
            'code' => $method,
            'name' => $method === 'direct_final' ? 'Suluk' : 'Fiqih',
            'default_assessment_method' => $method,
        ]);
        $classSubject = DiniyyahClassSubject::create([
            'classroom_term_id' => $classroomTerm->id,
            'subject_id' => $subject->id,
            'assessment_method' => $method,
            'kkm' => 70,
            'daily_weight' => $method === 'weighted' ? 40 : null,
            'exam_weight' => $method === 'weighted' ? 60 : null,
        ]);

        return DiniyyahAssessmentSet::create([
            'diniyyah_class_subject_id' => $classSubject->id,
            'title' => $subject->name,
            'assessment_method' => $method,
            'kkm' => 70,
            'daily_weight' => $method === 'weighted' ? 40 : null,
            'exam_weight' => $method === 'weighted' ? 60 : null,
            'status' => 'active',
        ]);
    }
}
