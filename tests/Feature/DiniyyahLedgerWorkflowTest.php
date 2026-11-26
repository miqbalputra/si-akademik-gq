<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\DiniyyahLedgerGenerator;
use App\Services\DiniyyahLedgerWorkflow;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiniyyahLedgerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_validates_and_locks_ledger_snapshot(): void
    {
        [$snapshot, $user] = $this->makeSnapshot();

        app(DiniyyahLedgerWorkflow::class)->validate($snapshot, $user);
        $this->assertSame('validated', $snapshot->refresh()->status);
        $this->assertNotNull($snapshot->validated_at);
        $this->assertSame($user->id, $snapshot->validated_by);

        app(DiniyyahLedgerWorkflow::class)->lock($snapshot, $user);
        $this->assertSame('locked', $snapshot->refresh()->status);
        $this->assertNotNull($snapshot->locked_at);
        $this->assertSame($user->id, $snapshot->locked_by);
    }

    public function test_generator_refuses_to_overwrite_locked_snapshot(): void
    {
        [$snapshot] = $this->makeSnapshot();
        $snapshot->update(['status' => 'locked']);

        $this->expectException(DomainException::class);

        app(DiniyyahLedgerGenerator::class)->generate($snapshot->classroomTerm);
    }

    public function test_lock_refuses_snapshot_with_blocking_issues(): void
    {
        [$snapshot, $user] = $this->makeSnapshot();
        $snapshot->update([
            'snapshot_data' => [
                'summary' => [
                    'blocking_issues' => 1,
                ],
            ],
        ]);

        $this->expectException(DomainException::class);

        app(DiniyyahLedgerWorkflow::class)->lock($snapshot, $user);
    }

    /** @return array{DiniyyahLedgerSnapshot, User} */
    private function makeSnapshot(): array
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
        $snapshot = DiniyyahLedgerSnapshot::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'title' => 'Leger Diniyyah Mustawa 1 Ikhwan',
            'status' => 'draft',
            'snapshot_data' => ['columns' => [['key' => 'assessment_1', 'source_type' => 'diniyyah_assessment_set']]],
        ]);
        $student = Student::create(['name' => 'Santri Leger', 'gender' => 'male', 'nis' => 'LEGER-1', 'status' => 'active']);
        $enrollment = ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $classroomTerm->id, 'student_id' => $student->id, 'status' => 'active']);
        $row = $snapshot->rows()->create(['class_enrollment_id' => $enrollment->id, 'student_name' => $student->name, 'row_number' => 1,
            'total_diniyyah_score' => 0, 'average_diniyyah_score' => 0, 'rank_in_class' => 1]);
        $row->cells()->create(['column_key' => 'assessment_1', 'label' => 'Penilaian', 'source_type' => 'diniyyah_assessment_set', 'value_numeric' => 0]);
        $user = User::factory()->create();

        return [$snapshot, $user];
    }
}
