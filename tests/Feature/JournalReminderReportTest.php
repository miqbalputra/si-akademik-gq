<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\ClassSession;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\School;
use App\Models\SchoolHoliday;
use App\Models\Teacher;
use App\Models\User;
use App\Services\JournalReminderImageRenderer;
use App\Services\JournalReminderReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JournalReminderReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'kabag_diniyyah', 'guru', 'kepala_sekolah'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_report_groups_missing_slots_per_teacher_and_deduplicates_simultaneous_tafsir(): void
    {
        $ctx = $this->context();

        $report = app(JournalReminderReportService::class)->build($ctx['term']->id, '2026-08-05', '2026-08-05');

        $this->assertSame(1, $report['stats']['teachers_to_remind']);
        $this->assertSame(2, $report['stats']['total_missing']);
        $this->assertCount(1, $report['teachers']);
        $this->assertSame($ctx['teacher']->id, $report['teachers']->first()['teacher_id']);
        $this->assertSame(2, $report['teachers']->first()['missing_count']);
        $this->assertSame(['Jam 1', 'Tafsir serentak'], $report['teachers']->first()['rows']->pluck('session')->all());
    }

    public function test_holiday_slots_are_not_put_in_the_reminder(): void
    {
        $ctx = $this->context();
        SchoolHoliday::create([
            'school_id' => $ctx['school']->id,
            'academic_term_id' => $ctx['term']->id,
            'holiday_date' => '2026-08-05',
            'title' => 'Libur Nasional',
        ]);
        $this->assertDatabaseHas('school_holidays', ['academic_term_id' => $ctx['term']->id, 'holiday_date' => '2026-08-05 00:00:00']);

        $report = app(JournalReminderReportService::class)->build($ctx['term']->id, '2026-08-05', '2026-08-05');

        $this->assertSame(0, $report['stats']['total_missing']);
        $this->assertTrue($report['teachers']->isEmpty());
    }

    public function test_admin_and_kabag_can_open_report_and_others_are_forbidden(): void
    {
        $ctx = $this->context();
        $query = ['academic_term_id' => $ctx['term']->id, 'date_from' => '2026-08-05', 'date_until' => '2026-08-05'];

        $this->actingAs($ctx['admin'])->get(route('admin.journal-reminders.index', $query))
            ->assertOk()
            ->assertSee('Pengingat Pengisian Jurnal KBM')
            ->assertSee($ctx['teacher']->name)
            ->assertSee('2 jurnal kosong');
        $this->actingAs($ctx['kabag'])->get(route('admin.journal-reminders.index', $query))->assertOk();
        $this->actingAs($ctx['guruUser'])->get(route('admin.journal-reminders.index', $query))->assertForbidden();
        $this->actingAs($ctx['headmaster'])->get(route('admin.journal-reminders.index', $query))->assertForbidden();
    }

    public function test_downloads_are_pdf_and_png_and_date_range_is_bounded_to_the_term(): void
    {
        $ctx = $this->context();
        $query = ['academic_term_id' => $ctx['term']->id, 'date_from' => '2026-07-20', 'date_until' => '2026-08-05'];

        $this->actingAs($ctx['admin'])->get(route('admin.journal-reminders.index', $query))
            ->assertOk()
            ->assertSee('value="2026-08-01"', false);
        $this->actingAs($ctx['admin'])->get(route('admin.journal-reminders.export', ['format' => 'pdf'] + $query))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIW2Nk+M/wHwAF/gL+gV/7AAAAAElFTkSuQmCC');
        $renderer = Mockery::mock(JournalReminderImageRenderer::class);
        $renderer->shouldReceive('render')->once()->andReturn($png);
        $this->app->instance(JournalReminderImageRenderer::class, $renderer);

        $response = $this->actingAs($ctx['admin'])->get(route('admin.journal-reminders.export', ['format' => 'png'] + $query));
        $response->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $response->getContent());
    }

    public function test_default_range_is_current_month_to_today_and_future_dates_are_excluded(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 9, 0, 0, 'Asia/Jakarta'));

        try {
            $ctx = $this->context();

            $this->actingAs($ctx['admin'])->get(route('admin.journal-reminders.index', ['academic_term_id' => $ctx['term']->id]))
                ->assertOk()
                ->assertSee('value="2026-08-01"', false)
                ->assertSee('value="2026-08-10"', false);
            $this->actingAs($ctx['admin'])->get(route('admin.journal-reminders.index', [
                'academic_term_id' => $ctx['term']->id,
                'date_from' => '2026-08-09',
                'date_until' => '2026-12-01',
            ]))->assertOk()
                ->assertSee('value="2026-08-10"', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_png_renderer_failure_returns_a_clear_message_instead_of_a_server_error(): void
    {
        $ctx = $this->context();
        $query = ['academic_term_id' => $ctx['term']->id, 'date_from' => '2026-08-05', 'date_until' => '2026-08-05'];
        $renderer = Mockery::mock(JournalReminderImageRenderer::class);
        $renderer->shouldReceive('render')->once()->andThrow(new RuntimeException('Unduhan PNG memerlukan ekstensi GD.'));
        $this->app->instance(JournalReminderImageRenderer::class, $renderer);

        $this->from(route('admin.journal-reminders.index', $query))
            ->actingAs($ctx['admin'])
            ->get(route('admin.journal-reminders.export', ['format' => 'png'] + $query))
            ->assertRedirect(route('admin.journal-reminders.index', $query))
            ->assertSessionHasErrors('export');
    }

    /** @return array<string, mixed> */
    private function context(): array
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $kabag = User::factory()->create();
        $kabag->assignRole('kabag_diniyyah');
        $guruUser = User::factory()->create();
        $guruUser->assignRole('guru');
        $headmaster = User::factory()->create();
        $headmaster->assignRole('kepala_sekolah');
        $teacher = Teacher::create(['user_id' => $guruUser->id, 'name' => 'Ustadz Ahmad', 'niy' => 'G-001', 'status' => 'active']);
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil', 'starts_at' => '2026-08-01', 'ends_at' => '2026-12-31', 'is_active' => true]);
        $session = ClassSession::create(['session_name' => '1', 'starts_at' => '08:00', 'ends_at' => '09:00', 'is_break' => false]);

        $regular = $this->assignment($term, $teacher, 'Kelas Fiqih', 'Fiqih', 'fiqih');
        $tafsirOne = $this->assignment($term, $teacher, 'Kelas Tafsir A', 'Tafsir', 'tafsir');
        $tafsirTwo = $this->assignment($term, $teacher, 'Kelas Tafsir B', 'Tafsir Lanjutan', 'tafsir-lanjutan');
        foreach ([$regular, $tafsirOne, $tafsirTwo] as $assignment) {
            DiniyyahTeachingSchedule::create(['diniyyah_teacher_assignment_id' => $assignment->id, 'class_session_id' => $session->id, 'day_of_week' => 3]);
        }

        return compact('admin', 'kabag', 'guruUser', 'headmaster', 'teacher', 'school', 'term');
    }

    private function assignment(AcademicTerm $term, Teacher $teacher, string $className, string $subjectName, string $subjectCode): DiniyyahTeacherAssignment
    {
        $classroom = Classroom::create(['name' => $className]);
        $classroomTerm = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => $className]);
        $subject = DiniyyahSubject::firstOrCreate(['code' => $subjectCode], ['name' => $subjectName, 'default_assessment_method' => 'weighted']);
        $classSubject = DiniyyahClassSubject::create(['classroom_term_id' => $classroomTerm->id, 'subject_id' => $subject->id, 'assessment_method' => 'weighted', 'kkm' => 70, 'daily_weight' => 40, 'exam_weight' => 60]);

        return DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $classSubject->id, 'teacher_id' => $teacher->id, 'assignment_role' => 'primary']);
    }
}
