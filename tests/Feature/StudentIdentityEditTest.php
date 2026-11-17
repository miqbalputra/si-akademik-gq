<?php

namespace Tests\Feature;

use App\Filament\Resources\Students\Pages\CreateStudent;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentIdentityEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public static function nisValues(): array
    {
        return [
            'leading zero' => ['0214480333'],
            'multiple leading zeros' => ['000123'],
            'zero' => ['0'],
            'long identifier without precision loss' => ['000123456789012345678901234567890'],
            'maximum database length' => [str_repeat('0', 254).'1'],
            'existing alphanumeric identifiers' => ['UJI-001'],
        ];
    }

    #[DataProvider('nisValues')]
    public function test_create_and_edit_preserve_nis_exactly_as_text(string $nis): void
    {
        Livewire::test(CreateStudent::class)
            ->fillForm(['name' => 'Santri Baru', 'gender' => 'male', 'nis' => $nis, 'status' => 'active'])
            ->call('create')
            ->assertHasNoFormErrors();

        $student = Student::sole();
        $this->assertSame($nis, $student->nis);

        // A name-only correction must not silently change the loaded NIS.
        Livewire::test(EditStudent::class, ['record' => $student->getRouteKey()])
            ->assertFormSet(['nis' => $nis])
            ->fillForm(['name' => 'Nama Diperbaiki'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($nis, $student->fresh()->nis);
        $this->assertSame('Nama Diperbaiki', $student->fresh()->name);
        $this->assertDatabaseCount('students', 1);
    }

    public function test_identity_correction_preserves_student_id_and_all_existing_related_records(): void
    {
        $student = Student::create([
            'name' => 'Abdul Izan', 'gender' => 'male', 'nis' => '214480333',
            'nik' => '0012345678901234', 'status' => 'active',
        ]);
        $this->createRelatedRecords($student);
        $tables = [
            'class_enrollments', 'student_guardians', 'student_attendances',
            'diniyyah_scores', 'diniyyah_assessment_results', 'tahfidz_report_entries',
            'diniyyah_ledger_snapshots', 'diniyyah_ledger_rows', 'diniyyah_ledger_cells',
            'report_cards', 'report_card_lines', 'report_card_snapshots',
        ];
        $before = collect($tables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()]);

        Livewire::test(EditStudent::class, ['record' => $student->getRouteKey()])
            ->fillForm(['name' => 'Abdul Izzan', 'nis' => '0214480333'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseHas('students', [
            'id' => $student->id, 'name' => 'Abdul Izzan', 'nis' => '0214480333',
            'nik' => '0012345678901234', 'status' => 'active', 'deleted_at' => null,
        ]);
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson(), "Related data changed: {$table}");
        }
        $this->assertSame($student->id, StudentAttendance::sole()->student->id);
        $this->assertSame($student->id, ClassEnrollment::sole()->student->id);
        $this->assertSame(1, $student->fresh()->guardians()->count());
    }

    public function test_duplicate_nis_is_rejected_without_merging_or_changing_either_student(): void
    {
        $first = Student::create(['name' => 'Pertama', 'gender' => 'male', 'nis' => '00123']);
        $second = Student::create(['name' => 'Kedua', 'gender' => 'male', 'nis' => '00456']);
        $before = Student::query()->orderBy('id')->get()->toJson();

        Livewire::test(EditStudent::class, ['record' => $second->getRouteKey()])
            ->fillForm(['name' => 'Nama Baru', 'nis' => $first->nis])
            ->call('save')
            ->assertHasFormErrors(['nis' => 'unique']);

        $this->assertSame($before, Student::query()->orderBy('id')->get()->toJson());
    }

    private function createRelatedRecords(Student $student): void
    {
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Semester Ganjil', 'semester' => 'ganjil', 'is_active' => true]);
        $classroom = Classroom::create(['name' => 'Kelas 5']);
        $classroomTerm = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => 'Kelas 5']);
        $enrollment = ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $classroomTerm->id, 'student_id' => $student->id]);
        $guardian = Guardian::create(['name' => 'Wali Santri']);
        $student->guardians()->attach($guardian->id, ['relationship' => 'ayah', 'is_primary' => true, 'can_login' => true]);
        StudentAttendance::create([
            'academic_term_id' => $term->id, 'classroom_term_id' => $classroomTerm->id,
            'class_enrollment_id' => $enrollment->id, 'student_id' => $student->id,
            'attendance_date' => '2025-07-14', 'status' => StudentAttendance::STATUS_SICK,
        ]);

        $subject = DB::table('diniyyah_subjects')->insertGetId(['name' => 'Fiqih', 'code' => 'FIQIH']);
        $classSubject = DB::table('diniyyah_class_subjects')->insertGetId(['classroom_term_id' => $classroomTerm->id, 'subject_id' => $subject, 'assessment_method' => 'exam_only']);
        $assessment = DB::table('diniyyah_assessment_sets')->insertGetId(['diniyyah_class_subject_id' => $classSubject, 'title' => 'UAS', 'assessment_method' => 'exam_only', 'status' => 'locked']);
        $component = DB::table('diniyyah_score_components')->insertGetId(['diniyyah_assessment_set_id' => $assessment, 'code' => 'UAS', 'name' => 'UAS', 'component_group' => 'exam']);
        DB::table('diniyyah_scores')->insert(['diniyyah_assessment_set_id' => $assessment, 'diniyyah_score_component_id' => $component, 'class_enrollment_id' => $enrollment->id, 'score' => 87.5, 'status' => 'locked']);
        DB::table('diniyyah_assessment_results')->insert(['diniyyah_assessment_set_id' => $assessment, 'class_enrollment_id' => $enrollment->id, 'final_score' => 87.5, 'is_complete' => true, 'locked_at' => now()]);
        DB::table('tahfidz_report_entries')->insert([
            'academic_term_id' => $term->id, 'student_id' => $student->id, 'version' => 1,
            'juz_tested' => '30', 'uas_score' => 90, 'sabaq_score' => 91, 'sabqi_score' => 92, 'manzil_score' => 93,
            'status' => 'final', 'input_by' => auth('admin')->id(), 'recorded_by' => auth('admin')->id(),
        ]);

        $ledger = DB::table('diniyyah_ledger_snapshots')->insertGetId(['academic_term_id' => $term->id, 'classroom_term_id' => $classroomTerm->id, 'title' => 'Leger Terkunci', 'status' => 'locked']);
        $row = DB::table('diniyyah_ledger_rows')->insertGetId(['diniyyah_ledger_snapshot_id' => $ledger, 'class_enrollment_id' => $enrollment->id, 'student_name' => $student->name, 'student_nis' => $student->nis, 'total_diniyyah_score' => 87.5, 'rank_in_class' => 1]);
        DB::table('diniyyah_ledger_cells')->insert(['diniyyah_ledger_row_id' => $row, 'column_key' => 'fiqih', 'label' => 'Fiqih', 'source_type' => 'diniyyah', 'source_id' => $assessment, 'value_numeric' => 87.5]);
        $report = DB::table('report_cards')->insertGetId(['academic_term_id' => $term->id, 'classroom_term_id' => $classroomTerm->id, 'class_enrollment_id' => $enrollment->id, 'student_id' => $student->id, 'status' => 'published', 'total_score' => 87.5, 'rank_in_class' => 1]);
        DB::table('report_card_lines')->insert(['report_card_id' => $report, 'line_type' => 'subject', 'source_type' => 'diniyyah', 'source_id' => $assessment, 'subject_name' => 'Fiqih', 'score_numeric' => 87.5]);
        DB::table('report_card_snapshots')->insert(['report_card_id' => $report, 'snapshot_data' => json_encode(['student_name' => $student->name, 'student_nis' => $student->nis, 'score' => 87.5]), 'generated_at' => now()]);
    }
}
