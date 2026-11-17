<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentDeparture;
use App\Models\TasmiExaminerAssignment;
use App\Models\TasmiRecord;
use App\Models\Teacher;
use App\Models\User;
use App\Services\StudentDepartureWorkflow;
use App\Services\TasmiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public static function examinerGenders(): array
    {
        return ['ustadz' => ['male'], 'ustadzah' => ['female']];
    }

    #[DataProvider('examinerGenders')]
    public function test_examiner_can_store_and_update_with_strict_numeric_audit_columns(string $gender): void
    {
        $ctx = $this->makeContext($gender);
        TasmiExaminerAssignment::create(['academic_term_id' => $ctx['term']->id, 'teacher_id' => $ctx['teacher']->id, 'status' => 'active']);
        // SQLite normally accepts text in DECIMAL columns. Emulate the production
        // numeric-column constraint so predicates cannot silently pass this test.
        DB::unprepared("CREATE TRIGGER strict_audit_scores BEFORE INSERT ON score_change_logs
            WHEN (NEW.old_score IS NOT NULL AND typeof(NEW.old_score) NOT IN ('integer', 'real'))
              OR (NEW.new_score IS NOT NULL AND typeof(NEW.new_score) NOT IN ('integer', 'real'))
            BEGIN SELECT RAISE(ABORT, 'audit scores must be numeric'); END");

        $this->actingAs($ctx['user'])->get(route('guru.tasmi.create', ['classroom_term_id' => $ctx['classroomTerm']->id]))
            ->assertOk()->assertSee($ctx['student']->name);
        $this->post(route('guru.tasmi.store'), [
            'classroom_term_id' => $ctx['classroomTerm']->id, 'student_id' => $ctx['student']->id,
            'exam_type' => '1_juz', 'juz_single' => 30, 'exam_date' => '2026-08-15', 'predicate' => 'jayyid',
        ])->assertRedirect(route('guru.tasmi.records'));

        $record = TasmiRecord::sole();
        $this->assertDatabaseHas('activity_log', ['subject_id' => $record->id, 'subject_type' => TasmiRecord::class, 'description' => 'created_tasmi_record']);
        $activity = DB::table('activity_log')->where('description', 'created_tasmi_record')->first();
        $this->assertSame('jayyid', json_decode($activity->properties, true)['attributes']['predicate']);

        $this->put(route('guru.tasmi.update', $record), [
            'exam_type' => '1_juz', 'juz_start' => 30, 'juz_end' => 30, 'exam_date' => '2026-08-15', 'predicate' => 'mumtaz',
        ])->assertRedirect(route('guru.tasmi.edit', $record));
        $this->assertSame('mumtaz', $record->fresh()->predicate);
        $activity = DB::table('activity_log')->where('description', 'updated_tasmi_record')->first();
        $this->assertSame(['from' => 'jayyid', 'to' => 'mumtaz'], json_decode($activity->properties, true)['changes']['predicate']);
        $this->assertDatabaseCount('score_change_logs', 2);
    }

    public function test_duplicate_date_on_update_returns_validation_and_preserves_both_records(): void
    {
        $ctx = $this->makeContext();
        TasmiExaminerAssignment::create(['academic_term_id' => $ctx['term']->id, 'teacher_id' => $ctx['teacher']->id, 'status' => 'active']);
        $data = [
            'academic_term_id' => $ctx['term']->id, 'classroom_term_id' => $ctx['classroomTerm']->id,
            'class_enrollment_id' => $ctx['enrollment']->id, 'student_id' => $ctx['student']->id,
            'examiner_teacher_id' => $ctx['teacher']->id, 'exam_type' => '1_juz', 'juz_start' => 30, 'juz_end' => 30,
            'predicate' => 'jayyid', 'input_by' => $ctx['user']->id,
        ];
        TasmiRecord::create($data + ['exam_date' => '2026-08-15']);
        $record = TasmiRecord::create($data + ['exam_date' => '2026-08-16']);
        $before = DB::table('tasmi_records')->orderBy('id')->get()->toJson();
        $auditCount = DB::table('score_change_logs')->count();

        $this->actingAs($ctx['user'])->from(route('guru.tasmi.edit', $record))->put(route('guru.tasmi.update', $record), [
            'exam_type' => '1_juz', 'juz_start' => 30, 'juz_end' => 30, 'exam_date' => '2026-08-15', 'predicate' => 'mumtaz',
        ])->assertRedirect(route('guru.tasmi.edit', $record))->assertSessionHasErrors('exam_date');

        $this->assertSame($before, DB::table('tasmi_records')->orderBy('id')->get()->toJson());
        $this->assertSame($auditCount, DB::table('score_change_logs')->count());
    }

    public function test_optional_hijri_date_does_not_block_saving_if_conversion_is_unavailable(): void
    {
        $ctx = $this->makeContext();
        TasmiExaminerAssignment::create(['academic_term_id' => $ctx['term']->id, 'teacher_id' => $ctx['teacher']->id, 'status' => 'active']);
        $this->partialMock(TasmiService::class)->shouldReceive('hijriDateFor')->andReturnNull();

        $this->actingAs($ctx['user'])->post(route('guru.tasmi.store'), [
            'classroom_term_id' => $ctx['classroomTerm']->id, 'student_id' => $ctx['student']->id,
            'exam_type' => '1_juz', 'juz_single' => 30, 'exam_date' => '2026-08-15', 'predicate' => 'jayyid',
        ])->assertRedirect(route('guru.tasmi.records'));
        $record = TasmiRecord::sole();
        $this->put(route('guru.tasmi.update', $record), [
            'exam_type' => '1_juz', 'juz_start' => 30, 'juz_end' => 30, 'exam_date' => '2026-08-15', 'predicate' => 'mumtaz',
        ])->assertRedirect(route('guru.tasmi.edit', $record));
        $this->assertNull($record->fresh()->hijri_date);
    }

    public function test_failed_audit_rolls_back_create_and_update_without_changing_existing_data(): void
    {
        $ctx = $this->makeContext();
        TasmiExaminerAssignment::create(['academic_term_id' => $ctx['term']->id, 'teacher_id' => $ctx['teacher']->id, 'status' => 'active']);
        DB::unprepared("CREATE TRIGGER fail_tasmi_audit BEFORE INSERT ON score_change_logs
            WHEN NEW.score_table = 'tasmi_records'
            BEGIN SELECT RAISE(ABORT, 'forced audit failure'); END");

        $this->actingAs($ctx['user'])->post(route('guru.tasmi.store'), [
            'classroom_term_id' => $ctx['classroomTerm']->id, 'student_id' => $ctx['student']->id,
            'exam_type' => '1_juz', 'juz_single' => 30, 'exam_date' => '2026-08-15', 'predicate' => 'jayyid',
        ])->assertServerError();

        $this->assertDatabaseCount('tasmi_records', 0);
        $this->assertDatabaseCount('score_change_logs', 0);

        DB::unprepared('DROP TRIGGER fail_tasmi_audit');
        $this->post(route('guru.tasmi.store'), [
            'classroom_term_id' => $ctx['classroomTerm']->id, 'student_id' => $ctx['student']->id,
            'exam_type' => '1_juz', 'juz_single' => 30, 'exam_date' => '2026-08-15', 'predicate' => 'jayyid',
        ])->assertRedirect(route('guru.tasmi.records'));
        $record = TasmiRecord::sole();
        $tables = ['tasmi_records', 'score_change_logs', 'activity_log', 'panel_notifications'];
        $before = collect($tables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()]);
        DB::unprepared("CREATE TRIGGER fail_tasmi_update_audit BEFORE INSERT ON score_change_logs
            WHEN NEW.reason = 'updated_tasmi'
            BEGIN SELECT RAISE(ABORT, 'forced audit failure'); END");

        $this->put(route('guru.tasmi.update', $record), [
            'exam_type' => '1_juz', 'juz_start' => 30, 'juz_end' => 30, 'exam_date' => '2026-08-15', 'predicate' => 'mumtaz',
        ])->assertServerError();
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson(), "Partial update in {$table}");
        }
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
        $resp->assertSee('Juz 30 → 26');
        $resp->assertSee('id="juz-five-feedback"', false);
        $resp->assertSee('Pilih 5 juz berurutan. Boleh dari depan');
        $resp->assertSee('Maqbul');
        $resp->assertSee('Mumtaz');
        $resp->assertSee('>Senin</option>', false);
        $resp->assertSee('>Ahad</option>', false);
        $resp->assertSee('data-hijri-auto', false);
        $resp->assertSee('data-hijri-url="'.route('guru.tasmi.hijri-date').'"', false);
        $resp->assertSee('fetch(hijriInput.dataset.hijriUrl', false);
        $resp->assertSee('method="GET" action="'.route('guru.tasmi.create').'"', false);
        $resp->assertSee('name="classroom_term_id" value="'.$ctx['classroomTerm']->id.'"', false);
    }

    public function test_class_picker_does_not_submit_incomplete_tasmi_record(): void
    {
        $ctx = $this->makeContext('male');
        TasmiExaminerAssignment::create([
            'academic_term_id' => $ctx['term']->id,
            'teacher_id' => $ctx['teacher']->id,
            'status' => 'active',
        ]);

        $resp = $this->actingAs($ctx['user'])->get(route('guru.tasmi.create'));

        $resp->assertOk()
            ->assertSee('method="GET" action="'.route('guru.tasmi.create').'"', false)
            ->assertDontSee('validation.required')
            ->assertDontSee('Simpan Data Tasmi\'');
    }

    public function test_hijri_date_is_calculated_from_gregorian_date_in_wib(): void
    {
        $this->assertSame('9 Rabi\'ul Awwal 1448 H', app(TasmiService::class)->hijriDateFor('2026-08-21'));
    }

    public function test_examiner_can_fetch_standard_hijri_date_for_selected_gregorian_date(): void
    {
        $ctx = $this->makeContext('male');
        TasmiExaminerAssignment::create([
            'academic_term_id' => $ctx['term']->id,
            'teacher_id' => $ctx['teacher']->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($ctx['user'])->getJson(route('guru.tasmi.hijri-date', [
            'date' => '2026-08-21',
        ]));

        $response->assertOk()->assertExactJson([
            'hijri_date' => '9 Rabi\'ul Awwal 1448 H',
        ]);
    }

    public function test_existing_hijri_month_spelling_is_normalized_when_read(): void
    {
        $monthNames = [
            'Muharam' => 'Muharram',
            'Safar' => 'Shafar',
            'Rabiulawal' => "Rabi'ul Awwal",
            'Rabiulakhir' => "Rabi'ul Akhir",
            'Jumadilawal' => 'Jumadal Ula',
            'Jumadilakhir' => 'Jumadal Akhirah',
            'Rajab' => 'Rajab',
            'Syakban' => "Sya'ban",
            'Ramadan' => 'Ramadhan',
            'Syawal' => 'Syawwal',
            'Zulkaidah' => "Dzulqo'dah",
            'Zulhijah' => 'Dzulhijjah',
        ];

        foreach ($monthNames as $legacyName => $canonicalName) {
            $record = new TasmiRecord(['hijri_date' => "1 {$legacyName} 1448 H"]);
            $this->assertSame("1 {$canonicalName} 1448 H", $record->hijri_date);
        }
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
            // Bentuk payload dari form satu juz, termasuk batas atas yang
            // sebelumnya tidak dapat dipetakan oleh dropdown rentang 5 juz.
            'juz_single' => 30,
            'exam_day_label' => 'Hari 1',
            'exam_date' => '2026-08-15',
            'hijri_date' => '1 Shafar 1448 H',
            'predicate' => TasmiRecord::PREDICATE_MUMTAZ,
            'notes' => 'Lancar',
        ]);

        $resp->assertRedirect(route('guru.tasmi.records'));
        $this->assertDatabaseHas('tasmi_records', [
            'student_id' => $ctx['student']->id,
            'exam_type' => '1_juz',
            'juz_start' => 30,
            'juz_end' => 30,
            'predicate' => 'mumtaz',
            'hijri_date' => '3 Rabi\'ul Awwal 1448 H',
        ]);
        // Audit log tercatat.
        $this->assertDatabaseHas('score_change_logs', [
            'score_table' => 'tasmi_records',
            'new_score' => null,
            'reason' => 'created_tasmi',
        ]);

        $dashboard = $this->actingAs($ctx['user'])->get(route('guru.tasmi.index'));
        $dashboard->assertOk()->assertSee('Juz 30');
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

    public function test_examiner_can_store_and_view_5_juz_from_juz_1_to_5(): void
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
            'juz_end' => 5,
            'exam_day_label' => 'Senin',
            'exam_date' => '2026-08-20',
            'predicate' => TasmiRecord::PREDICATE_JAYYID,
        ]);

        $resp->assertRedirect();
        $this->assertDatabaseHas('tasmi_records', [
            'student_id' => $ctx['student']->id,
            'exam_type' => TasmiRecord::EXAM_TYPE_FIVE_JUZ,
            'juz_start' => 1,
            'juz_end' => 5,
            'predicate' => TasmiRecord::PREDICATE_JAYYID,
        ]);

        $dashboard = $this->actingAs($ctx['user'])->get(route('guru.tasmi.index'));
        $dashboard->assertOk()->assertSee('Juz 1 - 5');
    }

    public function test_examiner_can_store_and_view_5_juz_from_juz_30_to_26(): void
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
            'juz_start' => 30,
            'juz_end' => 26,
            'exam_day_label' => 'Senin',
            'exam_date' => '2026-08-21',
            'predicate' => TasmiRecord::PREDICATE_JAYYID,
        ]);

        $resp->assertRedirect();
        $this->assertDatabaseHas('tasmi_records', [
            'student_id' => $ctx['student']->id,
            'exam_type' => TasmiRecord::EXAM_TYPE_FIVE_JUZ,
            'juz_start' => 30,
            'juz_end' => 26,
            'predicate' => TasmiRecord::PREDICATE_JAYYID,
        ]);

        $dashboard = $this->actingAs($ctx['user'])->get(route('guru.tasmi.index'));
        $dashboard->assertOk()->assertSee('Juz 30 - 26');
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

        $resp->assertSessionHasErrors([
            'juz_end' => 'Untuk Tasmi\' 5 Juz, pilih rentang tepat 5 juz. Juz awal dan akhir boleh naik atau turun.',
        ]);
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
            'old_score' => null,
            'new_score' => null,
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

    public function test_archived_student_tasmi_history_is_hidden_and_direct_changes_are_blocked(): void
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
            'juz_start' => 30,
            'juz_end' => 30,
            'exam_date' => '2026-08-15',
            'predicate' => 'maqbul',
            'input_by' => $ctx['user']->id,
            'input_at' => now(),
        ]);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        app(StudentDepartureWorkflow::class)->depart($ctx['student'], [
            'type' => StudentDeparture::TYPE_LEFT,
            'effective_date' => '2026-10-01',
            'reason' => 'Keluar.',
        ], $admin);

        $this->actingAs($ctx['user'], 'web')
            ->get(route('guru.tasmi.records'))
            ->assertOk()
            ->assertDontSee('Santri Contoh');
        $this->get(route('guru.tasmi.edit', $record))->assertNotFound();
        $this->put(route('guru.tasmi.update', $record), [
            'exam_type' => '1_juz',
            'juz_start' => 30,
            'juz_end' => 30,
            'exam_date' => '2026-08-15',
            'predicate' => 'mumtaz',
        ])->assertNotFound();
        $this->delete(route('guru.tasmi.destroy', $record))->assertNotFound();
        $this->assertDatabaseHas('tasmi_records', ['id' => $record->id, 'deleted_at' => null]);
    }

    public function test_admin_can_access_tasmi_record_resource(): void
    {
        $admin = User::factory()->create(['name' => 'Admin']);
        $admin->assignRole('admin');
        $ctx = $this->makeContext();

        $resp = $this->actingAs($admin, 'admin')->get('/admin/tasmi-records');

        $resp->assertOk();
        $resp->assertSee('Record Tasmi\'');
    }

    public function test_kabag_tahfidz_can_access_examiner_assignment_resource(): void
    {
        $kabag = User::factory()->create(['name' => 'Kabag Tahfidz']);
        $kabag->assignRole('kabag_tahfidz');

        $resp = $this->actingAs($kabag, 'admin')->get('/admin/tasmi-examiner-assignments');

        $resp->assertOk();
        $resp->assertSee('PJ Tasmi\'');
    }

    public function test_guru_cannot_access_filament_tasmi_resources(): void
    {
        $ctx = $this->makeContext();
        // guru biasa tidak bisa masuk Filament panel
        $resp = $this->actingAs($ctx['user'], 'admin')->get('/admin/tasmi-records');
        $resp->assertForbidden();
    }
}
