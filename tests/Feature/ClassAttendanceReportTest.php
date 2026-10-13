<?php

namespace Tests\Feature;

use App\Filament\Widgets\ClassAttendanceReportWidget;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\HomeroomAssignment;
use App\Models\School;
use App\Models\SchoolEvent;
use App\Models\SchoolHoliday;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentDeparture;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ClassAttendanceReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClassAttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-13 03:00:00', 'UTC'));
    }

    public function test_monthly_report_uses_wib_calendar_and_distinguishes_assumed_presence(): void
    {
        $context = $this->context();
        $report = $this->build($context);
        $class = $report['classes'][0];
        $student = $class['students'][0];
        $this->assertSame(21, $class['months'][0]['full_effective_days']);
        $this->assertSame(21, $class['effective_days']);
        $this->assertSame(8, $class['elapsed_days']);
        $this->assertSame(['H' => 4, 'S' => 1, 'I' => 1, 'A' => 1, 'L' => 1], $student['counts']);
        $this->assertSame(3, $student['assumed_present']);
        $this->assertSame(7, $student['eligible_days']);
        $this->assertSame('57,1%', $student['percentage_label']);
        $this->assertSame('H*', $student['daily']['2026-10-13']);
        $this->assertSame('—', $student['daily']['2026-10-14']);
        $this->assertSame('L', $student['daily']['2026-10-05']);
        $this->assertSame('L', $student['daily']['2026-10-10']);
        $this->assertSame('H*', $student['daily']['2026-10-09']);
        $this->assertSame(5, StudentAttendance::count());
    }

    public function test_one_date_and_cross_month_range_keep_full_month_effective_counts(): void
    {
        $context = $this->context();
        $single = $this->build($context, ['period_mode' => 'range', 'start_date' => '2026-10-08', 'end_date' => '2026-10-08']);
        $this->assertSame(21, $single['classes'][0]['months'][0]['full_effective_days']);
        $this->assertSame(1, $single['classes'][0]['effective_days']);
        $this->assertSame('100,0%', $single['classes'][0]['students'][0]['percentage_label']);
        $this->assertSame(0, $single['classes'][0]['students'][0]['assumed_present']);
        $this->assertSame('—', $single['classes'][0]['students'][0]['daily']['2026-10-07']);
        $range = $this->build($context, ['period_mode' => 'range', 'start_date' => '2026-10-30', 'end_date' => '2026-11-03']);
        $this->assertCount(2, $range['classes'][0]['months']);
        $this->assertSame(3, $range['classes'][0]['effective_days']);
        $this->assertSame(0, $range['classes'][0]['elapsed_days']);
        $this->assertNull($range['classes'][0]['students'][0]['percentage']);
    }

    public function test_semester_boundaries_clip_monthly_calendar(): void
    {
        $context = $this->context();
        $context['term']->update(['starts_at' => '2026-10-08']);
        $report = $this->build($context);
        $this->assertSame('2026-10-08', $report['period']['start']);
        $this->assertSame(17, $report['classes'][0]['effective_days']);
        $this->assertSame(4, $report['classes'][0]['elapsed_days']);
        $this->assertSame('—', $report['classes'][0]['students'][0]['daily']['2026-10-01']);
    }

    public function test_single_day_includes_saved_status_and_month_end_holiday_is_excluded(): void
    {
        $context = $this->context();
        $single = $this->build($context, ['period_mode' => 'range', 'start_date' => '2026-10-01', 'end_date' => '2026-10-01']);
        $this->assertSame(1, $single['classes'][0]['students'][0]['counts']['S']);
        $this->assertSame('0,0%', $single['classes'][0]['students'][0]['percentage_label']);
        SchoolHoliday::create(['school_id' => $context['school']->id, 'academic_term_id' => $context['term']->id, 'holiday_date' => '2026-11-30', 'title' => 'Libur akhir bulan']);
        $month = $this->build($context, ['month' => '2026-11']);
        $this->assertSame(20, $month['classes'][0]['months'][0]['full_effective_days']);
    }

    public function test_archived_student_is_kept_without_assumed_presence_after_departure(): void
    {
        $context = $this->context();
        $archived = Student::create(['name' => 'Santri Arsip', 'nis' => '00002', 'gender' => 'male', 'status' => 'inactive']);
        $enrollment = ClassEnrollment::create(['academic_term_id' => $context['term']->id, 'classroom_term_id' => $context['class']->id, 'student_id' => $archived->id, 'roll_number' => 2, 'status' => 'inactive']);
        StudentDeparture::create(['student_id' => $archived->id, 'type' => 'transfer', 'effective_date' => '2026-10-09', 'reason' => 'Pindah', 'destination_school' => 'Sekolah lain', 'exited_by' => $context['admin']->id]);
        $archived->delete();
        $this->attendance($context, $enrollment, '2026-10-12', 'present');
        $report = $this->build($context);
        $row = $report['classes'][0]['students'][1];
        $this->assertSame('Santri Arsip', $row['name']);
        $this->assertTrue($row['archived']);
        $this->assertSame('2026-10-09', $row['departure_date']);
        $this->assertSame('—', $row['daily']['2026-10-09']);
        $this->assertSame('H', $row['daily']['2026-10-12']);
        $this->assertSame('—', $row['daily']['2026-10-13']);
        $this->assertSame(5, $row['assumed_present']);
        $this->assertSame(6, $row['eligible_days']);
        $single = $this->build($context, ['period_mode' => 'range', 'start_date' => '2026-10-13', 'end_date' => '2026-10-13']);
        $this->assertSame('—', $single['classes'][0]['students'][1]['percentage_label']);
    }

    public function test_admin_can_preview_and_export_all_classes_with_unique_sheet_names(): void
    {
        $context = $this->context();
        $second = ClassroomTerm::create(['academic_term_id' => $context['term']->id, 'classroom_id' => Classroom::create(['school_id' => $context['school']->id, 'name' => 'Kelas B', 'level_name' => 'M3', 'gender_group' => 'ikhwan'])->id, 'name' => $context['class']->name, 'status' => 'active']);
        $student = Student::create(['name' => '=Nama santri sebagai teks', 'nis' => '00003', 'gender' => 'male', 'status' => 'active']);
        ClassEnrollment::create(['academic_term_id' => $context['term']->id, 'classroom_term_id' => $second->id, 'student_id' => $student->id, 'status' => 'active']);
        $filters = $this->filters($context, ['classroom_term_id' => 'all']);
        $this->actingAs($context['admin'], 'admin')->getJson(route('admin.class-attendance-report.preview', $filters))
            ->assertOk()->assertJsonCount(2, 'classes')->assertJsonPath('classes.0.effective_days', 21);
        $response = $this->get(route('admin.class-attendance-report.export', array_merge(['format' => 'xlsx'], $filters)))->assertOk();
        $book = $this->workbook($response->getContent());
        try {
            $this->assertSame(4, $book->getSheetCount());
            $this->assertCount(4, array_unique($book->getSheetNames()));
            $this->assertSame('00001', $book->getSheet(0)->getCell('B13')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $book->getSheet(0)->getCell('B13')->getDataType());
            $this->assertSame(4, $book->getSheet(0)->getCell('G13')->getValue());
            $this->assertSame(3, $book->getSheet(0)->getCell('L13')->getValue());
            $this->assertEqualsWithDelta(4 / 7, $book->getSheet(0)->getCell('M13')->getValue(), 0.000001);
            $this->assertSame('0.0%', $book->getSheet(0)->getStyle('M13')->getNumberFormat()->getFormatCode());
            $this->assertSame('D13', $book->getSheet(0)->getFreezePane());
            $this->assertSame('E13', $book->getSheet(1)->getFreezePane());
            $this->assertSame('00001', $book->getSheet(1)->getCell('C13')->getValue());
            $this->assertSame('H*', $book->getSheet(1)->getCell('Q13')->getValue());
            $this->assertSame('=Nama santri sebagai teks', $book->getSheet(2)->getCell('C13')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $book->getSheet(2)->getCell('C13')->getDataType());
        } finally {
            $book->disconnectWorksheets();
        }
        $pdf = $this->get(route('admin.class-attendance-report.export', array_merge($filters, ['format' => 'pdf', 'period_mode' => 'range', 'start_date' => '2026-10-08', 'end_date' => '2026-10-08'])))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertGreaterThanOrEqual(4, preg_match_all('/\/Type\s*\/Page\b/', $pdf->getContent()));
    }

    public function test_homeroom_preview_and_excel_download_use_own_class(): void
    {
        $context = $this->context();
        $filters = $this->filters($context);
        $this->actingAs($context['guru'])->getJson(route('class-attendance-report.preview', $filters))
            ->assertOk()->assertJsonPath('classes.0.students', 1)->assertJsonPath('classes.0.elapsed_days', 8);
        $this->get(route('class-attendance-report.export', array_merge(['format' => 'xlsx'], $filters)))
            ->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $page = $this->get(route('attendance.edit', ['classroomTerm' => $context['class'], 'month' => '2026-10']))
            ->assertOk()->assertSee('Unduh Laporan')->assertSee('Download Excel')->assertSee('Download PDF');
        $this->assertSame(1, substr_count($page->getContent(), '</main>'));
        $page->assertDontSee('} text-theme-sm font-normal"', false);
    }

    public function test_other_class_all_classes_expired_assignments_and_other_roles_are_forbidden(): void
    {
        $context = $this->context();
        $this->actingAs($context['guru'])->getJson(route('class-attendance-report.preview', $this->filters($context, ['classroom_term_id' => 'all'])))->assertForbidden();
        $other = $this->user('guru');
        Teacher::create(['user_id' => $other->id, 'name' => 'Guru lain', 'gender' => 'male', 'status' => 'active']);
        $this->actingAs($other)->getJson(route('class-attendance-report.preview', $this->filters($context)))->assertForbidden();
        HomeroomAssignment::where('teacher_id', $context['teacher']->id)->update(['ends_at' => '2026-10-12']);
        $this->actingAs($context['guru'])->getJson(route('class-attendance-report.preview', $this->filters($context)))->assertForbidden();
        foreach (['wali_santri', 'kepala_sekolah', 'kabag_diniyyah', 'kabag_tahfidz'] as $role) {
            $this->actingAs($this->user($role), 'admin')->getJson(route('admin.class-attendance-report.preview', $this->filters($context)))->assertForbidden();
        }
    }

    public function test_guards_are_separate_and_guest_cannot_download(): void
    {
        $context = $this->context();
        $this->getJson(route('class-attendance-report.preview', $this->filters($context)))->assertUnauthorized();
        $this->actingAs($context['admin'], 'web')->getJson(route('admin.class-attendance-report.preview', $this->filters($context)))->assertUnauthorized();
        $this->actingAs($context['guru'], 'admin')->getJson(route('admin.class-attendance-report.preview', $this->filters($context)))->assertForbidden();
    }

    public function test_invalid_period_class_semester_and_format_are_rejected(): void
    {
        $context = $this->context();
        $this->actingAs($context['guru']);
        foreach ([
            ['month' => '2027-01'], ['month' => '2026-99'],
            ['period_mode' => 'range', 'start_date' => '2026-09-30', 'end_date' => '2026-10-05'],
            ['period_mode' => 'range', 'start_date' => '2026-10-13', 'end_date' => '2026-10-12'],
            ['academic_term_id' => $context['term']->id + 999],
        ] as $overrides) {
            $this->getJson(route('class-attendance-report.preview', $this->filters($context, $overrides)))->assertUnprocessable();
        }
        $this->get(route('class-attendance-report.export', array_merge($this->filters($context), ['format' => 'csv'])))->assertNotFound();
    }

    public function test_weekends_future_and_zero_days_have_no_percentage(): void
    {
        $context = $this->context();
        $future = $this->build($context, ['month' => '2026-11']);
        $this->assertSame('—', $future['classes'][0]['students'][0]['percentage_label']);
        $weekend = $this->build($context, ['period_mode' => 'range', 'start_date' => '2026-10-10', 'end_date' => '2026-10-11']);
        $this->assertSame(0, $weekend['classes'][0]['effective_days']);
        $this->assertSame(0, $weekend['classes'][0]['students'][0]['eligible_days']);
        $this->assertNull($weekend['classes'][0]['students'][0]['percentage']);
        $specialHoliday = $this->build($context, ['period_mode' => 'range', 'start_date' => '2026-10-07', 'end_date' => '2026-10-07']);
        $this->assertSame(1, $specialHoliday['classes'][0]['students'][0]['counts']['L']);
        $this->assertSame(0, $specialHoliday['classes'][0]['students'][0]['eligible_days']);
        $this->assertSame('—', $specialHoliday['classes'][0]['students'][0]['percentage_label']);
    }

    public function test_homeroom_dates_and_elapsed_days_use_today_in_wib(): void
    {
        $context = $this->context();
        $this->travelTo(CarbonImmutable::parse('2026-10-12 18:00:00', 'UTC'));
        HomeroomAssignment::where('teacher_id', $context['teacher']->id)->firstOrFail()->update(['starts_at' => '2026-10-13', 'ends_at' => '2026-10-13']);
        $this->assertTrue(app(ClassAttendanceReportService::class)->canExportClass($context['guru'], $context['class']));
        $report = $this->build($context, ['period_mode' => 'range', 'start_date' => '2026-10-13', 'end_date' => '2026-10-13']);
        $this->assertSame('2026-10-13', $report['period']['as_of']);
        $this->assertSame(1, $report['classes'][0]['elapsed_days']);
        $this->assertSame('H*', $report['classes'][0]['students'][0]['daily']['2026-10-13']);
    }

    public function test_unassigned_class_is_forbidden_even_when_the_teacher_has_another_class(): void
    {
        $context = $this->context();
        $otherClass = ClassroomTerm::create(['academic_term_id' => $context['term']->id, 'classroom_id' => Classroom::create(['school_id' => $context['school']->id, 'name' => 'Kelas lain', 'level_name' => 'M3', 'gender_group' => 'ikhwan'])->id, 'name' => 'Kelas lain', 'status' => 'active']);
        $this->actingAs($context['guru'])->getJson(route('class-attendance-report.preview', $this->filters($context, ['classroom_term_id' => (string) $otherClass->id])))->assertForbidden();
        $this->get(route('class-attendance-report.export', array_merge($this->filters($context, ['classroom_term_id' => (string) $otherClass->id]), ['format' => 'pdf'])))->assertForbidden();
    }

    public function test_preview_and_download_do_not_write_attendance_journals_or_business_tables(): void
    {
        $context = $this->context();
        $before = DB::table('student_attendances')->orderBy('id')->get()->toJson();
        $writes = [];
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $this->actingAs($context['guru'])->getJson(route('class-attendance-report.preview', $this->filters($context)))->assertOk();
        $this->get(route('class-attendance-report.export', array_merge($this->filters($context), ['format' => 'xlsx'])))->assertOk();
        $this->get(route('class-attendance-report.export', array_merge($this->filters($context, ['period_mode' => 'range', 'start_date' => '2026-10-08', 'end_date' => '2026-10-08']), ['format' => 'pdf'])))->assertOk();
        $this->assertSame([], $writes);
        $this->assertSame($before, DB::table('student_attendances')->orderBy('id')->get()->toJson());
    }

    public function test_pdf_renders_multiple_pages_and_widget_is_available_only_to_admin(): void
    {
        $context = $this->context();
        for ($i = 2; $i <= 45; $i++) {
            $student = Student::create(['name' => 'Santri dengan Nama Panjang untuk Pemeriksaan Tabel Laporan '.$i, 'nis' => str_pad((string) $i, 8, '0', STR_PAD_LEFT), 'gender' => 'male', 'status' => 'active']);
            ClassEnrollment::create(['academic_term_id' => $context['term']->id, 'classroom_term_id' => $context['class']->id, 'student_id' => $student->id, 'roll_number' => $i, 'status' => 'active']);
        }
        $this->actingAs($context['admin'], 'admin');
        $this->assertTrue(ClassAttendanceReportWidget::canView());
        $this->get('/admin')->assertOk()->assertSee('Laporan Presensi Kelas')->assertSee('Download Excel');
        $this->get('/admin/attendance-shortcut')->assertOk()->assertSee('Laporan Presensi Kelas');
        $response = $this->get(route('admin.class-attendance-report.export', array_merge($this->filters($context), ['format' => 'pdf'])))->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertGreaterThanOrEqual(4, preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()));
        $this->actingAs($this->user('kepala_sekolah'), 'admin');
        $this->assertFalse(ClassAttendanceReportWidget::canView());
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

        return $user;
    }

    private function context(): array
    {
        $admin = $this->user('admin');
        $guru = $this->user('guru');
        $teacher = Teacher::create(['user_id' => $guru->id, 'name' => 'Wali Kelas', 'gender' => 'male', 'status' => 'active']);
        $school = School::create(['name' => 'Griya Qur’an Tunas Ilmu', 'code' => 'GQ']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027', 'is_active' => true]);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil', 'starts_at' => '2026-10-01', 'ends_at' => '2026-12-31', 'is_active' => true]);
        $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'M3 Ikhwan', 'level_name' => 'M3', 'gender_group' => 'ikhwan']);
        $class = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => 'M3 Ikhwan dengan Nama Kelas Panjang', 'status' => 'active']);
        HomeroomAssignment::create(['classroom_term_id' => $class->id, 'teacher_id' => $teacher->id]);
        $student = Student::create(['name' => 'Ahmad dengan Nama Panjang untuk Laporan Kehadiran', 'nis' => '00001', 'gender' => 'male', 'status' => 'active']);
        $enrollment = ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'student_id' => $student->id, 'roll_number' => 1, 'status' => 'active']);
        SchoolHoliday::create(['school_id' => $school->id, 'academic_term_id' => $term->id, 'holiday_date' => '2026-10-05', 'title' => 'Libur Sekolah']);
        SchoolEvent::create(['school_id' => $school->id, 'academic_term_id' => $term->id, 'title' => 'Agenda Tanpa KBM', 'event_type' => 'other', 'is_no_kbm' => true, 'target_scope' => 'all', 'starts_on' => '2026-10-09', 'ends_on' => '2026-10-09']);
        $context = compact('admin', 'guru', 'teacher', 'school', 'term', 'class', 'student', 'enrollment');
        foreach (['2026-10-01' => 'sick', '2026-10-02' => 'permission', '2026-10-06' => 'absent', '2026-10-07' => 'holiday', '2026-10-08' => 'present'] as $date => $status) {
            $this->attendance($context, $enrollment, $date, $status);
        }

        return $context;
    }

    private function attendance(array $context, ClassEnrollment $enrollment, string $date, string $status): void
    {
        StudentAttendance::create(['academic_term_id' => $context['term']->id, 'classroom_term_id' => $context['class']->id, 'class_enrollment_id' => $enrollment->id, 'student_id' => $enrollment->student_id, 'attendance_date' => $date, 'status' => $status, 'input_by' => $context['guru']->id]);
    }

    private function filters(array $context, array $overrides = []): array
    {
        return array_merge(['academic_term_id' => $context['term']->id, 'classroom_term_id' => (string) $context['class']->id, 'period_mode' => 'month', 'month' => '2026-10'], $overrides);
    }

    private function build(array $context, array $overrides = []): array
    {
        return app(ClassAttendanceReportService::class)->build($context['guru'], $this->filters($context, $overrides));
    }

    private function workbook(string $content): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'class-attendance-test-');
        try {
            file_put_contents($path, $content);

            return IOFactory::load($path);
        } finally {
            unlink($path);
        }
    }
}
