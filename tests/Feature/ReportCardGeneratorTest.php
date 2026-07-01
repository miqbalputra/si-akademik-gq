<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\User;
use App\Services\ReportCardBulkWorkflow;
use App\Services\ReportCardGenerator;
use App\Services\ReportCardWorkflow;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportCardGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_report_card_from_ledger_row(): void
    {
        [$snapshot, $enrollment] = $this->makeLedgerSnapshot();

        $count = app(ReportCardGenerator::class)->generateFromLedgerSnapshot($snapshot);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('report_cards', [
            'class_enrollment_id' => $enrollment->id,
            'report_type' => 'diniyyah',
            'status' => 'draft',
            'total_score' => 170,
            'average_score' => 85,
            'rank_in_class' => 1,
        ]);
        $this->assertDatabaseCount('report_card_lines', 2);
        $this->assertDatabaseCount('report_card_snapshots', 1);
        $this->assertDatabaseHas('report_card_lines', [
            'subject_name' => 'Fiqih',
            'score_words' => 'Delapan Puluh',
            'score_letter' => 'Jayyid Jiddan',
            'is_passed' => true,
        ]);
    }

    public function test_it_syncs_report_card_attendance_from_daily_attendance(): void
    {
        [$snapshot, $enrollment] = $this->makeLedgerSnapshot();

        foreach ([
            ['attendance_date' => '2026-01-01', 'status' => StudentAttendance::STATUS_SICK],
            ['attendance_date' => '2026-01-02', 'status' => StudentAttendance::STATUS_PERMISSION],
            ['attendance_date' => '2026-01-03', 'status' => StudentAttendance::STATUS_ABSENT],
            ['attendance_date' => '2026-01-04', 'status' => StudentAttendance::STATUS_PRESENT],
            ['attendance_date' => '2026-01-05', 'status' => StudentAttendance::STATUS_HOLIDAY],
        ] as $attendance) {
            StudentAttendance::create([
                'academic_term_id' => $enrollment->academic_term_id,
                'classroom_term_id' => $enrollment->classroom_term_id,
                'class_enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                ...$attendance,
            ]);
        }

        app(ReportCardGenerator::class)->generateFromLedgerSnapshot($snapshot);

        $this->assertDatabaseHas('report_card_attendances', [
            'sick_count' => 1,
            'permission_count' => 1,
            'absent_count' => 1,
        ]);
    }

    public function test_it_does_not_convert_ledger_attendance_cells_into_report_subject_lines(): void
    {
        [$snapshot] = $this->makeLedgerSnapshot();
        $row = $snapshot->rows()->first();
        $row->cells()->create([
            'column_key' => 'attendance_sick',
            'label' => 'Presensi Sakit',
            'source_type' => 'student_attendance_recap',
            'source_id' => null,
            'value_numeric' => 2,
            'value_text' => '2',
            'sort_order' => 10010,
        ]);

        app(ReportCardGenerator::class)->generateFromLedgerSnapshot($snapshot);

        $this->assertDatabaseCount('report_card_lines', 2);
        $this->assertDatabaseMissing('report_card_lines', [
            'source_type' => 'student_attendance_recap',
            'subject_name' => 'Presensi Sakit',
        ]);
    }

    public function test_it_refuses_to_generate_report_cards_from_unlocked_ledger(): void
    {
        [$snapshot] = $this->makeLedgerSnapshot();
        $snapshot->update(['status' => 'draft']);

        $this->expectException(DomainException::class);

        app(ReportCardGenerator::class)->generateFromLedgerSnapshot($snapshot);
    }

    public function test_it_refuses_to_generate_report_cards_from_ledger_with_blocking_issues(): void
    {
        [$snapshot] = $this->makeLedgerSnapshot();
        $snapshot->update([
            'snapshot_data' => [
                'summary' => [
                    'blocking_issues' => 1,
                ],
            ],
        ]);

        $this->expectException(DomainException::class);

        app(ReportCardGenerator::class)->generateFromLedgerSnapshot($snapshot);
    }

    public function test_it_refuses_to_generate_report_card_from_incomplete_ledger_row(): void
    {
        [$snapshot] = $this->makeLedgerSnapshot();
        $row = $snapshot->rows()->first();
        $row->update([
            'total_diniyyah_score' => null,
            'average_diniyyah_score' => null,
            'rank_in_class' => null,
        ]);

        $this->expectException(DomainException::class);

        app(ReportCardGenerator::class)->generateFromLedgerRow($snapshot, $row);
    }

    public function test_it_locks_and_publishes_report_card(): void
    {
        [$snapshot] = $this->makeLedgerSnapshot();
        $reportCard = app(ReportCardGenerator::class)->generateFromLedgerSnapshot($snapshot);
        $reportCard = ReportCard::first();
        $user = User::factory()->create();

        app(ReportCardWorkflow::class)->lock($reportCard, $user);
        $this->assertSame('locked', $reportCard->refresh()->status);

        app(ReportCardWorkflow::class)->publish($reportCard, $user);
        $this->assertSame('published', $reportCard->refresh()->status);
        $this->assertNotNull($reportCard->published_at);
    }

    public function test_it_refuses_to_publish_unlocked_report_card(): void
    {
        [$snapshot] = $this->makeLedgerSnapshot();
        app(ReportCardGenerator::class)->generateFromLedgerSnapshot($snapshot);
        $reportCard = ReportCard::first();

        $this->expectException(DomainException::class);

        app(ReportCardWorkflow::class)->publish($reportCard, User::factory()->create());
    }

    public function test_bulk_workflow_summarizes_locks_and_publishes_class_report_cards(): void
    {
        [$snapshot] = $this->makeLedgerSnapshot();
        app(ReportCardGenerator::class)->generateFromLedgerSnapshot($snapshot);
        $user = User::factory()->create();
        $bulkWorkflow = app(ReportCardBulkWorkflow::class);

        $this->assertSame([
            'expected' => 1,
            'total' => 1,
            'draft' => 1,
            'locked' => 0,
            'published' => 0,
            'missing' => 0,
        ], $bulkWorkflow->summaryForSnapshot($snapshot));

        $lockResult = $bulkWorkflow->lockForSnapshot($snapshot, $user);
        $this->assertSame(['locked' => 1, 'skipped' => 0], $lockResult);
        $this->assertSame(1, ReportCard::where('status', 'locked')->count());

        $publishResult = $bulkWorkflow->publishForSnapshot($snapshot, $user);
        $this->assertSame(['published' => 1, 'skipped' => 0], $publishResult);
        $this->assertSame(1, ReportCard::where('status', 'published')->count());
    }

    public function test_bulk_workflow_refuses_to_lock_when_report_cards_are_missing(): void
    {
        [$snapshot] = $this->makeLedgerSnapshot();

        $this->expectException(DomainException::class);

        app(ReportCardBulkWorkflow::class)->lockForSnapshot($snapshot, User::factory()->create());
    }

    public function test_bulk_workflow_refuses_to_publish_draft_report_cards(): void
    {
        [$snapshot] = $this->makeLedgerSnapshot();
        app(ReportCardGenerator::class)->generateFromLedgerSnapshot($snapshot);

        $this->expectException(DomainException::class);

        app(ReportCardBulkWorkflow::class)->publishForSnapshot($snapshot, User::factory()->create());
    }

    public function test_admin_can_bulk_lock_and_publish_report_cards_from_ledger_routes(): void
    {
        [$snapshot] = $this->makeLedgerSnapshot();
        app(ReportCardGenerator::class)->generateFromLedgerSnapshot($snapshot);
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('report-cards.ledger.lock', $snapshot))
            ->assertRedirect(route('diniyyah.ledger.show', $snapshot));

        $this->assertSame(1, ReportCard::where('status', 'locked')->count());

        $this->actingAs($admin)
            ->post(route('report-cards.ledger.publish', $snapshot))
            ->assertRedirect(route('diniyyah.ledger.show', $snapshot));

        $this->assertSame(1, ReportCard::where('status', 'published')->count());
    }

    /** @return array{DiniyyahLedgerSnapshot, ClassEnrollment} */
    private function makeLedgerSnapshot(): array
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
        $snapshot = DiniyyahLedgerSnapshot::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'title' => 'Leger Diniyyah Mustawa 1 Ikhwan',
            'status' => 'locked',
            'snapshot_data' => [
                'columns' => [
                    ['key' => 'assessment_1', 'label' => 'Fiqih', 'assessment_set_id' => 1],
                    ['key' => 'assessment_2', 'label' => 'Suluk', 'assessment_set_id' => 2],
                ],
                'summary' => [
                    'blocking_issues' => 0,
                ],
            ],
        ]);
        $row = $snapshot->rows()->create([
            'class_enrollment_id' => $enrollment->id,
            'row_number' => 1,
            'student_name' => 'Santri 1',
            'student_nis' => '001',
            'total_diniyyah_score' => 170,
            'average_diniyyah_score' => 85,
            'rank_in_class' => 1,
        ]);
        $row->cells()->create([
            'column_key' => 'assessment_1',
            'label' => 'Fiqih',
            'source_type' => 'diniyyah_assessment_set',
            'source_id' => 1,
            'value_numeric' => 80,
            'sort_order' => 10,
        ]);
        $row->cells()->create([
            'column_key' => 'assessment_2',
            'label' => 'Suluk',
            'source_type' => 'diniyyah_assessment_set',
            'source_id' => 2,
            'value_numeric' => 90,
            'sort_order' => 20,
        ]);

        return [$snapshot, $enrollment];
    }
}
