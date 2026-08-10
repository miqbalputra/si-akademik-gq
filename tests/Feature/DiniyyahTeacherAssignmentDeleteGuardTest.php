<?php

namespace Tests\Feature;

use App\Filament\Resources\DiniyyahTeacherAssignments\DiniyyahTeacherAssignmentResource;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SessionTimetable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Guard integritas jurnal: penugasan yang sudah memiliki jurnal kelas tidak
 * boleh dihapus (cascadeOnDelete FK jurnal→assignment = satu-satunya jalur
 * hilangnya data jurnal). Ditegakkan berlapis: model isDeletable(), policy
 * Gate delete, dan Filament canDelete() per-record.
 */
class DiniyyahTeacherAssignmentDeleteGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_is_deletable_true_when_no_journal(): void
    {
        $guru = $this->makeGuru('Ustadz Ahmad');
        [$assignment] = $this->makeAssignment($guru['teacher'], 'Fiqih');

        $this->assertTrue($assignment->isDeletable());
    }

    public function test_is_deletable_false_when_has_journal(): void
    {
        $guru = $this->makeGuru('Ustadz Ahmad');
        [$assignment] = $this->makeAssignment($guru['teacher'], 'Fiqih');
        $this->makeJournal($assignment);

        $this->assertFalse($assignment->isDeletable());
    }

    public function test_policy_denies_delete_when_has_journal(): void
    {
        $admin = $this->makeAdmin();
        $guru = $this->makeGuru('Ustadz Ahmad');
        [$assignment] = $this->makeAssignment($guru['teacher'], 'Fiqih');
        $this->makeJournal($assignment);

        $this->actingAs($admin);
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $assignment));
        $this->assertFalse(Gate::forUser($admin)->allows('forceDelete', $assignment));
    }

    public function test_policy_allows_delete_when_no_journal(): void
    {
        $admin = $this->makeAdmin();
        $guru = $this->makeGuru('Ustadz Ahmad');
        [$assignment] = $this->makeAssignment($guru['teacher'], 'Fiqih');

        $this->actingAs($admin);
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $assignment));
    }

    public function test_filament_can_delete_reflects_policy_per_record(): void
    {
        $admin = $this->makeAdmin();
        $guru = $this->makeGuru('Ustadz Ahmad');
        [$assignmentClean] = $this->makeAssignment($guru['teacher'], 'Fiqih');
        [$assignmentWithJournal] = $this->makeAssignment($guru['teacher'], 'Akidah');
        $this->makeJournal($assignmentWithJournal);

        $this->actingAs($admin);

        // canDelete() = canCreate() (admin boleh manage) && policyAllows('delete')
        // → harus mencerminkan isDeletable() per record.
        $this->assertTrue(DiniyyahTeacherAssignmentResource::canDelete($assignmentClean));
        $this->assertFalse(DiniyyahTeacherAssignmentResource::canDelete($assignmentWithJournal));
    }

    public function test_kepala_sekolah_cannot_delete_any_assignment_even_without_journal(): void
    {
        $kepala = $this->userWithRole('kepala_sekolah');
        $guru = $this->makeGuru('Ustadz Ahmad');
        [$assignment] = $this->makeAssignment($guru['teacher'], 'Fiqih');

        $this->actingAs($kepala);
        // kepala_sekolah read-only (tidak di MANAGE_ROLES) → canDelete false
        // meski penugasan sendiri tanpa jurnal.
        $this->assertFalse(DiniyyahTeacherAssignmentResource::canDelete($assignment));
    }

    // ----- Helpers -----

    private function makeAdmin(): User
    {
        return $this->userWithRole('admin');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

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
     * Buat assignment reguler TANPA schedule & TANPA actingAs (observer created
     * skip tanpa Auth → tidak terlog, tidak mengganggu hitung log bila dipakai di
     * test lain). Mengembalikan [assignment, classSubject].
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

    private function makeJournal(DiniyyahTeacherAssignment $assignment): DiniyyahClassJournal
    {
        return DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'date' => '2026-08-04',
            'session_hour' => '1',
            'session_starts_at' => '10:30:00',
            'session_ends_at' => '11:00:00',
            'material' => 'Bab Thaharah',
            'jp_count' => 1,
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
}