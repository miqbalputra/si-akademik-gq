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
 * Ekspor lengkap jurnal diniyyah (reguler + pengganti) untuk admin/kabag/kepsek.
 * Kolom "Guru Mengajar (untuk gaji)" = pengganti jika ada, else guru asli.
 */
class DiniyyahJournalExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_excel_export_containing_substitute_info(): void
    {
        $ctx = $this->makeContext();

        // Satu jurnal reguler (guru asli A) + satu jurnal pengganti (pengganti B menggantikan A di slot lain).
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'substitute_teacher_id' => null,
            'date' => '2026-07-15',
            'session_hour' => '1',
            'material' => 'Reguler Bab 1',
            'jp_count' => 1,
        ]);
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'substitute_teacher_id' => $ctx['teacherB']->id,
            'date' => '2026-07-16',
            'session_hour' => '2',
            'material' => 'Pengganti Bab 2',
            'jp_count' => 1,
        ]);

        $response = $this->actingAs($ctx['admin'])
            ->get(route('admin.diniyyah-journals.export', ['format' => 'excel']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.ms-excel');
        $response->assertSee('Guru Mengajar (untuk gaji)');
        $response->assertSee($ctx['teacherB']->name, false); // nama pengganti tampil
        $response->assertSee('Reguler Bab 1');
        $response->assertSee('Pengganti Bab 2');
    }

    public function test_csv_export_works_and_contains_substitute(): void
    {
        $ctx = $this->makeContext();
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'substitute_teacher_id' => $ctx['teacherB']->id,
            'date' => '2026-07-16',
            'session_hour' => '2',
            'material' => 'Pengganti Bab 2',
            'jp_count' => 1,
        ]);

        $response = $this->actingAs($ctx['admin'])
            ->get(route('admin.diniyyah-journals.export', ['format' => 'csv']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertSee($ctx['teacherB']->name, false);
        $response->assertSee('Pengganti Bab 2');
    }

    public function test_admin_can_download_a_real_xlsx_workbook(): void
    {
        $ctx = $this->makeContext();
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'date' => '2026-07-16',
            'session_hour' => '2',
            'material' => 'Materi XLSX',
            'jp_count' => 1,
        ]);

        $response = $this->actingAs($ctx['admin'])
            ->get(route('admin.diniyyah-journals.export', [
                'format' => 'xlsx',
                'academic_term_id' => $ctx['term']->id,
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'xlsx-test-');
        file_put_contents($path, $response->getContent());
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $this->assertStringContainsString('Materi XLSX', (string) $zip->getFromName('xl/worksheets/sheet2.xml'));
        $this->assertNotFalse($zip->getFromName('xl/workbook.xml'));
        $zip->close();
        @unlink($path);
    }

    public function test_management_report_can_download_pdf(): void
    {
        $ctx = $this->makeContext();
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'date' => '2026-07-16',
            'session_hour' => '2',
            'material' => 'Materi PDF',
            'jp_count' => 1,
        ]);

        $response = $this->actingAs($ctx['admin'])
            ->get(route('admin.diniyyah-journals.export', [
                'format' => 'pdf',
                'academic_term_id' => $ctx['term']->id,
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_guru_report_uses_effective_teacher_scope(): void
    {
        $ctx = $this->makeContext();
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'date' => '2026-07-15',
            'session_hour' => '1',
            'material' => 'Materi Guru Asli',
            'jp_count' => 1,
        ]);
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'substitute_teacher_id' => $ctx['teacherB']->id,
            'date' => '2026-07-16',
            'session_hour' => '2',
            'material' => 'Materi Guru Pengganti',
            'jp_count' => 1,
        ]);

        $this->actingAs($ctx['userA'])
            ->get(route('guru.diniyyah-journals.report'))
            ->assertOk()
            ->assertSee('Materi Guru Asli')
            ->assertDontSee('Materi Guru Pengganti');

        $this->actingAs($ctx['userB'])
            ->get(route('guru.diniyyah-journals.report'))
            ->assertOk()
            ->assertDontSee('Materi Guru Asli')
            ->assertSee('Materi Guru Pengganti');
    }

    public function test_guru_can_download_xlsx_report(): void
    {
        $ctx = $this->makeContext();
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'date' => '2026-07-16',
            'session_hour' => '2',
            'material' => 'Materi Laporan Guru',
            'jp_count' => 1,
        ]);

        $response = $this->actingAs($ctx['userA'])
            ->get(route('guru.diniyyah-journals.report.export', ['format' => 'xlsx']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('PK', substr($response->getContent(), 0, 2));
    }

    public function test_guru_is_forbidden_to_access_export(): void
    {
        $ctx = $this->makeContext();
        $this->actingAs($ctx['userA'])
            ->get(route('admin.diniyyah-journals.export'))
            ->assertForbidden();
    }

    public function test_tipe_substitute_filter_only_returns_substitute_journals(): void
    {
        $ctx = $this->makeContext();
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'substitute_teacher_id' => null,
            'date' => '2026-07-15',
            'session_hour' => '1',
            'material' => 'Reguler Bab 1',
            'jp_count' => 1,
        ]);
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'substitute_teacher_id' => $ctx['teacherB']->id,
            'date' => '2026-07-16',
            'session_hour' => '2',
            'material' => 'Pengganti Bab 2',
            'jp_count' => 1,
        ]);

        $response = $this->actingAs($ctx['admin'])
            ->get(route('admin.diniyyah-journals.export', ['format' => 'excel', 'tipe' => 'substitute']));

        $response->assertOk();
        $response->assertSee('Pengganti Bab 2');
        $response->assertDontSee('Reguler Bab 1');
    }

    public function test_date_range_filter_limits_rows(): void
    {
        $ctx = $this->makeContext();
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'substitute_teacher_id' => null,
            'date' => '2026-07-01',
            'session_hour' => '1',
            'material' => 'Awal Bulan',
            'jp_count' => 1,
        ]);
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'substitute_teacher_id' => null,
            'date' => '2026-07-20',
            'session_hour' => '1',
            'material' => 'Akhir Bulan',
            'jp_count' => 1,
        ]);

        $response = $this->actingAs($ctx['admin'])
            ->get(route('admin.diniyyah-journals.export', [
                'format' => 'excel',
                'date_from' => '2026-07-15',
                'date_until' => '2026-07-31',
            ]));

        $response->assertOk();
        $response->assertSee('Akhir Bulan');
        $response->assertDontSee('Awal Bulan');
    }

    private function makeContext(): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['name' => 'Admin']);
        $admin->assignRole('admin');

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
            'admin' => $admin,
            'userA' => $userA,
            'teacherA' => $teacherA,
            'userB' => $userB,
            'teacherB' => $teacherB,
            'term' => $term,
            'classroomTerm' => $classroomTerm,
            'assignmentA' => $assignmentA,
        ];
    }
}
