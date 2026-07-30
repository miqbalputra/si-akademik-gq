<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassSession;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SessionTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Batasan sesi input Jurnal Pengganti sesuai jadwal guru ASLI yang digantikan.
 *
 * Skenario: guru A punya assignment Fiqih di Mustawa 2 Ikhwan + jadwal sesi 1
 * hari Senin. Guru B (pengganti) hanya boleh mengisi sesi 1 di hari Senin untuk
 * assignment A — bukan sesi lain. 2026-07-13 = Senin.
 */
class GuruDiniyyahSubstituteJournalScheduleTest extends TestCase
{
    use RefreshDatabase;

    /** 2026-07-13 = Senin (matrix M2 Ikhwan punya sesi). */
    private const SENIN = '2026-07-13';

    public function test_substitute_index_shows_original_teacher_scheduled_slots(): void
    {
        [$teacherA, $userA, $assignmentA, $classroomTerm] = $this->makeOriginalTeacherWithClass();
        [, $userB] = $this->makeSubstituteTeacher();

        // Jadwal guru asli: sesi 1 hari Senin.
        $this->makeSchedule($assignmentA, dayOfWeek: 1);

        $this->actingAs($userB)
            ->get(route('guru.diniyyah-substitute-journals.index', [
                'classroom_term_id' => $classroomTerm->id,
                'date' => self::SENIN,
            ]))
            ->assertOk()
            ->assertSee('Sesi 1')
            ->assertSee($teacherA->name)
            ->assertDontSee('Sesi 2 —');
    }

    public function test_substitute_store_rejects_unscheduled_session(): void
    {
        [, , $assignmentA, $classroomTerm] = $this->makeOriginalTeacherWithClass();
        [, $userB] = $this->makeSubstituteTeacher();

        // Jadwal guru asli hanya sesi 1 → pengganti POST sesi 2 harus ditolak.
        $this->makeSchedule($assignmentA, dayOfWeek: 1);

        $this->actingAs($userB)
            ->post(route('guru.diniyyah-substitute-journals.store'), [
                'diniyyah_teacher_assignment_id' => $assignmentA->id,
                'classroom_term_id' => $classroomTerm->id,
                'date' => self::SENIN,
                'session_hour' => '2',
                'material' => 'Pengganti bab 1',
                'absences' => [],
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('diniyyah_class_journals', 0);
    }

    public function test_substitute_store_accepts_scheduled_session(): void
    {
        [, , $assignmentA, $classroomTerm] = $this->makeOriginalTeacherWithClass();
        [$teacherB, $userB] = $this->makeSubstituteTeacher();

        $this->makeSchedule($assignmentA, dayOfWeek: 1);

        $this->actingAs($userB)
            ->post(route('guru.diniyyah-substitute-journals.store'), [
                'diniyyah_teacher_assignment_id' => $assignmentA->id,
                'classroom_term_id' => $classroomTerm->id,
                'date' => self::SENIN,
                'session_hour' => '1',
                'material' => 'Pengganti bab 1',
                'absences' => [],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseCount('diniyyah_class_journals', 1);
        $this->assertDatabaseHas('diniyyah_class_journals', [
            'diniyyah_teacher_assignment_id' => $assignmentA->id,
            'substitute_teacher_id' => $teacherB->id,
            'session_hour' => '1',
        ]);
    }

    /**
     * Guru asli (A) + assignment Fiqih di Mustawa 2 Ikhwan + matrix sesi.
     *
     * @return array{0: Teacher, 1: User, 2: DiniyyahTeacherAssignment, 3: ClassroomTerm}
     */
    private function makeOriginalTeacherWithClass(string $classroomName = 'Mustawa 2 Ikhwan'): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $userA = User::factory()->create(['name' => 'Ustadz Asli']);
        $userA->assignRole('guru');
        $teacherA = Teacher::create(['user_id' => $userA->id, 'name' => 'Ustadz Asli']);

        $termId = $this->academicTermId();

        $classroom = Classroom::create(['name' => $classroomName]);
        SessionTimetable::seedForClassroom($classroom);

        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $termId,
            'classroom_id' => $classroom->id,
            'name' => $classroomName,
        ]);

        $subject = DiniyyahSubject::firstOrCreate(
            ['code' => 'fiqih'],
            ['name' => 'Fiqih', 'default_assessment_method' => 'weighted', 'is_active' => true],
        );

        $classSubject = DiniyyahClassSubject::create([
            'classroom_term_id' => $classroomTerm->id,
            'subject_id' => $subject->id,
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
        ]);

        $assignmentA = DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $classSubject->id,
            'teacher_id' => $teacherA->id,
            'assignment_role' => 'primary',
        ]);

        return [$teacherA, $userA, $assignmentA, $classroomTerm];
    }

    /**
     * Guru pengganti (B) — akun terpisah, role guru, terhubung ke Teacher B.
     *
     * @return array{0: Teacher, 1: User}
     */
    private function makeSubstituteTeacher(): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $userB = User::factory()->create(['name' => 'Ustadz Pengganti']);
        $userB->assignRole('guru');
        $teacherB = Teacher::create(['user_id' => $userB->id, 'name' => 'Ustadz Pengganti']);

        return [$teacherB, $userB];
    }

    private function makeSchedule(DiniyyahTeacherAssignment $assignment, int $dayOfWeek): DiniyyahTeachingSchedule
    {
        $session = ClassSession::where('session_name', '1')->firstOrFail();

        return DiniyyahTeachingSchedule::create([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'class_session_id' => $session->id,
            'day_of_week' => $dayOfWeek,
        ]);
    }

    private function academicTermId(): int
    {
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);

        return AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil'])->id;
    }
}