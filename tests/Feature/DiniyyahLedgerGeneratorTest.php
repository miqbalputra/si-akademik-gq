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
use App\Models\DiniyyahSubject;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Services\DiniyyahLedgerGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiniyyahLedgerGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_ledger_rows_cells_totals_and_ranks(): void
    {
        [$classroomTerm, $assessmentSets, $enrollments] = $this->makeLedgerContext();

        DiniyyahAssessmentResult::create([
            'diniyyah_assessment_set_id' => $assessmentSets[0]->id,
            'class_enrollment_id' => $enrollments[0]->id,
            'final_score' => 80,
            'is_complete' => true,
        ]);
        DiniyyahAssessmentResult::create([
            'diniyyah_assessment_set_id' => $assessmentSets[1]->id,
            'class_enrollment_id' => $enrollments[0]->id,
            'final_score' => 90,
            'is_complete' => true,
        ]);
        DiniyyahAssessmentResult::create([
            'diniyyah_assessment_set_id' => $assessmentSets[0]->id,
            'class_enrollment_id' => $enrollments[1]->id,
            'final_score' => 70,
            'is_complete' => true,
        ]);
        DiniyyahAssessmentResult::create([
            'diniyyah_assessment_set_id' => $assessmentSets[1]->id,
            'class_enrollment_id' => $enrollments[1]->id,
            'final_score' => 75,
            'is_complete' => true,
        ]);
        foreach ([
            [$enrollments[0], '2026-01-01', StudentAttendance::STATUS_SICK],
            [$enrollments[0], '2026-01-02', StudentAttendance::STATUS_SICK],
            [$enrollments[0], '2026-01-03', StudentAttendance::STATUS_PERMISSION],
            [$enrollments[0], '2026-01-04', StudentAttendance::STATUS_ABSENT],
            [$enrollments[0], '2026-01-05', StudentAttendance::STATUS_PRESENT],
            [$enrollments[0], '2026-01-06', StudentAttendance::STATUS_HOLIDAY],
            [$enrollments[1], '2026-01-01', StudentAttendance::STATUS_ABSENT],
        ] as [$enrollment, $date, $status]) {
            StudentAttendance::create([
                'academic_term_id' => $enrollment->academic_term_id,
                'classroom_term_id' => $enrollment->classroom_term_id,
                'class_enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'attendance_date' => $date,
                'status' => $status,
            ]);
        }

        $snapshot = app(DiniyyahLedgerGenerator::class)->generate($classroomTerm);

        $this->assertSame(2, $snapshot->rows()->count());
        $this->assertSame(10, $snapshot->rows()->withCount('cells')->get()->sum('cells_count'));

        $firstRow = $snapshot->rows()->with('cells')->where('class_enrollment_id', $enrollments[0]->id)->first();
        $secondRow = $snapshot->rows()->where('class_enrollment_id', $enrollments[1]->id)->first();

        $this->assertSame('170.00', $firstRow->total_diniyyah_score);
        $this->assertSame('85.00', $firstRow->average_diniyyah_score);
        $this->assertSame(1, $firstRow->rank_in_class);
        $this->assertSame(2, $secondRow->rank_in_class);
        $this->assertSame(3, $snapshot->snapshot_data['summary']['attendance_columns']);
        $this->assertTrue(collect($snapshot->snapshot_data['columns'])->contains('key', 'attendance_sick'));
        $this->assertSame('2', $firstRow->cells->firstWhere('column_key', 'attendance_sick')->value_text);
        $this->assertSame('1', $firstRow->cells->firstWhere('column_key', 'attendance_permission')->value_text);
        $this->assertSame('1', $firstRow->cells->firstWhere('column_key', 'attendance_absent')->value_text);
        $this->assertSame(0, $snapshot->snapshot_data['summary']['blocking_issues']);
        $this->assertSame(2, $snapshot->snapshot_data['summary']['complete_rows']);
    }

    public function test_incomplete_students_are_not_ranked(): void
    {
        [$classroomTerm, $assessmentSets, $enrollments] = $this->makeLedgerContext();

        DiniyyahAssessmentResult::create([
            'diniyyah_assessment_set_id' => $assessmentSets[0]->id,
            'class_enrollment_id' => $enrollments[0]->id,
            'final_score' => 80,
            'is_complete' => true,
        ]);
        DiniyyahAssessmentResult::create([
            'diniyyah_assessment_set_id' => $assessmentSets[1]->id,
            'class_enrollment_id' => $enrollments[0]->id,
            'final_score' => 90,
            'is_complete' => true,
        ]);
        DiniyyahAssessmentResult::create([
            'diniyyah_assessment_set_id' => $assessmentSets[0]->id,
            'class_enrollment_id' => $enrollments[1]->id,
            'final_score' => 100,
            'is_complete' => true,
        ]);

        $snapshot = app(DiniyyahLedgerGenerator::class)->generate($classroomTerm);
        $completeRow = $snapshot->rows()->where('class_enrollment_id', $enrollments[0]->id)->first();
        $incompleteRow = $snapshot->rows()->where('class_enrollment_id', $enrollments[1]->id)->first();

        $this->assertSame(1, $completeRow->rank_in_class);
        $this->assertNull($incompleteRow->rank_in_class);
        $this->assertNull($incompleteRow->total_diniyyah_score);
        $this->assertSame(1, $snapshot->snapshot_data['summary']['complete_rows']);
        $this->assertSame(1, $snapshot->snapshot_data['summary']['incomplete_rows']);
        $this->assertGreaterThan(0, $snapshot->snapshot_data['summary']['blocking_issues']);
    }

    /** @return array{ClassroomTerm, array<int, DiniyyahAssessmentSet>, array<int, ClassEnrollment>} */
    private function makeLedgerContext(): array
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

        $enrollments = [];
        foreach ([1, 2] as $index) {
            $student = Student::create(['name' => "Santri {$index}", 'gender' => 'male', 'nis' => "00{$index}"]);
            $enrollments[] = ClassEnrollment::create([
                'academic_term_id' => $term->id,
                'classroom_term_id' => $classroomTerm->id,
                'student_id' => $student->id,
                'roll_number' => $index,
            ]);
        }

        $assessmentSets = [];
        foreach ([['fiqih', 'Fiqih', 10], ['suluk', 'Suluk', 20]] as [$code, $name, $sortOrder]) {
            $subject = DiniyyahSubject::create(['code' => $code, 'name' => $name, 'default_assessment_method' => 'weighted']);
            $classSubject = DiniyyahClassSubject::create([
                'classroom_term_id' => $classroomTerm->id,
                'subject_id' => $subject->id,
                'assessment_method' => 'weighted',
                'appears_on_ledger' => true,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]);
            $assessmentSets[] = DiniyyahAssessmentSet::create([
                'diniyyah_class_subject_id' => $classSubject->id,
                'title' => $name,
                'assessment_method' => 'weighted',
                'appears_on_ledger' => true,
                'sort_order' => $sortOrder,
                'status' => 'validated',
            ]);
        }

        return [$classroomTerm, $assessmentSets, $enrollments];
    }
}
