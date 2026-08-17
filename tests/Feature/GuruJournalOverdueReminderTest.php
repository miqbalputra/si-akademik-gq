<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\ClassSession;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\PanelUserPreference;
use App\Models\School;
use App\Models\SchoolHoliday;
use App\Models\Teacher;
use App\Models\User;
use App\Services\GuruJournalReminderPreferenceService;
use App\Services\GuruPerformaService;
use App\Support\SessionTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruJournalOverdueReminderTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-08-10';

    private const OVERDUE_DATE = '2026-08-04';

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_active_term_reminder_includes_previous_month_and_excludes_other_terms(): void
    {
        $this->setNow();
        $context = $this->makeRegularContext('2026-07-28');

        // Selasa 28 Juli dan 4 Agustus sama-sama punya jadwal. Isi slot Agustus
        // sehingga bukti tunggakan lintas bulan yang tersisa adalah Juli.
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $context['assignment']->id,
            'date' => self::OVERDUE_DATE,
            'session_hour' => '1',
            'material' => 'Materi sudah dicatat',
            'jp_count' => 1,
        ]);

        $this->makeRegularAssignment($context['teacher'], $this->makeTerm('Genap', false, '2026-07-01'), 'Kelas Lama');

        $reminder = app(GuruPerformaService::class)->overdueForActiveTerm($context['teacher']);

        $this->assertNotNull($reminder);
        $this->assertSame(1, $reminder['count']);
        $this->assertSame(1, $reminder['class_count']);
        $this->assertSame('2026-07-28', $reminder['empty_slots'][0]['date']);
        $this->assertSame('Mustawa 2 Ikhwan', $reminder['empty_slots'][0]['classroom_names']);
        $this->assertStringContainsString('assignment_id='.$context['assignment']->id, $reminder['empty_slots'][0]['fill_url']);
        $this->assertStringContainsString('session_hour=1', $reminder['empty_slots'][0]['fill_url']);
    }

    public function test_dashboard_renders_required_reminder_and_direct_form_bypasses_it(): void
    {
        $this->setNow();
        $context = $this->makeRegularContext('2026-08-01');

        $dashboard = $this->actingAs($context['user'])->get(route('guru.dashboard'));

        $dashboard->assertOk()
            ->assertSee('Masih ada 1 jurnal kosong')
            ->assertSee('Tutup sementara 3 jam')
            ->assertSee('Mustawa 2 Ikhwan')
            ->assertSee('data-journal-overdue-reminder', false)
            ->assertSee('assignment_id='.$context['assignment']->id, false)
            ->assertSee('session_hour=1', false);

        $form = $this->actingAs($context['user'])->get(route('guru.diniyyah-journals.index', [
            'classroom_term_id' => $context['classroomTerm']->id,
            'date' => self::OVERDUE_DATE,
            'assignment_id' => $context['assignment']->id,
            'session_hour' => '1',
        ]));

        $form->assertOk()
            ->assertDontSee('Masih ada 1 jurnal kosong')
            ->assertSee('value="'.$context['assignment']->id.'|1"', false);
        $this->assertMatchesRegularExpression(
            '/<option value="'.$context['assignment']->id.'\|1"[^>]*\sselected(?:\s|=|>)/',
            $form->getContent(),
        );
    }

    public function test_reminder_excludes_holidays_and_future_schedule_slots(): void
    {
        $this->setNow();
        $context = $this->makeRegularContext('2026-08-01');
        SchoolHoliday::create([
            'school_id' => $context['term']->academicYear->school_id,
            'academic_term_id' => $context['term']->id,
            'holiday_date' => self::OVERDUE_DATE,
            'title' => 'Libur Nasional',
        ]);

        $reminder = app(GuruPerformaService::class)->overdueForActiveTerm($context['teacher']);

        $this->assertNotNull($reminder);
        $this->assertSame(0, $reminder['count']);
        $this->assertSame([], $reminder['empty_slots']);
    }

    public function test_reminder_disappears_after_last_regular_journal_is_saved(): void
    {
        $this->setNow();
        $context = $this->makeRegularContext('2026-08-01');

        $this->actingAs($context['user'])
            ->postJson(route('guru.journal-reminder.snooze'))
            ->assertOk();

        $this->actingAs($context['user'])
            ->post(route('guru.diniyyah-journals.store'), [
                'diniyyah_teacher_assignment_id' => $context['assignment']->id,
                'classroom_term_id' => $context['classroomTerm']->id,
                'date' => self::OVERDUE_DATE,
                'session_hour' => '1',
                'material' => 'Materi pengganti yang lengkap',
                'absences' => [],
            ])
            ->assertRedirect();

        $this->actingAs($context['user'])
            ->get(route('guru.dashboard'))
            ->assertOk()
            ->assertDontSee('Masih ada 1 jurnal kosong');

        $preference = $this->reminderPreferenceFor($context['user']);
        $this->assertArrayNotHasKey('snoozed_until', $preference->preferences ?? []);
    }

    public function test_teacher_can_snooze_reminder_for_three_hours(): void
    {
        $this->setNow();
        $context = $this->makeRegularContext('2026-08-01');

        $response = $this->actingAs($context['user'])
            ->postJson(route('guru.journal-reminder.snooze'));

        $response->assertOk()
            ->assertJsonPath('snoozed_until', now('Asia/Jakarta')->addHours(3)->toIso8601String())
            ->assertJsonPath('snoozed_until_label', '15:00');

        $this->assertSame(
            now('Asia/Jakarta')->addHours(3)->toIso8601String(),
            $this->reminderPreferenceFor($context['user'])->preferences['snoozed_until'],
        );
    }

    public function test_snoozed_reminder_renders_banner_until_the_snooze_expires(): void
    {
        $this->setNow();
        $context = $this->makeRegularContext('2026-08-01');

        $this->actingAs($context['user'])
            ->postJson(route('guru.journal-reminder.snooze'))
            ->assertOk();

        $dashboard = $this->actingAs($context['user'])->get(route('guru.dashboard'));

        $dashboard->assertOk()
            ->assertSee('Buka daftar jurnal')
            ->assertSee('Ingatkan lagi pukul')
            ->assertSee('data-journal-overdue-banner', false)
            ->assertSee('data-journal-overdue-modal', false);
        $this->assertMatchesRegularExpression(
            '/data-journal-overdue-modal\s+hidden/',
            $dashboard->getContent(),
        );
        $this->assertDoesNotMatchRegularExpression(
            '/data-journal-overdue-banner\s+hidden/',
            $dashboard->getContent(),
        );

        $this->setNowAt('2026-08-10 15:01:00');
        $expired = $this->actingAs($context['user'])->get(route('guru.dashboard'));

        $expired->assertOk()
            ->assertSee('Masih ada 1 jurnal kosong')
            ->assertSee('Tutup sementara 3 jam');
        $this->assertDoesNotMatchRegularExpression(
            '/data-journal-overdue-modal\s+hidden/',
            $expired->getContent(),
        );
        $this->assertMatchesRegularExpression(
            '/data-journal-overdue-banner\s+hidden/',
            $expired->getContent(),
        );
    }

    public function test_snooze_endpoint_rejects_users_without_a_teacher_profile(): void
    {
        $this->setNow();
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $guruWithoutProfile = User::factory()->create();
        $guruWithoutProfile->assignRole('guru');
        $nonGuru = User::factory()->create();

        $this->actingAs($guruWithoutProfile)
            ->postJson(route('guru.journal-reminder.snooze'))
            ->assertForbidden();

        $this->actingAs($nonGuru)
            ->postJson(route('guru.journal-reminder.snooze'))
            ->assertForbidden();
    }

    public function test_tafsir_reminder_opens_form_with_all_missing_classes_checked(): void
    {
        $this->setNow();
        $context = $this->makeTafsirContext();

        $reminder = app(GuruPerformaService::class)->overdueForActiveTerm($context['teacher']);

        $this->assertNotNull($reminder);
        $this->assertSame(1, $reminder['count'], 'Tafsir serentak ditagih sebagai satu sesi.');
        $this->assertSame(2, $reminder['class_count']);
        $this->assertTrue($reminder['empty_slots'][0]['is_tafsir']);

        $form = $this->actingAs($context['user'])->get($reminder['empty_slots'][0]['fill_url']);

        $form->assertOk()
            ->assertDontSee('Masih ada 1 jurnal kosong')
            ->assertSee('id="tafsir-'.$context['assignments'][0]->id.'"', false)
            ->assertSee('id="tafsir-'.$context['assignments'][1]->id.'"', false);
        foreach ($context['assignments'] as $assignment) {
            $this->assertMatchesRegularExpression(
                '/<input[^>]*id="tafsir-'.$assignment->id.'"[^>]*\schecked(?:\s|=|>)/',
                $form->getContent(),
            );
        }
    }

    /** @return array{user: User, teacher: Teacher, term: AcademicTerm, classroomTerm: ClassroomTerm, assignment: DiniyyahTeacherAssignment} */
    private function makeRegularContext(string $termStartsAt): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => 'Ustadz Pengingat']);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => 'Ustadz Pengingat']);
        $term = $this->makeTerm('Ganjil', true, $termStartsAt);
        [$assignment, $classroomTerm] = $this->makeRegularAssignment($teacher, $term, 'Mustawa 2 Ikhwan');

        return compact('user', 'teacher', 'term', 'classroomTerm', 'assignment');
    }

    /** @return array{user: User, teacher: Teacher, term: AcademicTerm, assignments: array<int, DiniyyahTeacherAssignment>} */
    private function makeTafsirContext(): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => 'Ustadz Tafsir']);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => 'Ustadz Tafsir']);
        $term = $this->makeTerm('Ganjil', true, '2026-08-01');
        $subject = DiniyyahSubject::firstOrCreate(['code' => 'tafsir'], [
            'code' => 'tafsir',
            'name' => 'Tafsir Al Quran',
            'default_assessment_method' => 'weighted',
            'is_active' => true,
        ]);
        $session = null;
        $assignments = [];

        foreach (['Mustawa 2 Ikhwan', 'Mustawa 3 Ikhwan'] as $name) {
            $classroom = Classroom::create(['name' => $name]);
            SessionTimetable::seedForClassroom($classroom);
            $session ??= ClassSession::where('session_name', SessionTimetable::SESSION_TAFSIR)->firstOrFail();
            $classroomTerm = ClassroomTerm::create([
                'academic_term_id' => $term->id,
                'classroom_id' => $classroom->id,
                'name' => $name,
            ]);
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
                'starts_at' => '2026-08-01',
            ]);
            DiniyyahTeachingSchedule::create([
                'diniyyah_teacher_assignment_id' => $assignment->id,
                'class_session_id' => $session->id,
                'day_of_week' => 4,
            ]);
            $assignments[] = $assignment;
        }

        return compact('user', 'teacher', 'term', 'assignments');
    }

    private function makeTerm(string $name, bool $active, string $startsAt): AcademicTerm
    {
        $school = School::first() ?? School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::first() ?? AcademicYear::create([
            'school_id' => $school->id,
            'name' => '2026/2027',
            'starts_at' => '2026-07-01',
        ]);

        return AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => $name,
            'semester' => strtolower($name),
            'starts_at' => $startsAt,
            'ends_at' => '2026-12-31',
            'is_active' => $active,
        ]);
    }

    /** @return array{0: DiniyyahTeacherAssignment, 1: ClassroomTerm} */
    private function makeRegularAssignment(Teacher $teacher, AcademicTerm $term, string $className): array
    {
        $classroom = Classroom::create(['name' => $className]);
        SessionTimetable::seedForClassroom($classroom);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => $className,
        ]);
        $subject = DiniyyahSubject::firstOrCreate(
            ['code' => 'fiqih'],
            ['name' => 'Fiqih', 'default_assessment_method' => 'weighted', 'is_active' => true],
        );
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
            'starts_at' => '2026-07-01',
        ]);
        $session = ClassSession::where('session_name', '1')->firstOrFail();
        DiniyyahTeachingSchedule::create([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'class_session_id' => $session->id,
            'day_of_week' => 2,
        ]);

        return [$assignment, $classroomTerm];
    }

    private function setNow(): void
    {
        $this->setNowAt(self::TODAY.' 12:00:00');
    }

    private function setNowAt(string $dateTime): void
    {
        Carbon::setTestNow(Carbon::parse($dateTime, 'Asia/Jakarta')->setTimezone('UTC'));
    }

    private function reminderPreferenceFor(User $user): PanelUserPreference
    {
        return PanelUserPreference::query()
            ->where('user_id', $user->id)
            ->where('panel_key', GuruJournalReminderPreferenceService::PANEL_KEY)
            ->firstOrFail();
    }
}
