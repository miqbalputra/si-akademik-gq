<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassSession;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\School;
use App\Models\SchoolEvent;
use App\Models\Teacher;
use App\Models\User;
use App\Services\DiniyyahNoKbmAgendaService;
use App\Services\DiniyyahJournalReportService;
use App\Services\GuruPerformaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruNoKbmAgendaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_agenda_without_kbm_is_virtual_and_not_counted_as_empty(): void
    {
        $this->setNow('2026-08-10');
        $fixture = $this->fixture('male');

        SchoolEvent::create([
            'school_id' => $fixture['school']->id,
            'academic_term_id' => $fixture['term']->id,
            'title' => 'Outdoor Bersama',
            'event_type' => 'outdoor',
            'is_no_kbm' => true,
            'starts_on' => '2026-08-04',
            'ends_on' => '2026-08-04',
            'target_scope' => 'all',
        ]);

        $performa = app(GuruPerformaService::class)->calculate($fixture['teacher'], 8, 2026);

        $this->assertSame(1, $performa['stats']['agenda']);
        $this->assertSame(0, $performa['stats']['kosong']);
        $this->assertSame(1, $performa['stats']['total']);
        $this->assertCount(1, $performa['agenda_rows']);
        $this->assertSame('Libur Mengajar - Agenda Outdoor Bersama', $performa['agenda_rows']->first()['material']);
    }

    public function test_gender_scope_does_not_match_mixed_or_other_gender_class(): void
    {
        $fixture = $this->fixture('male');
        $event = SchoolEvent::create([
            'school_id' => $fixture['school']->id,
            'academic_term_id' => $fixture['term']->id,
            'title' => 'Kegiatan Akhwat',
            'event_type' => 'religious',
            'is_no_kbm' => true,
            'starts_on' => '2026-08-04',
            'ends_on' => '2026-08-04',
            'target_scope' => 'gender',
            'target_gender_group' => 'female',
        ]);

        $events = app(DiniyyahNoKbmAgendaService::class)->eventsForRange(
            collect([$fixture['classroomTerm']]),
            Carbon::parse('2026-08-04', 'Asia/Jakarta'),
            Carbon::parse('2026-08-04', 'Asia/Jakarta'),
        );

        $this->assertNull(app(DiniyyahNoKbmAgendaService::class)->forClassroomTerm($events, $fixture['classroomTerm'], '2026-08-04'));
        $this->assertNotNull($event->fresh());
    }

    public function test_regular_input_cannot_create_journal_on_no_kbm_slot(): void
    {
        $fixture = $this->fixture('male');
        SchoolEvent::create([
            'school_id' => $fixture['school']->id,
            'academic_term_id' => $fixture['term']->id,
            'title' => 'Outdoor',
            'event_type' => 'outdoor',
            'is_no_kbm' => true,
            'starts_on' => '2026-08-04',
            'ends_on' => '2026-08-04',
            'target_scope' => 'all',
        ]);

        $this->actingAs($fixture['user'])
            ->post(route('guru.diniyyah-journals.store'), [
                'diniyyah_teacher_assignment_id' => $fixture['assignment']->id,
                'classroom_term_id' => $fixture['classroomTerm']->id,
                'date' => '2026-08-04',
                'session_hour' => '1',
                'material' => 'Tidak seharusnya dibuat',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, DiniyyahClassJournal::query()->count());
    }

    public function test_journal_report_includes_virtual_agenda_row(): void
    {
        $fixture = $this->fixture('male');
        SchoolEvent::create([
            'school_id' => $fixture['school']->id,
            'academic_term_id' => $fixture['term']->id,
            'title' => 'Outdoor Laporan',
            'event_type' => 'outdoor',
            'is_no_kbm' => true,
            'starts_on' => '2026-08-04',
            'ends_on' => '2026-08-04',
            'target_scope' => 'all',
        ]);

        $report = app(DiniyyahJournalReportService::class)->build([
            'date_from' => '2026-08-04',
            'date_until' => '2026-08-04',
        ], $fixture['teacher']->id);

        $this->assertSame(1, $report['stats']['agenda']);
        $this->assertSame('AGENDA', $report['rows']->first()['status']);
        $this->assertSame('Libur Mengajar - Agenda Outdoor Laporan', $report['rows']->first()['material']);
    }

    /** @return array<string, mixed> */
    private function fixture(string $gender): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => 'Guru Agenda']);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => 'Guru Agenda']);
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => 'Ganjil',
            'semester' => 'ganjil',
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-12-31',
        ]);
        $classroom = Classroom::create(['name' => 'Mustawa 2 Ikhwan', 'gender_group' => $gender]);
        $classroomTerm = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => $classroom->name]);
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
        $session = ClassSession::query()->where('session_name', '1')->firstOrFail();
        DiniyyahTeachingSchedule::create([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'class_session_id' => $session->id,
            'day_of_week' => 2,
        ]);

        return compact('user', 'teacher', 'school', 'term', 'classroomTerm', 'assignment');
    }

    private function setNow(string $date): void
    {
        Carbon::setTestNow(Carbon::parse($date.' 12:00:00', 'Asia/Jakarta')->setTimezone('UTC'));
    }
}
