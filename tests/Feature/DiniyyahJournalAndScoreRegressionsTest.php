<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassJournalAbsence;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\DiniyyahScore;
use App\Models\DiniyyahScoreComponent;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression test untuk Batch 4:
 *  - #14: observer jurnal/absensi diniyyah → recompute skor keaktifan_presensi.
 *  - #15: recompute presensi tidak menurunkan status skor yang sudah submitted/validated.
 *  - #16: store jurnal menolak classroom_term silang & absensi enrollment asing.
 *  - #17: admin mengedit set submitted/validated → kembali ke needs_revision;
 *         leger terkunci → 403.
 */
class DiniyyahJournalAndScoreRegressionsTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- #14 / #15

    public function test_journal_and_absence_auto_recompute_keaktifan_presensi_score(): void
    {
        [$assessmentSet, $teacher, $enrollmentA, $enrollmentB, $attendanceComponent] = $this->makeJournalContext();

        // 1 jurnal terisi (belum ada absensi) → semua siswa hadir penuh = 100.
        $journal = DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $teacher->assignment_id,
            'date' => '2026-07-09',
            'session_hour' => '1',
            'material' => 'Bab 1',
            'jp_count' => 1,
        ]);

        $this->assertSame(100.00, $this->attendanceScore($assessmentSet, $attendanceComponent, $enrollmentA));
        $this->assertSame(100.00, $this->attendanceScore($assessmentSet, $attendanceComponent, $enrollmentB));

        // Guru menandai enrollment A "bolos sesi" (status=skipped) → A jadi 0, B tetap 100.
        DiniyyahClassJournalAbsence::create([
            'diniyyah_class_journal_id' => $journal->id,
            'class_enrollment_id' => $enrollmentA->id,
            'status' => 'skipped',
        ]);

        $this->assertSame(0.00, $this->attendanceScore($assessmentSet, $attendanceComponent, $enrollmentA));
        $this->assertSame(100.00, $this->attendanceScore($assessmentSet, $attendanceComponent, $enrollmentB));
    }

    public function test_attendance_recompute_preserves_submitted_score_status(): void
    {
        [$assessmentSet, $teacher, $enrollmentA, , $attendanceComponent] = $this->makeJournalContext();

        // Skor presensi sudah disubmit (mis. di-submit admin lalu ada jurnal baru).
        DiniyyahScore::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'diniyyah_score_component_id' => $attendanceComponent->id,
            'class_enrollment_id' => $enrollmentA->id,
            'score' => 50.00,
            'status' => 'submitted',
            'input_by' => $teacher->user_id,
            'input_at' => now(),
        ]);

        // Observer jurnal menyalakan ulang presensi: totalDays=1, 0 absensi → 100.
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $teacher->assignment_id,
            'date' => '2026-07-09',
            'session_hour' => '1',
            'material' => 'Bab 1',
            'jp_count' => 1,
        ]);

        $score = DiniyyahScore::where([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'diniyyah_score_component_id' => $attendanceComponent->id,
            'class_enrollment_id' => $enrollmentA->id,
        ])->first();

        $this->assertNotNull($score);
        $this->assertSame(100.00, (float) $score->score);   // nilai di-refresh
        $this->assertSame('submitted', $score->status);      // status TIDAK diturunkan ke draft
    }

    // ---------------------------------------------------------------- #16

    public function test_journal_store_rejects_cross_classroom_term(): void
    {
        [$assessmentSet, $teacher, , ,] = $this->makeJournalContext();

        // Buat classroom + classroom_term lain untuk dipalsukan.
        $otherClassroom = Classroom::create(['name' => 'Mustawa 2 Ikhwan']);
        $otherTerm = ClassroomTerm::create([
            'academic_term_id' => $assessmentSet->classSubject->classroomTerm->academic_term_id,
            'classroom_id' => $otherClassroom->id,
            'name' => 'Mustawa 2 Ikhwan',
        ]);

        $this->actingAs($teacher->user)
            ->post(route('guru.diniyyah-journals.store'), [
                'diniyyah_teacher_assignment_id' => $teacher->assignment_id,
                'date' => '2026-07-09',
                'session_hour' => '1',
                'material' => 'Bab 1',
                'classroom_term_id' => $otherTerm->id, // silang → 403
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('diniyyah_class_journals', [
            'diniyyah_teacher_assignment_id' => $teacher->assignment_id,
            'date' => '2026-07-09',
        ]);
    }

    public function test_journal_store_ignores_absence_for_foreign_enrollment(): void
    {
        [$assessmentSet, $teacher, $enrollmentA, ,] = $this->makeJournalContext();

        // Enrollment dari classroom_term lain.
        $otherClassroom = Classroom::create(['name' => 'Mustawa 2 Ikhwan']);
        $otherTerm = ClassroomTerm::create([
            'academic_term_id' => $assessmentSet->classSubject->classroomTerm->academic_term_id,
            'classroom_id' => $otherClassroom->id,
            'name' => 'Mustawa 2 Ikhwan',
        ]);
        $foreignStudent = Student::create(['name' => 'Santri Asing', 'gender' => 'male', 'nis' => '099']);
        $foreignEnrollment = ClassEnrollment::create([
            'academic_term_id' => $assessmentSet->classSubject->classroomTerm->academic_term_id,
            'classroom_term_id' => $otherTerm->id,
            'student_id' => $foreignStudent->id,
        ]);

        $this->actingAs($teacher->user)
            ->post(route('guru.diniyyah-journals.store'), [
                'diniyyah_teacher_assignment_id' => $teacher->assignment_id,
                'date' => '2026-07-09',
                'session_hour' => '1',
                'material' => 'Bab 1',
                'classroom_term_id' => $assessmentSet->classSubject->classroom_term_id,
                'absences' => [
                    $enrollmentA->id => 'skipped',            // valid → disimpan
                    $foreignEnrollment->id => 'skipped',      // asing → diabaikan
                ],
            ])
            ->assertRedirect();

        // Hanya absensi valid yang tersimpan; enrollment asing diabaikan.
        $this->assertDatabaseHas('diniyyah_class_journal_absences', [
            'class_enrollment_id' => $enrollmentA->id,
            'status' => 'skipped',
        ]);
        $this->assertDatabaseMissing('diniyyah_class_journal_absences', [
            'class_enrollment_id' => $foreignEnrollment->id,
        ]);
    }

    // ---------------------------------------------------------------- #17

    public function test_admin_editing_submitted_set_reverts_to_needs_revision(): void
    {
        [$assessmentSet, $teacher, $enrollment, , $dailyComponent] = $this->makeInputContext();
        $admin = $this->makeAdmin();

        $assessmentSet->update(['status' => 'submitted']);

        $this->actingAs($admin)
            ->put(route('guru.diniyyah-scores.update', $assessmentSet), [
                'scores' => [
                    $enrollment->id => [$dailyComponent->id => 75],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('needs_revision', $assessmentSet->refresh()->status);
        $this->assertDatabaseHas('diniyyah_scores', [
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'diniyyah_score_component_id' => $dailyComponent->id,
            'class_enrollment_id' => $enrollment->id,
            'score' => 75,
        ]);
    }

    public function test_admin_cannot_edit_scores_when_ledger_locked(): void
    {
        [$assessmentSet, $teacher, $enrollment, , $dailyComponent] = $this->makeInputContext();
        $admin = $this->makeAdmin();

        DiniyyahLedgerSnapshot::create([
            'academic_term_id' => $assessmentSet->classSubject->classroomTerm->academic_term_id,
            'classroom_term_id' => $assessmentSet->classSubject->classroom_term_id,
            'title' => 'Leger Semester',
            'status' => 'locked',
        ]);

        $this->actingAs($admin)
            ->put(route('guru.diniyyah-scores.update', $assessmentSet), [
                'scores' => [
                    $enrollment->id => [$dailyComponent->id => 80],
                ],
            ])
            ->assertForbidden();
    }

    // ---------------------------------------------------------------- helpers

    private function attendanceScore(DiniyyahAssessmentSet $set, DiniyyahScoreComponent $component, ClassEnrollment $enrollment): float
    {
        $score = DiniyyahScore::where([
            'diniyyah_assessment_set_id' => $set->id,
            'diniyyah_score_component_id' => $component->id,
            'class_enrollment_id' => $enrollment->id,
        ])->first();

        $this->assertNotNull($score, "Skor presensi untuk enrollment {$enrollment->id} belum dihitung observer.");

        return (float) $score->score;
    }

    /** @return array{DiniyyahAssessmentSet, object{user_id:int,assignment_id:int,user:User}, ClassEnrollment, ClassEnrollment, DiniyyahScoreComponent} */
    private function makeJournalContext(): array
    {
        $teacher = $this->makeTeacher('Guru Fiqih');
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Semester Genap', 'semester' => 'genap']);
        $classroom = Classroom::create(['name' => 'Mustawa 1 Ikhwan']);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => 'Mustawa 1 Ikhwan',
        ]);

        $studentA = Student::create(['name' => 'Santri A', 'gender' => 'male', 'nis' => '001']);
        $studentB = Student::create(['name' => 'Santri B', 'gender' => 'male', 'nis' => '002']);
        $enrollmentA = ClassEnrollment::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'student_id' => $studentA->id,
            'status' => 'active',
        ]);
        $enrollmentB = ClassEnrollment::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'student_id' => $studentB->id,
            'status' => 'active',
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
        $assignment = DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $classSubject->id,
            'teacher_id' => $teacher->id,
            'assignment_role' => 'primary',
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
        $attendanceComponent = DiniyyahScoreComponent::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'code' => 'keaktifan_presensi',
            'name' => 'Keaktifan & Presensi',
            'component_group' => 'daily',
            'is_required' => true,
        ]);

        $teacherBag = new class($teacher->user_id, $assignment->id, $teacher->user) {
            public function __construct(public int $user_id, public int $assignment_id, public User $user) {}
        };

        return [$assessmentSet, $teacherBag, $enrollmentA, $enrollmentB, $attendanceComponent];
    }

    /** @return array{DiniyyahAssessmentSet, object, ClassEnrollment, mixed, DiniyyahScoreComponent} */
    private function makeInputContext(): array
    {
        $teacher = $this->makeTeacher('Guru Fiqih');
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
            'status' => 'active',
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
        DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $classSubject->id,
            'teacher_id' => $teacher->id,
            'assignment_role' => 'primary',
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
        $dailyComponent = DiniyyahScoreComponent::create([
            'diniyyah_assessment_set_id' => $assessmentSet->id,
            'code' => 'harian',
            'name' => 'Harian',
            'component_group' => 'daily',
            'is_required' => true,
        ]);

        return [$assessmentSet, $teacher, $enrollment, null, $dailyComponent];
    }

    private function makeTeacher(string $name): Teacher
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('guru');

        return Teacher::create(['user_id' => $user->id, 'name' => $name]);
    }

    private function makeAdmin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => 'Admin']);
        $user->assignRole('admin');

        return $user;
    }
}