<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassJournalAbsence;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentDeparture;
use App\Models\Teacher;
use App\Models\User;
use App\Services\StudentDepartureWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * #2: destroy() jurnal harus 403 (bukan 500) untuk akun tanpa Teacher,
 * dan 403 untuk guru non-pemilik, 200 untuk pemilik.
 */
class DiniyyahJournalDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_teacher_gets_403_not_500(): void
    {
        $journal = $this->makeJournal();

        $user = User::factory()->create(); // tidak ada Teacher, tidak ada role guru

        $response = $this->actingAs($user)
            ->delete(route('guru.diniyyah-journals.destroy', $journal));

        $response->assertStatus(403);
        $this->assertDatabaseHas('diniyyah_class_journals', ['id' => $journal->id]);
    }

    public function test_other_teacher_cannot_delete(): void
    {
        $journal = $this->makeJournal();
        $other = $this->makeTeacher('Guru Lain');

        $response = $this->actingAs($other->user)
            ->delete(route('guru.diniyyah-journals.destroy', $journal));

        $response->assertStatus(403);
        $this->assertDatabaseHas('diniyyah_class_journals', ['id' => $journal->id]);
    }

    public function test_owner_teacher_can_delete(): void
    {
        $context = $this->makeJournalWithContext();
        $journal = $context['journal'];
        $owner = $context['teacher'];

        $response = $this->actingAs($owner->user)
            ->delete(route('guru.diniyyah-journals.destroy', $journal));

        $response->assertRedirect();
        $this->assertSoftDeleted('diniyyah_class_journals', ['id' => $journal->id]);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'academic-data',
            'description' => 'diniyyah_journal_deleted',
            'subject_id' => $journal->id,
        ]);
    }

    public function test_editing_a_journal_does_not_delete_archived_student_absence_history(): void
    {
        $context = $this->makeJournalWithContext();
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        app(StudentDepartureWorkflow::class)->depart($context['student'], [
            'type' => StudentDeparture::TYPE_LEFT,
            'effective_date' => '2026-10-01',
            'reason' => 'Keluar.',
        ], $admin);

        $this->actingAs($context['teacher']->user)
            ->put(route('guru.diniyyah-journals.update', $context['journal']), [
                'material' => 'Materi diperbarui setelah santri keluar',
                'absences' => [],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('diniyyah_class_journal_absences', [
            'id' => $context['absence']->id,
            'class_enrollment_id' => $context['enrollment']->id,
            'status' => 'sick',
        ]);
    }

    private function makeJournal(): DiniyyahClassJournal
    {
        return $this->makeJournalWithContext()['journal'];
    }

    private function makeJournalWithContext(): array
    {
        $teacher = $this->makeTeacher('Guru Fiqih');
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Genap', 'semester' => 'genap']);
        $classroom = Classroom::create(['name' => 'Mustawa 1 Ikhwan']);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => 'Mustawa 1 Ikhwan',
        ]);
        $subject = DiniyyahSubject::create(['code' => 'fiqih', 'name' => 'Fiqih', 'default_assessment_method' => 'weighted']);
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
        $student = Student::create(['name' => 'Santri Jurnal', 'gender' => 'male', 'nis' => 'JURNAL-001']);
        $enrollment = ClassEnrollment::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'student_id' => $student->id,
            'roll_number' => 1,
        ]);
        $journal = DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'date' => '2026-07-09',
            'session_hour' => '1',
            'material' => 'Bab 1',
            'jp_count' => 1,
        ]);
        $absence = DiniyyahClassJournalAbsence::create([
            'diniyyah_class_journal_id' => $journal->id,
            'class_enrollment_id' => $enrollment->id,
            'status' => 'sick',
        ]);

        return [
            'journal' => $journal,
            'teacher' => $teacher,
            'student' => $student,
            'enrollment' => $enrollment,
            'absence' => $absence,
        ];
    }

    private function makeTeacher(string $name): Teacher
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('guru');

        return Teacher::create(['user_id' => $user->id, 'name' => $name]);
    }
}
