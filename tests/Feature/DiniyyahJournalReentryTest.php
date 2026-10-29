<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\ClassSession;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SessionTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DiniyyahJournalReentryTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_29_000001_limit_journal_slot_uniqueness_to_active_records.php';

    public static function journalModes(): array
    {
        return [
            'reguler' => [false, false],
            'pengganti' => [true, false],
            'tafsir' => [false, true],
            'pengganti tafsir' => [true, true],
        ];
    }

    #[DataProvider('journalModes')]
    public function test_deleted_slot_can_be_filled_again_without_overwriting_history(bool $substitute, bool $tafsir): void
    {
        $ctx = $this->context($substitute, $tafsir);
        $old = $this->journal($ctx);
        $old->absences()->create(['class_enrollment_id' => $ctx['enrollment']->id, 'status' => 'sick']);
        $deleteRoute = $substitute ? 'guru.diniyyah-substitute-journals.destroy' : 'guru.diniyyah-journals.destroy';
        $storeRoute = 'guru.diniyyah-'.($substitute ? 'substitute-' : '').($tafsir ? 'tafsir-' : '').'journals.store';
        $this->actingAs($ctx['user'])->delete(route($deleteRoute, $old))->assertRedirect();
        $archived = DB::table('diniyyah_class_journals')->where('id', $old->id)->first();
        $oldAbsences = DB::table('diniyyah_class_journal_absences')->where('diniyyah_class_journal_id', $old->id)->get()->all();

        $payload = ['date' => $ctx['date'], 'material' => 'Materi perbaikan'];
        if ($tafsir) {
            $payload['assignments'] = [$ctx['assignment']->id];
        } else {
            $payload += [
                'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
                'classroom_term_id' => $ctx['classroomTerm']->id,
                'session_hour' => $ctx['session'],
                'absences' => [$ctx['enrollment']->id => 'skipped'],
            ];
            $response = $this->get(route(str_replace('.store', '.index', $storeRoute), [
                'classroom_term_id' => $ctx['classroomTerm']->id, 'date' => $ctx['date'],
            ]))->assertOk();
            $this->assertFalse($response->viewData('scheduledSlots')->firstWhere('assignment_id', $ctx['assignment']->id)->filled);
        }

        $this->post(route($storeRoute), $payload)->assertRedirect()->assertSessionHas('success');
        $replacement = DiniyyahClassJournal::sole();
        $this->assertNotSame($old->id, $replacement->id);
        $this->assertSame('Materi perbaikan', $replacement->material);
        $this->assertSame($substitute ? $ctx['teacher']->id : null, $replacement->substitute_teacher_id);
        $this->assertEquals($archived, DB::table('diniyyah_class_journals')->where('id', $old->id)->first());
        $this->assertEquals($oldAbsences, DB::table('diniyyah_class_journal_absences')->where('diniyyah_class_journal_id', $old->id)->get()->all());
        if (! $tafsir) {
            $this->assertSame('skipped', $replacement->absences()->sole()->status);
        }

        // Duplikat aktif tetap ditolak, sedangkan siklus hapus/isi ulang berikutnya diizinkan.
        $this->post(route($storeRoute), $payload)->assertRedirect()->assertSessionHas('error');
        $this->assertSame(1, DiniyyahClassJournal::count());
        $this->delete(route($deleteRoute, $replacement))->assertRedirect();
        $payload['material'] = 'Materi revisi kedua';
        $this->post(route($storeRoute), $payload)->assertRedirect()->assertSessionHas('success');
        $this->assertSame('Materi revisi kedua', DiniyyahClassJournal::sole()->material);
        $this->assertSame(3, DiniyyahClassJournal::withTrashed()->count());
        $this->assertEquals($archived, DB::table('diniyyah_class_journals')->where('id', $old->id)->first());
        $this->assertEquals($oldAbsences, DB::table('diniyyah_class_journal_absences')->where('diniyyah_class_journal_id', $old->id)->get()->all());
    }

    public function test_migration_preserves_every_existing_journal_and_absence(): void
    {
        $ctx = $this->context();
        $active = $this->journal($ctx);
        $active->absences()->create(['class_enrollment_id' => $ctx['enrollment']->id, 'status' => 'permission']);
        $archived = $this->journal($ctx, ['session_hour' => '1']);
        $archived->absences()->create(['class_enrollment_id' => $ctx['enrollment']->id, 'status' => 'sick']);
        $archived->delete();
        $migration = require database_path(self::MIGRATION);
        $migration->down();
        $before = DB::table('diniyyah_class_journals')->orderBy('id')->get()->all();
        $absences = DB::table('diniyyah_class_journal_absences')->orderBy('id')->get()->all();

        $migration->up();

        $this->assertEquals($before, DB::table('diniyyah_class_journals')->orderBy('id')->get()->all());
        $this->assertEquals($absences, DB::table('diniyyah_class_journal_absences')->orderBy('id')->get()->all());
        $this->assertSame(1, DiniyyahClassJournal::count());
        $this->assertSame(2, DiniyyahClassJournal::withTrashed()->count());
    }

    public function test_reported_fiqih_journal_can_be_edited_without_deleting_or_replacing_it(): void
    {
        $ctx = $this->context();
        $journal = $this->journal($ctx);
        $journal->absences()->create(['class_enrollment_id' => $ctx['enrollment']->id, 'status' => 'sick']);

        $this->actingAs($ctx['user'])->get(route('guru.diniyyah-journals.edit', $journal))
            ->assertOk()->assertSee('Mustawa 5 Akhwat')->assertSee('Fiqih')->assertSee('Materi asli');
        $this->put(route('guru.diniyyah-journals.update', $journal), [
            'material' => 'Materi hasil edit', 'absences' => [$ctx['enrollment']->id => 'permission'],
        ])->assertRedirect()->assertSessionHas('success');

        $updated = DiniyyahClassJournal::sole();
        $this->assertSame($journal->id, $updated->id);
        $this->assertSame('2026-10-26', $updated->date->toDateString());
        $this->assertSame('Materi hasil edit', $updated->material);
        $this->assertSame('permission', $updated->absences()->sole()->status);
        $this->assertSame(1, DiniyyahClassJournal::withTrashed()->count());
    }

    public function test_rollback_refuses_to_remove_archived_history_to_recreate_old_index(): void
    {
        $ctx = $this->context();
        $old = $this->journal($ctx);
        $old->delete();
        $replacement = $this->journal($ctx, ['material' => 'Materi pengganti arsip']);
        $before = DB::table('diniyyah_class_journals')->orderBy('id')->get()->all();
        $migration = require database_path(self::MIGRATION);

        try {
            $migration->down();
            $this->fail('Rollback yang bertentangan dengan riwayat harus ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('riwayat', $e->getMessage());
        }

        $this->assertEquals($before, DB::table('diniyyah_class_journals')->orderBy('id')->get()->all());
        $this->assertSame($replacement->id, DiniyyahClassJournal::sole()->id);
    }

    public function test_archived_journal_cannot_be_restored_over_an_active_replacement(): void
    {
        $ctx = $this->context();
        $old = $this->journal($ctx);
        $old->delete();
        $replacement = $this->journal($ctx, ['material' => 'Jurnal aktif']);

        try {
            $old->restore();
            $this->fail('Arsip tidak boleh dipulihkan menimpa slot aktif.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('date', $e->errors());
        }

        $this->assertSoftDeleted('diniyyah_class_journals', ['id' => $old->id]);
        $this->assertSame($replacement->id, DiniyyahClassJournal::sole()->id);
    }

    public function test_archived_journal_can_be_restored_when_its_slot_is_free(): void
    {
        $ctx = $this->context();
        $old = $this->journal($ctx);
        $old->absences()->create(['class_enrollment_id' => $ctx['enrollment']->id, 'status' => 'sick']);
        $old->delete();

        $this->assertTrue($old->restore());
        $this->assertSame($old->id, DiniyyahClassJournal::sole()->id);
        $this->assertSame('Materi asli', $old->fresh()->material);
        $this->assertSame('sick', $old->absences()->sole()->status);
    }

    private function journal(array $ctx, array $attributes = []): DiniyyahClassJournal
    {
        return DiniyyahClassJournal::create(array_merge([
            'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
            'substitute_teacher_id' => $ctx['substitute'] ? $ctx['teacher']->id : null,
            'date' => $ctx['date'], 'session_hour' => $ctx['session'], 'material' => 'Materi asli', 'jp_count' => 1,
        ], $attributes));
    }

    private function context(bool $substitute = false, bool $tafsir = false): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $originalUser = User::factory()->create();
        $originalUser->assignRole('guru');
        $originalTeacher = Teacher::create(['user_id' => $originalUser->id, 'name' => 'Ustadzah Fiqih', 'gender' => 'female']);
        $user = $originalUser;
        $teacher = $originalTeacher;
        if ($substitute) {
            $user = User::factory()->create();
            $user->assignRole('guru');
            $teacher = Teacher::create(['user_id' => $user->id, 'name' => 'Ustadzah Pengganti', 'gender' => 'female']);
        }
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil']);
        $subject = DiniyyahSubject::firstOrCreate(['code' => $tafsir ? 'tafsir' : 'fiqih'], ['name' => $tafsir ? 'Tafsir' : 'Fiqih']);
        $date = $tafsir ? '2026-10-29' : '2026-10-26';
        $session = $tafsir ? 'tafsir' : '2';
        $assignments = [];
        foreach ($tafsir ? [5, 6] : [5] as $level) {
            $classroom = Classroom::create(['name' => "Mustawa {$level} Akhwat", 'gender_group' => 'female']);
            SessionTimetable::seedForClassroom($classroom);
            $classroomTerm = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => $classroom->name]);
            $classSubject = DiniyyahClassSubject::create(['classroom_term_id' => $classroomTerm->id, 'subject_id' => $subject->id, 'assessment_method' => 'weighted', 'kkm' => 70]);
            $assignment = DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $classSubject->id, 'teacher_id' => $originalTeacher->id, 'assignment_role' => 'primary']);
            DiniyyahTeachingSchedule::create(['diniyyah_teacher_assignment_id' => $assignment->id, 'class_session_id' => ClassSession::where('session_name', $session)->sole()->id, 'day_of_week' => $tafsir ? 4 : 1]);
            $assignments[] = $assignment;
        }
        $assignment = $assignments[0];
        $classroomTerm = $assignment->classSubject->classroomTerm;
        $student = Student::create(['name' => 'Santriwati Riwayat', 'nis' => 'REENTRY-001', 'gender' => 'female', 'status' => 'active']);
        $enrollment = ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $classroomTerm->id, 'student_id' => $student->id, 'status' => 'active']);

        return compact('user', 'teacher', 'assignment', 'classroomTerm', 'enrollment', 'date', 'session', 'substitute');
    }
}
