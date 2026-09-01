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
use PhpOffice\PhpSpreadsheet\IOFactory;
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

    public function test_exports_separate_realized_jp_from_empty_journals(): void
    {
        $ctx = $this->context();
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
            'date' => '2026-08-05', 'session_hour' => '1', 'material' => 'Materi', 'jp_count' => 1,
        ]);
        $query = ['classroom_term_id' => $ctx['classroomTerm']->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($ctx['waliUser'])
            ->get(route('wali.jp-recap.index', $query))
            ->assertOk()
            ->assertSee('JP Terealisasi')
            ->assertSee('Rekap JP Terealisasi per Guru')
            ->assertSee('Daftar Jurnal Kosong')
            ->assertSee('Slot berikut tidak termasuk JP terealisasi');

        $excel = $this->actingAs($ctx['waliUser'])
            ->get(route('wali.jp-recap.export-excel', $query));
        $excel->assertOk()->assertDownload('rekap-jp-kelas-wali-2026-08.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'wali-jp-recap-');
        file_put_contents($path, $excel->getContent());

        try {
            $workbook = IOFactory::load($path);
            $this->assertSame(['JP Terealisasi', 'Jurnal Kosong'], $workbook->getSheetNames());
            $this->assertSame('JP Terealisasi', $workbook->getSheetByName('JP Terealisasi')->getCell('G7')->getValue());
            $this->assertSame(1, $workbook->getSheetByName('JP Terealisasi')->getCell('G8')->getValue());
            $this->assertSame('Guru', $workbook->getSheetByName('Jurnal Kosong')->getCell('B5')->getValue());
            $this->assertSame('Guru Mapel', $workbook->getSheetByName('Jurnal Kosong')->getCell('B6')->getValue());
            $this->assertSame('Fiqih', $workbook->getSheetByName('Jurnal Kosong')->getCell('E6')->getValue());
        } finally {
            @unlink($path);
        }

        $this->actingAs($ctx['waliUser'])
            ->get(route('wali.jp-recap.export-pdf', $query))
            ->assertOk()
            ->assertDownload('rekap-jp-kelas-wali-2026-08.pdf');
    }

    public function test_realized_jp_is_credited_only_to_the_teacher_who_filled_the_journal(): void
    {
        $ctx = $this->context();
        $substituteUser = User::factory()->create(['name' => 'Guru Pengganti']);
        $substituteUser->assignRole('guru');
        $substitute = Teacher::create(['user_id' => $substituteUser->id, 'name' => 'Guru Pengganti']);
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
            'substitute_teacher_id' => $substitute->id,
            'date' => '2026-08-05', 'session_hour' => '1', 'material' => 'Diisi guru pengganti', 'jp_count' => 1,
        ]);
        $query = ['classroom_term_id' => $ctx['classroomTerm']->id, 'month' => 8, 'year' => 2026];

        $excel = $this->actingAs($ctx['waliUser'])
            ->get(route('wali.jp-recap.export-excel', $query));
        $excel->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'wali-jp-effective-teacher-');
        file_put_contents($path, $excel->getContent());

        try {
            $workbook = IOFactory::load($path);
            $realized = $workbook->getSheetByName('JP Terealisasi');
            $missing = $workbook->getSheetByName('Jurnal Kosong');

            $this->assertSame('Guru Mapel', $realized->getCell('B8')->getValue());
            $this->assertSame(0, $realized->getCell('G8')->getValue(), 'Pemilik jadwal yang digantikan tidak menerima JP.');
            $this->assertSame('Guru Pengganti', $realized->getCell('B9')->getValue());
            $this->assertSame(1, $realized->getCell('G9')->getValue(), 'JP dikreditkan kepada guru yang mengisi jurnal pengganti.');
            $this->assertSame('Guru Mapel', $missing->getCell('B6')->getValue(), 'Slot kosong tetap dilaporkan pada guru pemilik jadwal tanpa menambah JP.');
        } finally {
            @unlink($path);
        }
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
