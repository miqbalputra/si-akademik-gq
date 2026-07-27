<?php

namespace Tests\Feature;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Di akun guru asli, jurnal pengganti muncul dengan tanda "Anda sudah digantikan
 * oleh Guru XXX" dan TANPA tombol hapus (pengganti yang menghapus, via menu pengganti).
 */
class DiniyyahSubstituteJournalBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_original_teacher_sees_substitute_badge_and_no_delete_button(): void
    {
        $ctx = $this->makeContext();

        $journal = DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'substitute_teacher_id' => $ctx['teacherB']->id,
            'date' => '2026-07-15',
            'session_hour' => '1',
            'material' => 'Pengganti Bab 1',
            'jp_count' => 1,
        ]);

        // Guru asli (A) membuka Jurnal Kelas reguler untuk kelas+tanggal tsb.
        $response = $this->actingAs($ctx['userA'])
            ->get(route('guru.diniyyah-journals.index', [
                'classroom_term_id' => $ctx['classroomTerm']->id,
                'date' => '2026-07-15',
            ]));

        $response->assertOk();
        $response->assertSee('Anda sudah digantikan oleh');
        $response->assertSee($ctx['teacherB']->name);

        // Tombol hapus untuk jurnal pengganti TIDAK boleh dirender di halaman reguler.
        $response->assertDontSee(route('guru.diniyyah-journals.destroy', $journal));
    }

    public function test_original_teacher_cannot_delete_substitute_journal_via_regular_route(): void
    {
        $ctx = $this->makeContext();
        $journal = DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'substitute_teacher_id' => $ctx['teacherB']->id,
            'date' => '2026-07-15',
            'session_hour' => '1',
            'material' => 'x',
            'jp_count' => 1,
        ]);

        $this->actingAs($ctx['userA'])
            ->delete(route('guru.diniyyah-journals.destroy', $journal))
            ->assertForbidden();

        $this->assertDatabaseHas('diniyyah_class_journals', ['id' => $journal->id]);
    }

    private function makeContext(): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);

        $userA = User::factory()->create(['name' => 'Guru A']);
        $userA->assignRole('guru');
        $teacherA = Teacher::create(['user_id' => $userA->id, 'name' => 'Guru A']);

        $userB = User::factory()->create(['name' => 'Guru B']);
        $userB->assignRole('guru');
        $teacherB = Teacher::create(['user_id' => $userB->id, 'name' => 'Guru B']);

        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Genap', 'semester' => 'genap']);
        $classroom = Classroom::create(['name' => 'Mustawa 1']);
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
        $assignmentA = DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $classSubject->id,
            'teacher_id' => $teacherA->id,
            'assignment_role' => 'primary',
        ]);

        return [
            'userA' => $userA,
            'teacherA' => $teacherA,
            'userB' => $userB,
            'teacherB' => $teacherB,
            'classroomTerm' => $classroomTerm,
            'assignmentA' => $assignmentA,
        ];
    }
}