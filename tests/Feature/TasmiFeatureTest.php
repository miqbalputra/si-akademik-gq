<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TasmiExaminerAssignment;
use App\Models\TasmiRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TasmiFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'kabag_tahfidz', 'kepala_sekolah', 'guru', 'wali_santri'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
    }

    private function makeContext(string $teacherGender = 'male'): array
    {
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => 'Ganjil 2026/2027',
            'semester' => 'ganjil',
            'starts_at' => '2026-07-13',
            'ends_at' => '2026-12-31',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['name' => 'Ustadz Test']);
        $user->assignRole('guru');
        $teacher = Teacher::create([
            'user_id' => $user->id,
            'name' => 'Ustadz Test',
            'gender' => $teacherGender,
            'niy' => 'N001',
            'status' => 'active',
        ]);

        $classroom = Classroom::create([
            'name' => $teacherGender === 'male' ? 'Mustawa 1 Ikhwan' : 'Mustawa 1 Akhwat',
            'gender_group' => $teacherGender,
            'level_name' => 'Mustawa 1',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => $classroom->name,
            'status' => 'active',
        ]);

        $student = Student::create([
            'name' => 'Santri Contoh',
            'gender' => $teacherGender,
            'nis' => '12345',
            'status' => 'active',
        ]);
        $enrollment = ClassEnrollment::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'student_id' => $student->id,
            'roll_number' => 1,
            'status' => 'active',
        ]);

        return compact('school', 'year', 'term', 'user', 'teacher', 'classroom', 'classroomTerm', 'student', 'enrollment');
    }

    public function test_guru_without_examiner_assignment_cannot_access_tasmi_index(): void
    {
        $ctx = $this->makeContext();

        $resp = $this->actingAs($ctx['user'])->get(route('guru.tasmi.index'));

        $resp->assertForbidden();
    }

    public function test_guru_examiner_can_open_tasmi_index(): void
    {
        $ctx = $this->makeContext('male');
        TasmiExaminerAssignment::create([
            'academic_term_id' => $ctx['term']->id,
            'teacher_id' => $ctx['teacher']->id,
            'status' => 'active',
        ]);

        $resp = $this->actingAs($ctx['user'])->get(route('guru.tasmi.index'));

        $resp->assertOk();
        // Apostrof di Blade di-escape menjadi &#039; — cari tanpa apostrof.
        $resp->assertSee('Ujian Tasmi', false);
        $resp->assertSee('Mustawa 1 Ikhwan'); // kelas sesuai gender
        $resp->assertSee('Input Tasmi', false);
    }

    public function test_ustadzah_only_sees_akhwat_classes(): void
    {
        $ctx = $this->makeContext('female');
        // Buat juga kelas ikhwan yang harus disembunyikan
        $ikhwan = Classroom::create(['name' => 'Mustawa 1 Ikhwan', 'gender_group' => 'male', 'level_name' => 'M1', 'sort_order' => 2, 'is_active' => true]);
        ClassroomTerm::create(['academic_term_id' => $ctx['term']->id, 'classroom_id' => $ikhwan->id, 'name' => $ikhwan->name, 'status' => 'active']);

        TasmiExaminerAssignment::create([
            'academic_term_id' => $ctx['term']->id,
            'teacher_id' => $ctx['teacher']->id,
            'status' => 'active',
        ]);

        $resp = $this->actingAs($ctx['user'])->get(route('guru.tasmi.index'));

        $resp->assertOk();
        $resp->assertSee('Mustawa 1 Akhwat');
        $resp->assertDontSee('Mustawa 1 Ikhwan');
    }

    public function test_examiner_can_open_create_page_and_sees_students(): void
    {
        $ctx = $this->makeContext('male');
        TasmiExaminerAssignment::create([
            'academic_term_id' => $ctx['term']->id,
            'teacher_id' => $ctx['teacher']->id,
            'status' => 'active',
        ]);

        $resp = $this->actingAs($ctx['user'])
            ->get(route('guru.tasmi.create', ['classroom_term_id' => $ctx['classroomTerm']->id]));

        $resp->assertOk();
        $resp->assertSee('Santri Contoh');
        $resp->assertSee('NIS 12345');
        $resp->assertSee('Tasmi\' 1 Juz');
        $resp->assertSee('Tasmi\' 5 Juz');
        $resp->assertSee('Maqbul');
        $resp->assertSee('Mumtaz');
    }

    public function test_examiner_can_store_1_juz_tasmi(): void
    {
        $ctx = $this->makeContext('male');
        TasmiExaminerAssignment::create([
            'academic_term_id' => $ctx['term']->id,
            'teacher_id' => $ctx['teacher']->id,
            'status' => 'active',
        ]);

        $resp = $this->actingAs($ctx['user'])->post(route('guru.tasmi.store'), [
            'classroom_term_id' => $ctx['classroomTerm']->id,
            'student_id' => $ctx['student']->id,
            'exam_type' => TasmiRecord::EXAM_TYPE_ONE_JUZ,
            'juz_start' => 30,
            'juz_end' => 30,
            'exam_day_label' => 'Hari 1',
            'exam_date' => '2026-08-15',
            'hijri_date' => '1 Shafar 1448 H',
            'predicate' => TasmiRecord::PREDICATE_MUMTAZ,
            'notes' => 'Lancar',
        ]);

        $resp->assertRedirect(route('guru.tasmi.records', TasmiRecord::first()));
        $this->assertDatabaseHas('tasmi_records', [
            'student_id' => $ctx['student']->id,
            'exam_type' => '1_juz',
            'juz_start' => 30,
            'juz_end' => 30,
            'predicate' => 'mumtaz',
        ]);
        // Audit log tercatat.
        $this->assertDatabaseHas('score_change_logs', [
            'score_table' => 'tasmi_records',
            'new_score' => 'mumtaz',
            'reason' => 'created_tasmi',
        ]);
    }

    public function test_examiner_cannot_store_1_juz_with_mismatched_juz_range(): void
    {
        $ctx = $this->makeContext('male');
        TasmiExaminerAssignment::create([
            'academic_term_id' => $ctx['term']->id,
            'teacher_id' => $ctx['teacher']->id,
            'status' => 'active',
        ]);

        $resp = $this->actingAs($ctx['user'])->post(route('guru.tasmi.store'), [
            'classroom_term_id' => $ctx['classroomTerm']->id,
            'student_id' => $ctx['student']->id,
            'exam_type' => TasmiRecord::EXAM_TYPE_ONE_JUZ,
            'juz_start' => 1,
            'juz_end' => 5, // tidak sama → harus ditolak
            'exam_date' => '2026-08-15',
            'predicate' => TasmiRecord::PREDICATE_MAQBUL,
        ]);

        $resp->assertSessionHasErrors(['juz_end']);
        $this->assertDatabaseMissing('tasmi_records', ['student_id' => $ctx['student']->id]);
    }

    public function test_examiner_can_store_5_juz_with_exact_5_range(): void
    {
        $ctx = $this->makeContext('male');
        TasmiExaminerAssignment::create([
            'academic_term_id' => $ctx['term']->id,
            'teacher_id' => $ctx['teacher']->id,
            'status' => 'active',
        ]);

        $resp = $this->actingAs($ctx['user'])->post(route('guru.tasmi.store'), [
            'classroom_term_id' => $ctx['classroomTerm']->id,
            'student_id' => $ctx['student']->id,
            'exam_type' => TasmiRecord::EXAM_TYPE_FIVE_JUZ,
            'juz_start' => 26,
            'juz_end' => 30, // 30 - 26 + 1 = 5 ✓
            'exam_date' => '2026-08-20',
            'predicate' => TasmiRecord::PREDICATE_JAYYID_JIDDAN,
        ]);

        $resp->assertRedirect();
        $this->assertDatabaseHas('tasmi_records', [
            'exam_type' => '5_juz',
            'juz_start' => 26,
            'juz_end' => 30,
            'predicate' => 'jayyid_jiddan',
        ]);
    }

    public function test_examiner_cannot_store_5_juz_with_wrong_range(): void
    {
        $ctx = $this->makeContext('male');
        TasmiExaminerAssignment::create([
            'academic_term_id' => $ctx['term']->id,
            'teacher_id' => $ctx['teacher']->id,
            'status' => 'active',
        ]);

        $resp = $this->actingAs($ctx['user'])->post(route('guru.tasmi.store'), [
            'classroom_term_id' => $ctx['classroomTerm']->id,
            'student_id' => $ctx['student']->id,
            'exam_type' => TasmiRecord::EXAM_TYPE_FIVE_JUZ,
            'juz_start' => 1,
            'juz_end' => 4, // 4 - 1 + 1 = 4 ≠ 5
            'exam_date' => '2026-08-20',
            'predicate' => TasmiRecord::PREDICATE_MAQBUL,
        ]);

        $resp->assertSessionHasErrors(['juz_end']);
        $this->assertDatabaseMissing('tasmi_records', ['exam_type' => '5_juz']);
    }

    public function test_duplicate_tasmi_same_student_type_date_rejected(): void
    {
        $ctx = $this->makeContext('male');
        TasmiExaminerAssignment::create([
            'academic_term_id' => $ctx['term']->id,
            'teacher_id' => $ctx['teacher']->id,
            'status' => 'active',
        ]);
        TasmiRecord::create([
            'academic_term_id' => $ctx['term']->id,
            'classroom_term_id' => $ctx['classroomTerm']->id,
            'class_enrollment_id' => $ctx['enrollment']->id,
            'student_id' => $ctx['student']->id,
            'examiner_teacher_id' => $ctx['teacher']->id,
            'exam_type' => '1_juz',
            'juz_start' => 30, 'juz_end' => 30,
            'exam_date' => '2026-08-15',
            'predicate' => 'mumtaz',
            'input_by' => $ctx['user']->id,
            'input_at' => now(),
        ]);

        $resp = $this->actingAs($ctx['user'])->post(route('guru.tasmi.store'), [
            'classroom_term_id' => $ctx['classroomTerm']->id,
            'student_id' => $ctx['student']->id,
            'exam_type' => '1_juz',
            'juz_start' => 30, 'juz_end' => 30,
            'exam_date' => '2026-08-15', // sama persis
            'predicate' => 'maqbul',
        ]);

        $resp->assertSessionHasErrors(['exam_date']);
        $this->assertEquals(1, TasmiRecord::count());
    }

    public function test_examiner_can_edit_own_record(): void
    {
        $ctx = $this->makeContext('male');
        TasmiExaminerAssignment::create([
            'academic_term_id' => $ctx['term']->id,
            'teacher_id' => $ctx['teacher']->id,
            'status' => 'active',
        ]);
        $record = TasmiRecord::create([
            'academic_term_id' => $ctx['term']->id,
            'classroom_term_id' => $ctx['classroomTerm']->id,
            'class_enrollment_id' => $ctx['enrollment']->id,
            'student_id' => $ctx['student']->id,
            'examiner_teacher_id' => $ctx['teacher']->id,
            'exam_type' => '1_juz',
            'juz_start' => 30, 'juz_end' => 30,
            'exam_date' => '2026-08-15',
            'predicate' => 'maqbul',
            'input_by' => $ctx['user']->id,
            'input_at' => now(),
        ]);

        $resp = $this->actingAs($ctx['user'])->put(route('guru.tasmi.update', $record), [
            'exam_type' => '1_juz',
            'juz_start' => 30, 'juz_end' => 30,
            'exam_date' => '2026-08-15',
            'predicate' => 'mumtaz', // upgrade
        ]);

        $resp->assertRedirect();
        $this->assertDatabaseHas('tasmi_records', ['id' => $record->id, 'predicate' => 'mumtaz']);
        // Audit log update tercatat
        $this->assertDatabaseHas('score_change_logs', [
            'score_id' => $record->id,
            'old_score' => 'maqbul',
            'new_score' => 'mumtaz',
            'reason' => 'updated_tasmi',
        ]);
    }

    public function test_examiner_cannot_edit_other_examiner_record(): void
    {
        $ctx = $this->makeContext('male');
        $assignment = TasmiExaminerAssignment::create([
            'academic_term_id' => $ctx['term']->id,
            'teacher_id' => $ctx['teacher']->id,
            'status' => 'active',
        ]);
        // Guru lain sebagai examiner
        $user2 = User::factory()->create(['name' => 'Guru Lain']);
        $user2->assignRole('guru');
        $teacher2 = Teacher::create(['user_id' => $user2->id, 'name' => 'Guru Lain', 'gender' => 'male', 'niy' => 'N002', 'status' => 'active']);
        TasmiExaminerAssignment::create(['academic_term_id' => $ctx['term']->id, 'teacher_id' => $teacher2->id, 'status' => 'active']);

        $record = TasmiRecord::create([
            'academic_term_id' => $ctx['term']->id,
            'classroom_term_id' => $ctx['classroomTerm']->id,
            'class_enrollment_id' => $ctx['enrollment']->id,
            'student_id' => $ctx['student']->id,
            'examiner_teacher_id' => $teacher2->id, // milik guru2
            'exam_type' => '1_juz',
            'juz_start' => 30, 'juz_end' => 30,
            'exam_date' => '2026-08-15',
            'predicate' => 'maqbul',
            'input_by' => $user2->id,
            'input_at' => now(),
        ]);

        $resp = $this->actingAs($ctx['user'])->put(route('guru.tasmi.update', $record), [
            'exam_type' => '1_juz',
            'juz_start' => 30, 'juz_end' => 30,
            'exam_date' => '2026-08-15',
            'predicate' => 'mumtaz',
        ]);

        $resp->assertForbidden();
    }

    public function test_examiner_can_delete_own_record(): void
    {
        $ctx = $this->makeContext('male');
        TasmiExaminerAssignment::create([
            'academic_term_id' => $ctx['term']->id,
            'teacher_id' => $ctx['teacher']->id,
            'status' => 'active',
        ]);
        $record = TasmiRecord::create([
            'academic_term_id' => $ctx['term']->id,
            'student_id' => $ctx['student']->id,
            'examiner_teacher_id' => $ctx['teacher']->id,
            'exam_type' => '1_juz',
            'juz_start' => 30, 'juz_end' => 30,
            'exam_date' => '2026-08-15',
            'predicate' => 'maqbul',
            'input_by' => $ctx['user']->id,
        ]);

        $resp = $this->actingAs($ctx['user'])->delete(route('guru.tasmi.destroy', $record));

        $resp->assertRedirect(route('guru.tasmi.records'));
        $this->assertSoftDeleted('tasmi_records', ['id' => $record->id]);
    }

    public function test_examiner_can_open_records_list_and_search(): void
    {
        $ctx = $this->makeContext('male');
        TasmiExaminerAssignment::create([
            'academic_term_id' => $ctx['term']->id,
            'teacher_id' => $ctx['teacher']->id,
            'status' => 'active',
        ]);
        TasmiRecord::create([
            'academic_term_id' => $ctx['term']->id,
            'classroom_term_id' => $ctx['classroomTerm']->id,
            'student_id' => $ctx['student']->id,
            'examiner_teacher_id' => $ctx['teacher']->id,
            'exam_type' => '1_juz',
            'juz_start' => 30, 'juz_end' => 30,
            'exam_date' => '2026-08-15',
            'predicate' => 'mumtaz',
            'input_by' => $ctx['user']->id,
        ]);

        $resp = $this->actingAs($ctx['user'])->get(route('guru.tasmi.records'));

        $resp->assertOk();
        $resp->assertSee('Santri Contoh');
        $resp->assertSee('Juz 30');
        $resp->assertSee('Mumtaz');
    }

    public function test_admin_can_access_tasmi_record_resource(): void
    {
        $admin = User::factory()->create(['name' => 'Admin']);
        $admin->assignRole('admin');
        $ctx = $this->makeContext();

        $resp = $this->actingAs($admin)->get('/admin/tasmi-records');

        $resp->assertOk();
        $resp->assertSee('Record Tasmi\'');
    }

    public function test_kabag_tahfidz_can_access_examiner_assignment_resource(): void
    {
        $kabag = User::factory()->create(['name' => 'Kabag Tahfidz']);
        $kabag->assignRole('kabag_tahfidz');

        $resp = $this->actingAs($kabag)->get('/admin/tasmi-examiner-assignments');

        $resp->assertOk();
        $resp->assertSee('PJ Tasmi\'');
    }

    public function test_guru_cannot_access_filament_tasmi_resources(): void
    {
        $ctx = $this->makeContext();
        // guru biasa tidak bisa masuk Filament panel
        $resp = $this->actingAs($ctx['user'])->get('/admin/tasmi-records');
        $resp->assertForbidden();
    }
}