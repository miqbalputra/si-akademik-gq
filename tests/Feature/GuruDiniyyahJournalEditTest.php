<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassSubject;
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
 * Fitur "Riwayat Jurnal Saya" (grouping per tanggal, tanpa filter) + edit jurnal
 * (materi + presensi) di menu Jurnal Kelas Diniyyah.
 */
class GuruDiniyyahJournalEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_riwayat_grouped_by_date_with_count_and_excludes_substitute(): void
    {
        $ctx = $this->makeContext();

        // Dua jurnal sebagai guru asli di tanggal berbeda.
        $this->createJournal($ctx, '2026-07-09', '1', 'Materi Kamis pagi');
        $this->createJournal($ctx, '2026-07-10', '2', 'Materi Jumat');

        // Satu jurnal pengganti (substitute) di assignment yang sama — TIDAK boleh muncul.
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
            'substitute_teacher_id' => $ctx['otherTeacher']->id,
            'date' => '2026-07-09',
            'session_hour' => '2',
            'material' => 'Materi pengganti rahasia',
            'jp_count' => 1,
        ]);

        $resp = $this->actingAs($ctx['teacher']->user)
            ->get(route('guru.diniyyah-journals.index'));

        $resp->assertOk();
        $resp->assertSee('Riwayat Jurnal Saya');
        $resp->assertSee('2 jurnal'); // badge count — hanya jurnal sebagai guru asli
        $resp->assertSee('Materi Kamis pagi');
        $resp->assertSee('Materi Jumat');
        $resp->assertDontSee('Materi pengganti rahasia');
    }

    public function test_riwayat_does_not_require_date_selection(): void
    {
        $ctx = $this->makeContext();
        $this->createJournal($ctx, '2026-07-09', '1', 'Tanpa filter tetap tampil');

        // Akses tanpa query string date/classroom sama sekali.
        $resp = $this->actingAs($ctx['teacher']->user)
            ->get(route('guru.diniyyah-journals.index'));

        $resp->assertOk();
        $resp->assertSee('Riwayat Jurnal Saya');
        $resp->assertSee('Tanpa filter tetap tampil');
    }

    public function test_edit_page_loads_for_owner_and_prefills_material(): void
    {
        $ctx = $this->makeContext();
        $journal = $this->createJournal($ctx, '2026-07-09', '1', 'Pengantar Fiqih Bab 1');

        $resp = $this->actingAs($ctx['teacher']->user)
            ->get(route('guru.diniyyah-journals.edit', $journal));

        $resp->assertOk();
        $resp->assertSee('Edit Jurnal');
        $resp->assertSee('Pengantar Fiqih Bab 1');
    }

    public function test_edit_page_forbidden_for_non_owner_and_for_substitute_journal(): void
    {
        $ctx = $this->makeContext();
        $journal = $this->createJournal($ctx, '2026-07-09', '1', 'Materi asli');

        // Guru lain → 403.
        $this->actingAs($ctx['otherTeacher']->user)
            ->get(route('guru.diniyyah-journals.edit', $journal))
            ->assertForbidden();

        // Jurnal pengganti → 403 untuk guru asli pemilik assignment.
        $sub = DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
            'substitute_teacher_id' => $ctx['otherTeacher']->id,
            'date' => '2026-07-09',
            'session_hour' => '2',
            'material' => 'Materi pengganti',
            'jp_count' => 1,
        ]);
        $this->actingAs($ctx['teacher']->user)
            ->get(route('guru.diniyyah-journals.edit', $sub))
            ->assertForbidden();
    }

    public function test_update_changes_material_and_syncs_absences(): void
    {
        $ctx = $this->makeContext();
        $journal = $this->createJournal($ctx, '2026-07-09', '1', 'Materi lama');
        $enrollmentId = $ctx['enrollments'][0]->id;

        // Update materi + tandai 1 santri tidak hadir (skipped).
        $resp = $this->actingAs($ctx['teacher']->user)
            ->put(route('guru.diniyyah-journals.update', $journal), [
                'material' => 'Materi baru hasil edit',
                'absences' => [$enrollmentId => 'skipped'],
            ]);

        $resp->assertRedirect(route('guru.diniyyah-journals.index', [
            'classroom_term_id' => $ctx['classroomTerm']->id,
            'date' => '2026-07-09',
        ]));
        $this->assertSame('Materi baru hasil edit', $journal->fresh()->material);
        $this->assertSame(1, $journal->fresh()->absences()->count());

        // Update lagi tanpa absensi → sync menghapus absence yang ada.
        $this->actingAs($ctx['teacher']->user)
            ->put(route('guru.diniyyah-journals.update', $journal), [
                'material' => 'Materi tanpa absensi',
                'absences' => [],
            ]);

        $this->assertSame(0, $journal->fresh()->absences()->count());
        $this->assertSame('Materi tanpa absensi', $journal->fresh()->material);
    }

    public function test_update_forbidden_for_non_owner(): void
    {
        $ctx = $this->makeContext();
        $journal = $this->createJournal($ctx, '2026-07-09', '1', 'Materi asli');

        $this->actingAs($ctx['otherTeacher']->user)
            ->put(route('guru.diniyyah-journals.update', $journal), [
                'material' => 'Upaya ubah oleh guru lain',
                'absences' => [],
            ])
            ->assertForbidden();

        $this->assertSame('Materi asli', $journal->fresh()->material);
    }

    /**
     * Buat jurnal sebagai guru asli (bukan pengganti).
     */
    private function createJournal(array $ctx, string $date, string $session, string $material): DiniyyahClassJournal
    {
        return DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
            'date' => $date,
            'session_hour' => $session,
            'material' => $material,
            'jp_count' => 1,
        ]);
    }

    private function makeContext(): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => 'Guru Pemilik']);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => 'Guru Pemilik']);

        $otherUser = User::factory()->create(['name' => 'Guru Lain']);
        $otherUser->assignRole('guru');
        $otherTeacher = Teacher::create(['user_id' => $otherUser->id, 'name' => 'Guru Lain']);

        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Genap', 'semester' => 'genap']);
        $classroom = Classroom::create(['name' => 'Mustawa 2 Ikhwan']);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => 'Mustawa 2 Ikhwan',
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

        $enrollments = [];
        foreach (['Santri A', 'Santri B'] as $i => $name) {
            $student = Student::create(['name' => $name, 'gender' => 'L', 'nis' => 'TEST-' . ($i + 1)]);
            $enrollments[] = ClassEnrollment::create([
                'academic_term_id' => $term->id,
                'classroom_term_id' => $classroomTerm->id,
                'student_id' => $student->id,
                'roll_number' => (string) ($i + 1),
                'status' => 'active',
            ]);
        }

        return [
            'teacher' => $teacher,
            'otherTeacher' => $otherTeacher,
            'classroomTerm' => $classroomTerm,
            'assignment' => $assignment,
            'enrollments' => $enrollments,
        ];
    }
}