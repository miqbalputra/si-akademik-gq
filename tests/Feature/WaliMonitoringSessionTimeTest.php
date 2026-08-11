<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassSession;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\HomeroomAssignment;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SessionTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pantau Jurnal Kelas (wali kelas) harus menampilkan jam sesi per-classroom
 * dari matrix `class_session_times` (via SessionTimetable), BUKAN jam default
 * global `class_sessions`. Ikhwan Senin = 07:40/08:10, Akhwat Senin = 10:30/11:00.
 *
 * Sebelum fix, blade memakai `$schedule->classSession->starts_at` (global '1'→10:30)
 * sehingga kelas Ikhwan di Senin tampil 10:30 — gejala "belum sesuai jam diniyyah".
 */
class WaliMonitoringSessionTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_wali_monitoring_ikhwan_senin_shows_0740(): void
    {
        $ctx = $this->makeContext('ikhwan', 5);

        // Bulan lampau (Maret 2025) supaya pasti bukan currentMonth & tidak future.
        $this->actingAs($ctx['user'])
            ->get(route('wali.diniyyah-journals.index', ['month' => 3, 'year' => 2025]))
            ->assertOk()
            ->assertSee('Portal Guru')
            ->assertSee('Monitoring Jurnal Kelas')
            ->assertSee('07:40')
            ->assertSee('08:10')
            ->assertDontSee('10:30');
    }

    public function test_wali_monitoring_akhwat_senin_shows_1030(): void
    {
        $ctx = $this->makeContext('akhwat', 5);

        $this->actingAs($ctx['user'])
            ->get(route('wali.diniyyah-journals.index', ['month' => 3, 'year' => 2025]))
            ->assertOk()
            ->assertSee('10:30')
            ->assertDontSee('07:40');
    }

    private function makeContext(string $gender, int $level): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => 'Wali Kelas']);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => 'Wali Kelas']);

        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2024/2025']);
        $termId = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => 'Genap',
            'semester' => 'genap',
        ])->id;

        $classroom = Classroom::create(['name' => sprintf('Mustawa %d %s', $level, ucfirst($gender))]);
        SessionTimetable::seedForClassroom($classroom);

        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $termId,
            'classroom_id' => $classroom->id,
            'name' => $classroom->name,
        ]);

        // Wali kelas aktif untuk classroom ini.
        HomeroomAssignment::create([
            'classroom_term_id' => $classroomTerm->id,
            'teacher_id' => $teacher->id,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $subject = DiniyyahSubject::create([
            'code' => 'fiqih',
            'name' => 'Fiqih',
            'default_assessment_method' => 'weighted',
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
        ]);

        // Jadwal mengajar Senin (day_of_week=1), Sesi 1 & 2. ClassSession '1'/'2'
        // dibuat oleh SessionTimetable::seedForClassroom() -> ensureClassSessions().
        $sessionOne = ClassSession::where('session_name', '1')->first();
        $sessionTwo = ClassSession::where('session_name', '2')->first();

        DiniyyahTeachingSchedule::create([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'day_of_week' => 1,
            'class_session_id' => $sessionOne->id,
        ]);
        DiniyyahTeachingSchedule::create([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'day_of_week' => 1,
            'class_session_id' => $sessionTwo->id,
        ]);

        return [
            'user' => $user,
            'teacher' => $teacher,
            'classroomTerm' => $classroomTerm,
            'assignment' => $assignment,
        ];
    }
}
