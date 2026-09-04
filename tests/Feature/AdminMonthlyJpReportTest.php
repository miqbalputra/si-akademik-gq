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
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AdminMonthlyJpReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminMonthlyJpReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_report_includes_all_account_teachers_and_consolidates_tafsir(): void
    {
        $ctx = $this->context();
        DiniyyahClassJournal::create(['diniyyah_teacher_assignment_id' => $ctx['regular']->id, 'date' => '2026-08-05', 'session_hour' => '1', 'material' => 'Fiqih', 'jp_count' => 1]);
        DiniyyahClassJournal::create(['diniyyah_teacher_assignment_id' => $ctx['substituteSlot']->id, 'substitute_teacher_id' => $ctx['substitute']->id, 'date' => '2026-08-05', 'session_hour' => '2', 'material' => 'Digantikan', 'jp_count' => 1]);
        foreach ($ctx['tafsir'] as $assignment) {
            DiniyyahClassJournal::create(['diniyyah_teacher_assignment_id' => $assignment->id, 'date' => '2026-08-05', 'session_hour' => 'tafsir', 'session_starts_at' => '08:00:00', 'session_ends_at' => '09:00:00', 'material' => 'Tafsir bersama', 'jp_count' => 1]);
        }

        $report = app(AdminMonthlyJpReportService::class)->build($ctx['term']->id, 8, 2026);
        $byTeacher = collect($report['teachers'])->keyBy('teacher_id');
        $this->assertCount(3, $report['teachers']);
        $this->assertSame(2, $byTeacher[$ctx['teacher']->id]['total_jp']);
        $this->assertSame(1, $byTeacher[$ctx['teacher']->id]['sesi_tafsir']);
        $this->assertGreaterThan(0, $byTeacher[$ctx['teacher']->id]['missing_count']);
        $this->assertSame(1, $byTeacher[$ctx['substitute']->id]['total_jp']);
        $this->assertSame(0, $byTeacher[$ctx['idle']->id]['total_jp']);
        $this->assertCount(1, collect($report['realized'])->where('type', 'Tafsir serentak'));
        $this->assertCount(3, collect($report['missing'])->where('session', 'Tafsir serentak'));
        $this->assertSame(3, $report['stats']['total_jp']);

        $response = $this->actingAs($ctx['admin'])->get(route('admin.monthly-jp-recap.export', ['format' => 'xlsx', 'academic_term_id' => $ctx['term']->id, 'month' => 8, 'year' => 2026]));
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'admin-jp-');
        file_put_contents($path, $response->getContent());
        try {
            $book = IOFactory::load($path);
            $this->assertSame(['Rekap JP', 'Detail JP Terealisasi', 'Jurnal Kosong'], $book->getSheetNames());
            $this->assertSame('Kelas', $book->getSheetByName('Rekap JP')->getCell('E5')->getValue());
            $book->disconnectWorksheets();
        } finally {
            @unlink($path);
        }
        $this->actingAs($ctx['admin'])->get(route('admin.monthly-jp-recap.export', ['format' => 'pdf', 'academic_term_id' => $ctx['term']->id, 'month' => 8, 'year' => 2026]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($ctx['teacherUser'])->get(route('admin.monthly-jp-recap.index'))->assertForbidden();
    }

    private function context(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $teacherUser = User::factory()->create(['name' => 'Guru A']);
        $teacherUser->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'name' => 'Guru A', 'niy' => 'A001', 'status' => 'active']);
        $subUser = User::factory()->create(['name' => 'Guru Ganti']);
        $subUser->assignRole('guru');
        $substitute = Teacher::create(['user_id' => $subUser->id, 'name' => 'Guru Ganti', 'niy' => 'B001', 'status' => 'active']);
        $idleUser = User::factory()->create(['name' => 'Guru Kosong']);
        $idleUser->assignRole('guru');
        $idle = Teacher::create(['user_id' => $idleUser->id, 'name' => 'Guru Kosong', 'niy' => 'C001', 'status' => 'active']);
        $school = School::create(['name' => 'GQ']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil', 'starts_at' => '2026-07-01', 'ends_at' => '2026-12-31', 'is_active' => true]);
        $s1 = ClassSession::create(['session_name' => '1', 'starts_at' => '08:00', 'ends_at' => '09:00', 'is_break' => false]);
        $s2 = ClassSession::create(['session_name' => '2', 'starts_at' => '09:00', 'ends_at' => '10:00', 'is_break' => false]);
        $make = function (string $className, string $subjectName, string $code) use ($term, $teacher) {
            $classroom = Classroom::create(['name' => $className]);
            $classTerm = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => $className]);
            $subject = DiniyyahSubject::create(['name' => $subjectName, 'code' => $code, 'default_assessment_method' => 'weighted']);
            $classSubject = DiniyyahClassSubject::create(['classroom_term_id' => $classTerm->id, 'subject_id' => $subject->id, 'assessment_method' => 'weighted', 'kkm' => 70, 'daily_weight' => 40, 'exam_weight' => 60]);

            return DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $classSubject->id, 'teacher_id' => $teacher->id, 'assignment_role' => 'primary']);
        };
        $regular = $make('Kelas Reguler', 'Fiqih', 'fiqih');
        $substituteSlot = $make('Kelas Pengganti', 'Nahwu', 'nahwu');
        $missing = $make('Kelas Kosong', 'Aqidah', 'aqidah');
        $tafsirA = $make('Kelas Tafsir A', 'Tafsir A', 'tafsir-a');
        $tafsirB = $make('Kelas Tafsir B', 'Tafsir B', 'tafsir-b');
        foreach ([[$regular, $s1], [$substituteSlot, $s2], [$missing, $s2], [$tafsirA, $s1], [$tafsirB, $s1]] as [$assignment, $session]) {
            DiniyyahTeachingSchedule::create(['diniyyah_teacher_assignment_id' => $assignment->id, 'class_session_id' => $session->id, 'day_of_week' => 3]);
        }

        return compact('admin', 'teacherUser', 'teacher', 'substitute', 'idle', 'term', 'regular', 'substituteSlot') + ['tafsir' => [$tafsirA, $tafsirB]];
    }
}
