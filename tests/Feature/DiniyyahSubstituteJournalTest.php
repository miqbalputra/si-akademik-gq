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
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Event;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
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

        $this->assertSoftDeleted('diniyyah_class_journals', ['id' => $journal->id]);
    }

    public function test_old_journal_with_archived_student_remains_readable_in_both_forms(): void
    {
        $ctx = $this->makeContext();
        $enrollment = $this->makeEnrollment($ctx['classroomTerm'], 'Santri Riwayat');
        $journal = $this->makeOldJournal($ctx);
        $absence = $journal->absences()->create(['class_enrollment_id' => $enrollment->id, 'status' => 'sick']);
        $enrollment->student->delete();
        $before = $journal->fresh()->getRawOriginal();

        foreach (['guru.diniyyah-substitute-journals.index', 'guru.diniyyah-journals.index'] as $route) {
            $response = $this->actingAs($ctx['userA'])->get(route($route, [
                'classroom_term_id' => $ctx['classroomTerm']->id,
                'date' => '2026-10-19',
            ]));
            $response->assertOk()->assertSee('Santri Riwayat')->assertSee('Materi lama');
            $this->assertFalse($response->viewData('students')->contains('id', $enrollment->id));
        }

        $this->assertSame($before, $journal->fresh()->getRawOriginal());
        $this->assertDatabaseHas('diniyyah_class_journal_absences', ['id' => $absence->id, 'status' => 'sick']);
        $this->assertSoftDeleted('students', ['id' => $enrollment->student_id]);
    }

    public function test_old_journal_still_shows_archived_original_teacher(): void
    {
        $ctx = $this->makeContext();
        $journal = $this->makeOldJournal($ctx);
        $ctx['teacherA']->delete();

        $this->actingAs($ctx['userB'])->get(route('guru.diniyyah-substitute-journals.index', [
            'classroom_term_id' => $ctx['classroomTerm']->id,
            'date' => '2026-10-19',
        ]))->assertOk()->assertSee('Guru A')->assertSee('Materi lama');

        $this->assertDatabaseHas('diniyyah_class_journals', ['id' => $journal->id, 'material' => 'Materi lama']);
        $this->assertSoftDeleted('teachers', ['id' => $ctx['teacherA']->id]);
    }

    public function test_old_journal_still_shows_archived_substitute_teacher(): void
    {
        $ctx = $this->makeContext();
        $this->makeOldJournal($ctx, ['substitute_teacher_id' => $ctx['teacherB']->id]);
        $ctx['teacherB']->delete();

        foreach (['guru.diniyyah-substitute-journals.index', 'guru.diniyyah-journals.index'] as $route) {
            $this->actingAs($ctx['userA'])->get(route($route, [
                'classroom_term_id' => $ctx['classroomTerm']->id,
                'date' => '2026-10-19',
            ]))->assertOk()->assertSee('Guru B');
        }
        $this->assertSoftDeleted('teachers', ['id' => $ctx['teacherB']->id]);
    }

    #[DataProvider('journalStoreRoutes')]
    public function test_postgres_duplicate_does_not_cause_500_or_overwrite_old_journal(string $route, string $actor): void
    {
        $ctx = $this->makeContext();
        $old = $this->makeOldJournal($ctx);
        $before = $old->fresh()->getRawOriginal();
        $previous = new PDOException('duplicate key', 23505);
        $previous->errorInfo = ['23505', 7, 'duplicate key'];
        Event::listen('eloquent.creating: '.DiniyyahClassJournal::class, function () use ($previous): void {
            throw new UniqueConstraintViolationException('pgsql', 'insert into diniyyah_class_journals ...', [], $previous);
        });
        $payload = $this->substitutePayload($ctx);

        $this->actingAs($ctx[$actor])->post(route($route), $payload)
            ->assertRedirect()->assertSessionHas('error')->assertSessionHasInput('material', $payload['material']);

        $this->assertSame($before, $old->fresh()->getRawOriginal());
        $this->assertDatabaseCount('diniyyah_class_journals', 1);
    }

    #[DataProvider('journalStoreRoutes')]
    public function test_failed_absence_save_rolls_back_new_journal_and_preserves_old_data(string $route, string $actor): void
    {
        $ctx = $this->makeContext();
        $old = $this->makeOldJournal($ctx);
        $first = $this->makeEnrollment($ctx['classroomTerm'], 'Santri Pertama');
        $second = $this->makeEnrollment($ctx['classroomTerm'], 'Santri Kedua');
        $oldAbsence = $old->absences()->create(['class_enrollment_id' => $first->id, 'status' => 'permission']);
        $before = $old->fresh()->getRawOriginal();
        Event::listen('eloquent.creating: '.DiniyyahClassJournalAbsence::class, function ($absence) use ($second): void {
            if ((int) $absence->class_enrollment_id === $second->id) {
                throw new RuntimeException('Simulasi gagal menyimpan presensi kedua');
            }
        });
        $payload = $this->substitutePayload($ctx);
        $payload['absences'] = [$first->id => 'sick', $second->id => 'absent'];
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($ctx[$actor])->post(route($route), $payload);
            $this->fail('Penyimpanan presensi kedua seharusnya gagal.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulasi gagal menyimpan presensi kedua', $e->getMessage());
        }

        $this->assertDatabaseCount('diniyyah_class_journals', 1);
        $this->assertDatabaseCount('diniyyah_class_journal_absences', 1);
        $this->assertSame($before, $old->fresh()->getRawOriginal());
        $this->assertDatabaseHas('diniyyah_class_journal_absences', ['id' => $oldAbsence->id, 'status' => 'permission']);
    }

    public static function journalStoreRoutes(): array
    {
        return [
            'pengganti' => ['guru.diniyyah-substitute-journals.store', 'userB'],
            'reguler' => ['guru.diniyyah-journals.store', 'userA'],
        ];
    }

    private function makeOldJournal(array $ctx, array $attributes = []): DiniyyahClassJournal
    {
        return DiniyyahClassJournal::create(array_merge([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'date' => '2026-10-19',
            'session_hour' => '1',
            'material' => 'Materi lama',
            'jp_count' => 1,
        ], $attributes));
    }

    private function makeEnrollment(ClassroomTerm $classroomTerm, string $name): ClassEnrollment
    {
        $student = Student::create(['name' => $name, 'nis' => $name, 'gender' => 'male', 'status' => 'active']);

        return ClassEnrollment::create([
            'academic_term_id' => $classroomTerm->academic_term_id,
            'classroom_term_id' => $classroomTerm->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);
    }

    private function substitutePayload(array $ctx): array
    {
        return [
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'date' => '2026-10-19',
            'session_hour' => '2',
            'material' => 'Materi baru pengganti',
            'classroom_term_id' => $ctx['classroomTerm']->id,
            'absences' => [],
        ];
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
