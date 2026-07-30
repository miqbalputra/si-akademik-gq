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
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SessionTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Sinkronisasi input Jurnal Kelas diniyyah dengan jadwal mengajar guru (asli):
 *  - weekday tampil di dekat input tanggal (Jurnal Kelas & Pengganti);
 *  - form dimatikan + peringatan berkonteks kelas bila tanggal terpilih bukan
 *    hari mengajar guru di kelas terpilih (cek DiniyyahTeachingSchedule).
 *
 * Matrix Mustawa 2 Ikhwan: Senin (1) ada sesi; Sabtu (6) tidak ada sesi.
 * 2026-07-13 = Senin, 2026-07-18 = Sabtu.
 */
class GuruDiniyyahJournalScheduleTest extends TestCase
{
    use RefreshDatabase;

    /** 2026-07-13 = Senin (matrix M2 Ikhwan punya sesi). */
    private const SENIN = '2026-07-13';

    /** 2026-07-18 = Sabtu (matrix M2 Ikhwan tidak punya sesi). */
    private const SABTU = '2026-07-18';

    public function test_form_shown_when_teacher_scheduled_that_day(): void
    {
        $ctx = $this->makeTeacherWithClass();

        // Guru dijadwalkan mengajar kelas ini di hari Senin (session '1').
        $this->makeSchedule($ctx['assignment'], dayOfWeek: 1);

        $this->actingAs($ctx['user'])
            ->get(route('guru.diniyyah-journals.index', [
                'classroom_term_id' => $ctx['classroomTerm']->id,
                'date' => self::SENIN,
            ]))
            ->assertOk()
            ->assertSee('Isi Jam Pelajaran Anda')
            ->assertSee('Simpan Jurnal Jam Ini')
            ->assertDontSee('tidak ada jadwal mengajar untuk Anda');
    }

    public function test_form_hidden_with_warning_when_teacher_not_scheduled(): void
    {
        $ctx = $this->makeTeacherWithClass();

        // Guru punya assignment di kelas ini, tapi TIDAK dijadwalkan di hari Senin.
        $this->actingAs($ctx['user'])
            ->get(route('guru.diniyyah-journals.index', [
                'classroom_term_id' => $ctx['classroomTerm']->id,
                'date' => self::SENIN,
            ]))
            ->assertOk()
            ->assertSee('tidak ada jadwal mengajar untuk Anda di kelas Mustawa 2 Ikhwan')
            ->assertDontSee('Simpan Jurnal Jam Ini')
            ->assertDontSee('Isi Jam Pelajaran Anda');
    }

    public function test_matrix_empty_day_shows_no_sessions_message(): void
    {
        $ctx = $this->makeTeacherWithClass();

        // Sabtu: matrix kelas tidak punya sesi → pesan "Tidak ada sesi diniyyah",
        // BUKAN peringatan "tidak ada jadwal mengajar untuk Anda" (matrix kosong
        // diprioritaskan: kelas tidak ada KBM sama sekali di hari itu).
        $this->actingAs($ctx['user'])
            ->get(route('guru.diniyyah-journals.index', [
                'classroom_term_id' => $ctx['classroomTerm']->id,
                'date' => self::SABTU,
            ]))
            ->assertOk()
            ->assertSee('Tidak ada sesi diniyyah di hari ini')
            ->assertDontSee('tidak ada jadwal mengajar untuk Anda');
    }

    public function test_weekday_displayed_near_date_input(): void
    {
        $ctx = $this->makeTeacherWithClass();

        // Tampilan weekday muncul walau kelas belum dipilih — guru langsung tahu
        // hari dari tanggal yg dipilih.
        $this->actingAs($ctx['user'])
            ->get(route('guru.diniyyah-journals.index', ['date' => self::SENIN]))
            ->assertOk()
            ->assertSee('Senin');
    }

    public function test_substitute_page_displays_weekday(): void
    {
        $ctx = $this->makeTeacherWithClass();

        $this->actingAs($ctx['user'])
            ->get(route('guru.diniyyah-substitute-journals.index', ['date' => self::SENIN]))
            ->assertOk()
            ->assertSee('Senin');
    }

    /**
     * Buat guru (role 'guru') + satu assignment diniyyah umum (Fiqih) di kelas
     * Mustawa 2 Ikhwan, lengkap dengan matrix sesi (SessionTimetable::seedForClassroom).
     *
     * @return array{user: User, teacher: Teacher, assignment: DiniyyahTeacherAssignment, classroomTerm: ClassroomTerm}
     */
    private function makeTeacherWithClass(string $classroomName = 'Mustawa 2 Ikhwan'): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => 'Ustadz Ahmad']);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => 'Ustadz Ahmad']);

        $termId = $this->academicTermId();

        $classroom = Classroom::create(['name' => $classroomName]);
        SessionTimetable::seedForClassroom($classroom);

        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $termId,
            'classroom_id' => $classroom->id,
            'name' => $classroomName,
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
        ]);

        return [
            'user' => $user,
            'teacher' => $teacher,
            'assignment' => $assignment,
            'classroomTerm' => $classroomTerm,
        ];
    }

    /**
     * Buat satu baris DiniyyahTeachingSchedule untuk assignment pada day_of_week
     * tertentu, di sesi '1' (ClassSession pertama yg di-seed SessionTimetable).
     */
    private function makeSchedule(DiniyyahTeacherAssignment $assignment, int $dayOfWeek): DiniyyahTeachingSchedule
    {
        $session = ClassSession::where('session_name', '1')->firstOrFail();

        return DiniyyahTeachingSchedule::create([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'class_session_id' => $session->id,
            'day_of_week' => $dayOfWeek,
        ]);
    }

    private function academicTermId(): int
    {
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);

        return AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil'])->id;
    }
}