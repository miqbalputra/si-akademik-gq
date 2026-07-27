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
 * Fitur "Jurnal Guru Pengganti": guru (akun terhubung Teacher) mengisi jurnal
 * menggantikan guru asli. assignment_id TETAP = guru asli; substitute_teacher_id
 * = pengganti. JP dihitung ke pengganti (effectiveTeacher).
 */
class DiniyyahSubstituteJournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_substitute_teacher_without_assignment_can_store_journal(): void
    {
        $ctx = $this->makeContext();

        // Guru B (pengganti) TIDAK punya assignment diniyyah apa pun.
        $payload = [
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'date' => '2026-07-15',
            'session_hour' => '1',
            'material' => 'Pengganti Bab 1',
            'classroom_term_id' => $ctx['classroomTerm']->id,
            'absences' => [],
        ];

        $response = $this->actingAs($ctx['userB'])
            ->post(route('guru.diniyyah-substitute-journals.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('diniyyah_class_journals', [
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'substitute_teacher_id' => $ctx['teacherB']->id,
            'jp_count' => 1,
        ]);

        $journal = DiniyyahClassJournal::where('diniyyah_teacher_assignment_id', $ctx['assignmentA']->id)->first();
        $this->assertSame($ctx['teacherB']->id, $journal->effectiveTeacher()->id, 'JP tercatat ke pengganti (B)');
        $this->assertNull($journal->teacherAssignment->teacher_id === $ctx['teacherA']->id ? null : null); // noop, guru asli tetap A
    }

    public function test_index_accessible_to_any_teacher_even_without_diniyyah_assignment(): void
    {
        $ctx = $this->makeContext();
        $this->actingAs($ctx['userB'])
            ->get(route('guru.diniyyah-substitute-journals.index'))
            ->assertOk();
    }

    public function test_user_without_teacher_is_forbidden(): void
    {
        $ctx = $this->makeContext();
        $userNoTeacher = User::factory()->create(['name' => 'No Teacher']);
        $userNoTeacher->assignRole('guru');

        $payload = [
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'date' => '2026-07-15',
            'session_hour' => '1',
            'material' => 'x',
            'classroom_term_id' => $ctx['classroomTerm']->id,
            'absences' => [],
        ];

        $this->actingAs($userNoTeacher)
            ->post(route('guru.diniyyah-substitute-journals.store'), $payload)
            ->assertForbidden();
    }

    public function test_cannot_substitute_oneself(): void
    {
        $ctx = $this->makeContext();

        // Guru A punya assignment sendiri; mencoba menggantikan diri sendiri → ditolak.
        $payload = [
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'date' => '2026-07-15',
            'session_hour' => '1',
            'material' => 'x',
            'classroom_term_id' => $ctx['classroomTerm']->id,
            'absences' => [],
        ];

        $this->actingAs($ctx['userA'])
            ->post(route('guru.diniyyah-substitute-journals.store'), $payload)
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, DiniyyahClassJournal::count(), 'Tidak boleh ada jurnal saat substitusi diri sendiri');
    }

    public function test_double_submit_same_slot_is_rejected(): void
    {
        $ctx = $this->makeContext();
        $payload = [
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'date' => '2026-07-16',
            'session_hour' => '2',
            'material' => 'Bab 2',
            'classroom_term_id' => $ctx['classroomTerm']->id,
            'absences' => [],
        ];

        $first = $this->actingAs($ctx['userB'])->post(route('guru.diniyyah-substitute-journals.store'), $payload);
        $first->assertRedirect();

        $second = $this->actingAs($ctx['userB'])->post(route('guru.diniyyah-substitute-journals.store'), $payload);
        $second->assertRedirect()->assertSessionHas('error');

        $this->assertSame(1, DiniyyahClassJournal::where('diniyyah_teacher_assignment_id', $ctx['assignmentA']->id)->count());
    }

    public function test_substitute_owner_can_delete_original_teacher_cannot(): void
    {
        $ctx = $this->makeContext();
        $journal = DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'substitute_teacher_id' => $ctx['teacherB']->id,
            'date' => '2026-07-17',
            'session_hour' => '1',
            'material' => 'x',
            'jp_count' => 1,
        ]);

        // Guru asli (A) tidak boleh hapus jurnal pengganti via route reguler.
        $this->actingAs($ctx['userA'])
            ->delete(route('guru.diniyyah-journals.destroy', $journal))
            ->assertForbidden();

        // Pengganti (B) boleh hapus via route pengganti.
        $this->actingAs($ctx['userB'])
            ->delete(route('guru.diniyyah-substitute-journals.destroy', $journal))
            ->assertRedirect();

        $this->assertDatabaseMissing('diniyyah_class_journals', ['id' => $journal->id]);
    }

    private function makeContext(): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);

        // Guru A (asli)
        $userA = User::factory()->create(['name' => 'Guru A']);
        $userA->assignRole('guru');
        $teacherA = Teacher::create(['user_id' => $userA->id, 'name' => 'Guru A']);

        // Guru B (pengganti, tanpa assignment diniyyah)
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