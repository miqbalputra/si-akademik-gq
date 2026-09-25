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
            ->assertSee('Unduh JPG')
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
        $renderer->shouldReceive('render')->once()->with(Mockery::type('array'), 'png')->andReturn($png);
        $this->app->instance(JournalReminderImageRenderer::class, $renderer);

        $response = $this->actingAs($ctx['admin'])->get(route('admin.journal-reminders.export', ['format' => 'png'] + $query));
        $response->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringEndsWith('.png"', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $response->getContent());
    }

    public function test_teacher_can_be_exported_as_a_single_jpg_or_png_from_the_same_export_link(): void
    {
        $ctx = $this->context();
        $query = [
            'academic_term_id' => $ctx['term']->id,
            'date_from' => '2026-08-05',
            'date_until' => '2026-08-05',
            'teacher_id' => $ctx['teacher']->id,
        ];
        $jpg = "\xff\xd8\xff\xe0\x00\x10JFIF\x00\x01\x01\x00";
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIW2Nk+M/wHwAF/gL+gV/7AAAAAElFTkSuQmCC');
        $renderer = Mockery::mock(JournalReminderImageRenderer::class);
        $renderer->shouldReceive('render')
            ->once()
            ->with(Mockery::on(fn (array $report): bool => $report['teachers']->count() === 1
                && $report['teachers']->first()['teacher_id'] === $ctx['teacher']->id
                && $report['stats']['total_missing'] === 2), 'jpg')
            ->andReturn($jpg);
        $renderer->shouldReceive('render')
            ->once()
            ->with(Mockery::on(fn (array $report): bool => $report['teachers']->count() === 1
                && $report['teachers']->first()['teacher_id'] === $ctx['teacher']->id
                && $report['stats']['total_missing'] === 2), 'png')
            ->andReturn($png);
        $this->app->instance(JournalReminderImageRenderer::class, $renderer);

        $jpgResponse = $this->actingAs($ctx['admin'])->get(route('admin.journal-reminders.export', ['format' => 'jpg'] + $query));
        $pngResponse = $this->actingAs($ctx['admin'])->get(route('admin.journal-reminders.export', ['format' => 'png'] + $query));

        $jpgResponse->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringEndsWith('.jpg"', $jpgResponse->headers->get('Content-Disposition'));
        $this->assertStringStartsWith("\xff\xd8\xff", $jpgResponse->getContent());
        $pngResponse->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringEndsWith('.png"', $pngResponse->headers->get('Content-Disposition'));
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $pngResponse->getContent());
    }

    public function test_image_layout_keeps_all_four_columns_and_wraps_long_cell_values(): void
    {
        $longSubject = str_repeat('MapelDenganNamaPanjang', 10);
        $report = [
            'term' => (object) ['academicYear' => (object) ['name' => '2026/2027'], 'name' => 'Ganjil'],
            'start' => Carbon::parse('2026-08-05', 'Asia/Jakarta'),
            'end' => Carbon::parse('2026-08-05', 'Asia/Jakarta'),
            'generated_at' => Carbon::parse('2026-08-05 09:00:00', 'Asia/Jakarta'),
            'teachers' => collect([[
                'teacher_name' => 'Ustadz Ahmad',
                'niy' => 'G-001',
                'missing_count' => 1,
                'rows' => collect([[
                    'date_label' => 'Rabu, 05 Agustus 2026',
                    'session' => 'Tafsir serentak tingkat lanjutan',
                    'session_time' => '08:00 - 09:00',
                    'classes' => ['Kelas Tafsir A dan B', 'Kelas Ulumul Hadits Lanjutan'],
                    'subjects' => [$longSubject],
                ]]),
            ]]),
            'stats' => [
                'teachers_to_remind' => 1,
                'total_missing' => 1,
                'attendance_unverified_teachers' => 0,
            ],
        ];

        $renderer = new JournalReminderImageRenderer;
        $layoutMethod = new \ReflectionMethod($renderer, 'buildLayout');
        $layoutMethod->setAccessible(true);
        $layout = $layoutMethod->invoke($renderer, $report, [
            'regular' => resource_path('fonts/NotoSans-Regular.ttf'),
            'bold' => resource_path('fonts/NotoSans-Bold.ttf'),
        ]);
        $columns = (new \ReflectionClass($renderer))->getConstant('COLUMNS');
        $rowBlock = collect($layout['blocks'])->firstWhere('kind', 'table_row');

        $this->assertSame(['Tanggal', 'Sesi / Jam', 'Kelas', 'Mapel'], array_column($columns, 'label'));
        $this->assertNotNull($rowBlock);
        $this->assertGreaterThan(1, count($rowBlock['cells']['classes']['lines']));
        $this->assertGreaterThan(1, count($rowBlock['cells']['subjects']['lines']));
        $this->assertSame(
            'Kelas Tafsir A dan B, Kelas Ulumul Hadits Lanjutan',
            preg_replace('/\s+/u', ' ', trim(implode(' ', $rowBlock['cells']['classes']['lines']))),
        );
        $this->assertSame($longSubject, implode('', $rowBlock['cells']['subjects']['lines']));
        $this->assertSame(
            'Rabu, 05 Agustus 2026',
            preg_replace('/\s+/u', ' ', implode(' ', $rowBlock['cells']['date']['lines'])),
        );
        $this->assertSame(
            'Tafsir serentak tingkat lanjutan',
            preg_replace('/\s+/u', ' ', implode(' ', $rowBlock['cells']['session']['main'])),
        );
        $this->assertSame(['08:00 - 09:00'], $rowBlock['cells']['session']['time']);
        $this->assertGreaterThan(80, $rowBlock['height']);
    }

    public function test_image_layout_keeps_the_empty_report_message(): void
    {
        $report = [
            'term' => (object) ['academicYear' => (object) ['name' => '2026/2027'], 'name' => 'Ganjil'],
            'start' => Carbon::parse('2026-08-05', 'Asia/Jakarta'),
            'end' => Carbon::parse('2026-08-05', 'Asia/Jakarta'),
            'generated_at' => Carbon::parse('2026-08-05 09:00:00', 'Asia/Jakarta'),
            'teachers' => collect(),
            'stats' => [
                'teachers_to_remind' => 0,
                'total_missing' => 0,
                'attendance_unverified_teachers' => 0,
            ],
        ];
        $renderer = new JournalReminderImageRenderer;
        $layoutMethod = new \ReflectionMethod($renderer, 'buildLayout');
        $layoutMethod->setAccessible(true);
        $layout = $layoutMethod->invoke($renderer, $report, [
            'regular' => resource_path('fonts/NotoSans-Regular.ttf'),
            'bold' => resource_path('fonts/NotoSans-Bold.ttf'),
        ]);
        $emptyBlock = collect($layout['blocks'])->first(fn (array $block): bool => $block['kind'] === 'text' && in_array('Semua jurnal pada rentang ini sudah lengkap.', $block['lines'], true)
        );

        $this->assertNotNull($emptyBlock);
        $this->assertSame([], collect($layout['blocks'])->whereIn('kind', ['teacher', 'table_header', 'table_row'])->all());
        $this->assertGreaterThanOrEqual(900, $layout['height']);
    }

    public function test_image_renderer_produces_png_and_jpeg_with_bundled_font_when_gd_is_available(): void
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagettftext') || ! function_exists('imagettfbbox')) {
            $this->markTestSkipped('PHP GD with FreeType is not installed in this environment.');
        }

        $report = [
            'term' => (object) ['academicYear' => (object) ['name' => '2026/2027'], 'name' => 'Ganjil'],
            'start' => Carbon::parse('2026-08-05', 'Asia/Jakarta'),
            'end' => Carbon::parse('2026-08-05', 'Asia/Jakarta'),
            'generated_at' => Carbon::parse('2026-08-05 09:00:00', 'Asia/Jakarta'),
            'teachers' => collect([[
                'teacher_name' => 'Ustadz Ahmad',
                'niy' => 'G-001',
                'missing_count' => 1,
                'rows' => collect([[
                    'date_label' => 'Rabu, 05 Agustus 2026',
                    'session' => 'Jam 1',
                    'session_time' => '08:00 - 09:00',
                    'classes' => ['Kelas Fiqih'],
                    'subjects' => ['Fiqih'],
                ]]),
            ]]),
            'stats' => [
                'teachers_to_remind' => 1,
                'total_missing' => 1,
                'attendance_unverified_teachers' => 0,
            ],
        ];

        $renderer = new JournalReminderImageRenderer;
        foreach (['png' => 'image/png', 'jpg' => 'image/jpeg'] as $format => $expectedMime) {
            $content = $renderer->render($report, $format);
            $image = getimagesizefromstring($content);

            $this->assertIsArray($image);
            $this->assertSame($expectedMime, $image['mime']);
            $this->assertSame(1440, $image[0]);
            $this->assertGreaterThanOrEqual(900, $image[1]);
        }
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
        $renderer->shouldReceive('render')->once()->with(Mockery::type('array'), 'png')->andThrow(new RuntimeException('Unduhan PNG/JPG memerlukan ekstensi GD.'));
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
