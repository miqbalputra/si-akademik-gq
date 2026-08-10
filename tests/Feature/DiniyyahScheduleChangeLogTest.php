<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahScheduleChangeLog;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SessionTimetable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Riwayat perubahan jadwal & penugasan diniyyah: observer mencatat
 * created/updated/deleted ke diniyyah_schedule_change_logs dengan summary
 * Indonesia + teacher_id/old_teacher_id yang benar. created/updated skip bila
 * tanpa Auth (seeding/CLI); deleting selalu dicatat.
 */
class DiniyyahScheduleChangeLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_logs_schedule_created(): void
    {
        $admin = $this->makeAdmin();
        $guru = $this->makeGuru('Ustadz Ahmad');
        [$assignment] = $this->makeAssignment($guru['teacher'], 'Fiqih'); // tanpa Auth → tidak terlog

        $this->actingAs($admin);
        $schedule = $this->makeSchedule($assignment, 1, '1');

        $this->assertDatabaseHas('diniyyah_schedule_change_logs', [
            'entity_type' => 'schedule',
            'event' => 'created',
            'diniyyah_teaching_schedule_id' => $schedule->id,
            'teacher_id' => $guru['teacher']->id,
            'changed_by' => $admin->id,
        ]);
        $this->assertStringContainsString('Jadwal baru', $this->lastLog()->change_summary);
    }

    public function test_logs_schedule_updated_day_change(): void
    {
        $admin = $this->makeAdmin();
        $guru = $this->makeGuru('Ustadz Ahmad');
        [$assignment] = $this->makeAssignment($guru['teacher'], 'Fiqih');
        $schedule = $this->makeSchedule($assignment, 1, '1'); // tanpa Auth → tidak terlog

        $this->actingAs($admin);
        $schedule->update(['day_of_week' => 3]);

        $log = $this->lastLog();
        $this->assertSame('schedule', $log->entity_type);
        $this->assertSame('updated', $log->event);
        $this->assertSame(['day_of_week' => 1], $log->old_values);
        $this->assertSame(['day_of_week' => 3], $log->new_values);
        $this->assertStringContainsString('Senin', $log->change_summary);
        $this->assertStringContainsString('Rabu', $log->change_summary);
    }

    public function test_logs_schedule_updated_assignment_swap(): void
    {
        $admin = $this->makeAdmin();
        $guruA = $this->makeGuru('Ustadz Ahmad');
        $guruB = $this->makeGuru('Ustadz Budi');
        [$assignmentA] = $this->makeAssignment($guruA['teacher'], 'Fiqih');
        [$assignmentB] = $this->makeAssignment($guruB['teacher'], 'Akidah');
        $schedule = $this->makeSchedule($assignmentA, 1, '1'); // tanpa Auth

        $this->actingAs($admin);
        $schedule->update(['diniyyah_teacher_assignment_id' => $assignmentB->id]);

        $log = $this->lastLog();
        $this->assertSame('updated', $log->event);
        $this->assertSame($guruA['teacher']->id, $log->old_teacher_id);
        $this->assertSame($guruB['teacher']->id, $log->teacher_id);
        $this->assertStringContainsString('Ustadz Ahmad', $log->change_summary);
        $this->assertStringContainsString('Ustadz Budi', $log->change_summary);
    }

    public function test_logs_schedule_deleted(): void
    {
        $admin = $this->makeAdmin();
        $guru = $this->makeGuru('Ustadz Ahmad');
        [$assignment] = $this->makeAssignment($guru['teacher'], 'Fiqih');
        $schedule = $this->makeSchedule($assignment, 1, '1'); // tanpa Auth

        $this->actingAs($admin);
        $schedule->delete();

        $log = $this->lastLog();
        $this->assertSame('schedule', $log->entity_type);
        $this->assertSame('deleted', $log->event);
        $this->assertStringContainsString('dihapus', $log->change_summary);
    }

    public function test_logs_assignment_created(): void
    {
        $admin = $this->makeAdmin();
        $guru = $this->makeGuru('Ustadz Ahmad');

        $this->actingAs($admin);
        $this->makeAssignment($guru['teacher'], 'Fiqih');

        $log = $this->lastLog();
        $this->assertSame('assignment', $log->entity_type);
        $this->assertSame('created', $log->event);
        $this->assertSame($guru['teacher']->id, $log->teacher_id);
        $this->assertStringContainsString('Penugasan baru', $log->change_summary);
    }

    public function test_logs_assignment_updated_teacher_swap_with_journal_note(): void
    {
        $admin = $this->makeAdmin();
        $guruA = $this->makeGuru('Ustadz Ahmad');
        $guruB = $this->makeGuru('Ustadz Budi');
        [$assignment] = $this->makeAssignment($guruA['teacher'], 'Fiqih'); // tanpa Auth

        $this->actingAs($admin);
        $assignment->update(['teacher_id' => $guruB['teacher']->id]);

        $log = $this->lastLog();
        $this->assertSame('updated', $log->event);
        $this->assertSame($guruA['teacher']->id, $log->old_teacher_id);
        $this->assertSame($guruB['teacher']->id, $log->teacher_id);
        $this->assertStringContainsString('tetap menempel', $log->change_summary);
    }

    public function test_logs_assignment_updated_class_subject_swap(): void
    {
        $admin = $this->makeAdmin();
        $guru = $this->makeGuru('Ustadz Ahmad');
        [$assignmentA, $csA] = $this->makeAssignment($guru['teacher'], 'Fiqih');
        // Buat classSubject kedua (Akidah) di classroom term yang sama.
        $akidah = DiniyyahSubject::firstOrCreate(
            ['code' => 'akidah'],
            ['name' => 'Akidah', 'default_assessment_method' => 'weighted', 'is_active' => true],
        );
        $csB = DiniyyahClassSubject::create([
            'classroom_term_id' => $csA->classroom_term_id,
            'subject_id' => $akidah->id,
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
        ]);

        $this->actingAs($admin);
        $assignmentA->update(['diniyyah_class_subject_id' => $csB->id]);

        $log = $this->lastLog();
        $this->assertStringContainsString('Fiqih', $log->change_summary);
        $this->assertStringContainsString('Akidah', $log->change_summary);
    }

    public function test_logs_assignment_deleted_without_journal(): void
    {
        $admin = $this->makeAdmin();
        $guru = $this->makeGuru('Ustadz Ahmad');
        [$assignment] = $this->makeAssignment($guru['teacher'], 'Fiqih'); // tanpa Auth, tanpa jurnal

        $this->actingAs($admin);
        $assignment->delete();

        $log = $this->lastLog();
        $this->assertSame('assignment', $log->entity_type);
        $this->assertSame('deleted', $log->event);
        $this->assertStringContainsString('Penugasan dihapus', $log->change_summary);
    }

    public function test_no_log_created_when_no_auth_context(): void
    {
        $guru = $this->makeGuru('Ustadz Ahmad');
        [$assignment] = $this->makeAssignment($guru['teacher'], 'Fiqih'); // tanpa Auth
        $this->makeSchedule($assignment, 1, '1'); // tanpa Auth

        $this->assertDatabaseCount('diniyyah_schedule_change_logs', 0);
    }

    public function test_no_recursion_one_create_one_log(): void
    {
        $admin = $this->makeAdmin();
        $guru = $this->makeGuru('Ustadz Ahmad');
        [$assignment] = $this->makeAssignment($guru['teacher'], 'Fiqih'); // tanpa Auth

        $this->actingAs($admin);
        $this->makeSchedule($assignment, 1, '1');

        // Tepat 1 log (schedule created) — logger tidak men-trigger ulang.
        $this->assertDatabaseCount('diniyyah_schedule_change_logs', 1);
    }

    // ----- Helpers -----

    private function makeAdmin(): User
    {
        $user = User::factory()->create(['name' => 'Admin']);
        $user->assignRole('admin');

        return $user;
    }

    /** @return array{0: User, 1: Teacher} */
    private function makeGuru(string $name): array
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => $name]);

        return ['user' => $user, 'teacher' => $teacher];
    }

    /**
     * Buat assignment reguler (non-tafsir) TANPA schedule. Mengembalikan
     * [assignment, classSubject]. Dibuat tanpa actingAs supaya observer
     * created tidak terlog (test mengontrol kapan logging terjadi).
     *
     * @return array{0: DiniyyahTeacherAssignment, 1: DiniyyahClassSubject}
     */
    private function makeAssignment(Teacher $teacher, string $subjectName): array
    {
        $classroom = Classroom::create(['name' => 'Mustawa 2 Ikhwan']);
        SessionTimetable::seedForClassroom($classroom);
        $termId = $this->academicTermId();
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $termId,
            'classroom_id' => $classroom->id,
            'name' => 'Mustawa 2 Ikhwan',
        ]);

        $subject = DiniyyahSubject::firstOrCreate(
            ['code' => strtolower($subjectName)],
            ['name' => $subjectName, 'default_assessment_method' => 'weighted', 'is_active' => true],
        );
        $classSubject = DiniyyahClassSubject::create([
            'classroom_term_id' => $classroomTerm->id,
            'subject_id' => $subject->id,
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
        ]);
        $assignment = DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $classSubject->id,
            'teacher_id' => $teacher->id,
            'assignment_role' => 'primary',
        ]);

        return [$assignment, $classSubject];
    }

    private function makeSchedule(DiniyyahTeacherAssignment $assignment, int $dayOfWeek, string $sessionName): DiniyyahTeachingSchedule
    {
        $session = ClassSession::where('session_name', $sessionName)->firstOrFail();

        return DiniyyahTeachingSchedule::create([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'class_session_id' => $session->id,
            'day_of_week' => $dayOfWeek,
        ]);
    }

    private function academicTermId(): int
    {
        $school = School::first() ?? School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::first() ?? AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);

        return AcademicTerm::firstOrCreate(
            ['academic_year_id' => $year->id, 'name' => 'Ganjil'],
            ['semester' => 'ganjil'],
        )->id;
    }

    private function lastLog(): DiniyyahScheduleChangeLog
    {
        return DiniyyahScheduleChangeLog::latest('id')->firstOrFail();
    }
}