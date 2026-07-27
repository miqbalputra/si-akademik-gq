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
 * #3: double-submit jurnal tidak boleh menciptakan dua row. Penanganan:
 *  - exists() memberi pesan ramah (cek pertama).
 *  - unique index + try/catch QueryException menangkap race yang lolos exists().
 */
class DiniyyahJournalDoubleSubmitTest extends TestCase
{
    use RefreshDatabase;

    public function test_second_identical_submit_is_rejected_without_duplicate(): void
    {
        $context = $this->makeContext();
        $payload = [
            'diniyyah_teacher_assignment_id' => $context['assignment']->id,
            'date' => '2026-07-09',
            'session_hour' => '1',
            'material' => 'Bab 1',
            'classroom_term_id' => $context['classroomTerm']->id,
            'absences' => [],
        ];

        $first = $this->actingAs($context['teacher']->user)
            ->post(route('guru.diniyyah-journals.store'), $payload);
        $first->assertRedirect();

        $this->assertSame(1, DiniyyahClassJournal::where('diniyyah_teacher_assignment_id', $context['assignment']->id)->count());

        // Submit kedua identik (mis. double-klik / back-button) → ditolak, tidak menambah row.
        $second = $this->actingAs($context['teacher']->user)
            ->post(route('guru.diniyyah-journals.store'), $payload);

        $second->assertRedirect();
        $second->assertSessionHas('error');

        $this->assertSame(1, DiniyyahClassJournal::where('diniyyah_teacher_assignment_id', $context['assignment']->id)->count());
    }

    public function test_unique_index_blocks_duplicate_created_directly(): void
    {
        $context = $this->makeContext();

        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $context['assignment']->id,
            'date' => '2026-07-10',
            'session_hour' => '2',
            'material' => 'Bab 2',
            'jp_count' => 1,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $context['assignment']->id,
            'date' => '2026-07-10',
            'session_hour' => '2',
            'material' => 'Bab 2 duplikat',
            'jp_count' => 1,
        ]);
    }

    private function makeContext(): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => 'Guru Fiqih']);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => 'Guru Fiqih']);

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

        return [
            'teacher' => $teacher,
            'classroomTerm' => $classroomTerm,
            'assignment' => $assignment,
        ];
    }
}