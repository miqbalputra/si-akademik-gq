<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\Guardian;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuardianReportCardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_dashboard_shows_connected_children_and_only_own_published_report_cards(): void
    {
        [$guardianUser, $ownPublished, $ownDraft, $otherPublished] = $this->makeGuardianContext();

        $this->actingAs($guardianUser)
            ->get(route('wali.dashboard'))
            ->assertOk()
            ->assertSee($ownPublished->student->name)
            ->assertSee($ownDraft->student->name)
            ->assertSee('Anak Terhubung')
            ->assertSee('Rapor Terbit')
            ->assertSee('Buka Rapor Terbaru')
            ->assertSee('Rapor anak ini belum dipublikasikan.')
            ->assertDontSee($otherPublished->student->name);
    }

    public function test_guardian_can_view_own_published_report_card(): void
    {
        [$guardianUser, $ownPublished] = $this->makeGuardianContext();

        $this->actingAs($guardianUser)
            ->get(route('report-cards.show', $ownPublished))
            ->assertOk()
            ->assertSee($ownPublished->student->name);
    }

    public function test_guardian_can_print_own_published_report_card(): void
    {
        [$guardianUser, $ownPublished] = $this->makeGuardianContext();

        $this->actingAs($guardianUser)
            ->get(route('report-cards.print', $ownPublished))
            ->assertOk()
            ->assertSee('Cetak / Simpan PDF')
            ->assertSee('Dokumen ini dicetak dari Sistem Nilai Sekolah.')
            ->assertSee($ownPublished->student->name);
    }

    public function test_guardian_cannot_view_other_student_report_card(): void
    {
        [$guardianUser, , , $otherPublished] = $this->makeGuardianContext();

        $this->actingAs($guardianUser)
            ->get(route('report-cards.show', $otherPublished))
            ->assertForbidden();
    }

    public function test_guardian_cannot_print_other_student_report_card(): void
    {
        [$guardianUser, , , $otherPublished] = $this->makeGuardianContext();

        $this->actingAs($guardianUser)
            ->get(route('report-cards.print', $otherPublished))
            ->assertForbidden();
    }

    public function test_guardian_cannot_view_own_unpublished_report_card(): void
    {
        [$guardianUser, , $ownDraft] = $this->makeGuardianContext();

        $this->actingAs($guardianUser)
            ->get(route('report-cards.show', $ownDraft))
            ->assertForbidden();
    }

    public function test_guardian_cannot_print_own_unpublished_report_card(): void
    {
        [$guardianUser, , $ownDraft] = $this->makeGuardianContext();

        $this->actingAs($guardianUser)
            ->get(route('report-cards.print', $ownDraft))
            ->assertForbidden();
    }

    /** @return array{User, ReportCard, ReportCard, ReportCard} */
    private function makeGuardianContext(): array
    {
        Role::firstOrCreate(['name' => 'wali_santri', 'guard_name' => 'web']);

        $guardianUser = User::factory()->create(['name' => 'Wali Santri']);
        $guardianUser->assignRole('wali_santri');
        $guardian = Guardian::create([
            'user_id' => $guardianUser->id,
            'name' => 'Wali Santri',
        ]);

        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Semester Genap', 'semester' => 'genap']);
        $classroom = Classroom::create(['name' => 'Mustawa 1 Ikhwan']);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => 'Mustawa 1 Ikhwan',
        ]);

        $ownStudent = Student::create(['name' => 'Anak Sendiri', 'gender' => 'male', 'nis' => '001']);
        $ownDraftStudent = Student::create(['name' => 'Anak Draft', 'gender' => 'male', 'nis' => '002']);
        $otherStudent = Student::create(['name' => 'Anak Orang Lain', 'gender' => 'male', 'nis' => '003']);
        $guardian->students()->attach($ownStudent->id, ['relationship' => 'father', 'can_login' => true]);
        $guardian->students()->attach($ownDraftStudent->id, ['relationship' => 'father', 'can_login' => true]);

        $ownPublished = $this->makeReportCard($term, $classroomTerm, $ownStudent, 'published');
        $ownDraft = $this->makeReportCard($term, $classroomTerm, $ownDraftStudent, 'draft');
        $otherPublished = $this->makeReportCard($term, $classroomTerm, $otherStudent, 'published');

        return [$guardianUser, $ownPublished, $ownDraft, $otherPublished];
    }

    private function makeReportCard(AcademicTerm $term, ClassroomTerm $classroomTerm, Student $student, string $status): ReportCard
    {
        $enrollment = ClassEnrollment::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'student_id' => $student->id,
        ]);

        $reportCard = ReportCard::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'class_enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'report_type' => 'diniyyah',
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
            'total_score' => 170,
            'average_score' => 85,
            'rank_in_class' => 1,
        ]);

        $reportCard->lines()->create([
            'line_type' => 'subject',
            'source_type' => 'manual',
            'subject_name' => 'Fiqih',
            'score_numeric' => 85,
            'sort_order' => 10,
        ]);

        return $reportCard->load('student');
    }
}
