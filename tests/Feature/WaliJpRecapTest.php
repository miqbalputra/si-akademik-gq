<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassSession;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\HomeroomAssignment;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WaliJpRecapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-20 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_wali_sees_only_her_class_and_can_confirm_a_complete_teacher(): void
    {
        $ctx = $this->context();
        foreach (['2026-08-05', '2026-08-12', '2026-08-19'] as $date) {
            DiniyyahClassJournal::create([
                'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
                'date' => $date, 'session_hour' => '1', 'material' => 'Materi', 'jp_count' => 1,
            ]);
        }

        $this->actingAs($ctx['waliUser'])
            ->get(route('wali.jp-recap.index', ['classroom_term_id' => $ctx['classroomTerm']->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertSee('Rekap JP Kelas')
            ->assertSee($ctx['teacher']->name)
            ->assertDontSee($ctx['otherClassroomTerm']->name);

        $this->actingAs($ctx['waliUser'])
            ->post(route('wali.jp-recap.confirm'), [
                'classroom_term_id' => $ctx['classroomTerm']->id, 'month' => 8, 'year' => 2026,
                'teacher_id' => $ctx['teacher']->id, 'mode' => 'normal',
            ])->assertRedirect();

        $this->assertDatabaseHas('homeroom_monthly_jp_confirmations', [
            'classroom_term_id' => $ctx['classroomTerm']->id,
            'homeroom_teacher_id' => $ctx['waliTeacher']->id,
            'teacher_id' => $ctx['teacher']->id,
            'is_override' => false,
            'confirmed_jp' => 3,
        ]);
    }

    public function test_non_wali_and_forged_class_are_forbidden(): void
    {
        $ctx = $this->context();

        $this->actingAs($ctx['otherUser'])
            ->get(route('wali.jp-recap.index', ['classroom_term_id' => $ctx['classroomTerm']->id, 'month' => 8, 'year' => 2026]))
            ->assertForbidden();

        $this->actingAs($ctx['waliUser'])
            ->get(route('wali.jp-recap.index', ['classroom_term_id' => $ctx['otherClassroomTerm']->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertSee($ctx['classroomTerm']->name)
            ->assertDontSee($ctx['otherClassroomTerm']->name);
    }

    public function test_missing_journal_requires_reason_for_override(): void
    {
        $ctx = $this->context();

        $this->actingAs($ctx['waliUser'])
            ->post(route('wali.jp-recap.confirm'), [
                'classroom_term_id' => $ctx['classroomTerm']->id, 'month' => 8, 'year' => 2026,
                'teacher_id' => $ctx['teacher']->id, 'mode' => 'normal',
            ])->assertStatus(422);

        $this->actingAs($ctx['waliUser'])
            ->post(route('wali.jp-recap.confirm'), [
                'classroom_term_id' => $ctx['classroomTerm']->id, 'month' => 8, 'year' => 2026,
                'teacher_id' => $ctx['teacher']->id, 'mode' => 'override', 'override_reason' => 'Guru sedang izin.',
            ])->assertRedirect();

        $this->assertDatabaseHas('homeroom_monthly_jp_confirmations', ['is_override' => true, 'override_reason' => 'Guru sedang izin.']);
    }

    public function test_confirmation_requires_review_again_when_journal_data_changes(): void
    {
        $ctx = $this->context();
        $this->actingAs($ctx['waliUser'])->post(route('wali.jp-recap.confirm'), [
            'classroom_term_id' => $ctx['classroomTerm']->id, 'month' => 8, 'year' => 2026,
            'teacher_id' => $ctx['teacher']->id, 'mode' => 'override', 'override_reason' => 'Menunggu jurnal.',
        ])->assertRedirect();
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
            'date' => '2026-08-05', 'session_hour' => '1', 'material' => 'Materi', 'jp_count' => 1,
        ]);

        $this->actingAs($ctx['waliUser'])
            ->get(route('wali.jp-recap.index', ['classroom_term_id' => $ctx['classroomTerm']->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertSee('Perlu cek ulang');
    }

    private function context(): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $waliUser = User::factory()->create(['name' => 'Wali']); $waliUser->assignRole('guru');
        $waliTeacher = Teacher::create(['user_id' => $waliUser->id, 'name' => 'Wali']);
        $teacherUser = User::factory()->create(['name' => 'Guru Mapel']); $teacherUser->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'name' => 'Guru Mapel']);
        $otherUser = User::factory()->create(['name' => 'Guru Lain']); $otherUser->assignRole('guru');
        Teacher::create(['user_id' => $otherUser->id, 'name' => 'Guru Lain']);
        $school = School::create(['name' => 'GQ']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil', 'is_active' => true]);
        $classroom = Classroom::create(['name' => 'Kelas Wali']);
        $classroomTerm = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => 'Kelas Wali']);
        $otherClassroom = Classroom::create(['name' => 'Kelas Lain']);
        $otherClassroomTerm = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $otherClassroom->id, 'name' => 'Kelas Lain']);
        HomeroomAssignment::create(['classroom_term_id' => $classroomTerm->id, 'teacher_id' => $waliTeacher->id]);
        $subject = DiniyyahSubject::create(['code' => 'fiqih', 'name' => 'Fiqih', 'default_assessment_method' => 'weighted']);
        $classSubject = DiniyyahClassSubject::create(['classroom_term_id' => $classroomTerm->id, 'subject_id' => $subject->id, 'assessment_method' => 'weighted', 'kkm' => 70, 'daily_weight' => 40, 'exam_weight' => 60]);
        $assignment = DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $classSubject->id, 'teacher_id' => $teacher->id, 'assignment_role' => 'primary']);
        $session = ClassSession::create(['session_name' => '1', 'starts_at' => '08:00', 'ends_at' => '08:30', 'is_break' => false]);
        DiniyyahTeachingSchedule::create(['diniyyah_teacher_assignment_id' => $assignment->id, 'class_session_id' => $session->id, 'day_of_week' => 3]);

        return compact('waliUser', 'waliTeacher', 'teacher', 'otherUser', 'classroomTerm', 'otherClassroomTerm', 'assignment');
    }
}
