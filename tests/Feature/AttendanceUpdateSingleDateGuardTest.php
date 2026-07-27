<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\HomeroomAssignment;
use App\Models\School;
use App\Models\SchoolHoliday;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * #4: updateSingle (path presensi aktif) menolak tanggal masa depan, akhir pekan,
 * libur, dan luar semester. Menerima tanggal hari kerja valid dalam semester.
 */
class AttendanceUpdateSingleDateGuardTest extends TestCase
{
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->makeContext();
    }

    public function test_future_date_rejected(): void
    {
        $this->putCode('2099-12-31')->assertStatus(422);
    }

    public function test_weekend_rejected(): void
    {
        // 2026-01-04 = Minggu, dalam semester tapi akhir pekan.
        $this->putCode('2026-01-04')->assertStatus(422);
    }

    public function test_out_of_term_rejected(): void
    {
        // Sebelum semester dimulai (term mulai 2026-01-01... tapi ini Sabtu? 2025-12-01 = Senin, luar term).
        $this->putCode('2025-12-01')->assertStatus(422);
    }

    public function test_holiday_rejected(): void
    {
        SchoolHoliday::create([
            'school_id' => $this->ctx['school']->id,
            'academic_term_id' => $this->ctx['term']->id,
            'holiday_date' => '2026-01-05',
            'title' => 'Libur Ujian',
        ]);

        $this->putCode('2026-01-05')->assertStatus(422);
    }

    public function test_valid_weekday_in_term_accepted(): void
    {
        // 2026-01-05 = Senin, dalam semester, masa lalu relatif hari ini, bukan libur.
        $this->putCode('2026-01-05', 'S')
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertTrue(\App\Models\StudentAttendance::query()
            ->where('class_enrollment_id', $this->ctx['enrollment']->id)
            ->whereDate('attendance_date', '2026-01-05')
            ->where('status', 'sick')
            ->exists());
    }

    private function putCode(string $date, string $code = 'H')
    {
        return $this->actingAs($this->ctx['teacher']->user)
            ->putJson(route('attendance.update-single', $this->ctx['classroomTerm']), [
                'class_enrollment_id' => $this->ctx['enrollment']->id,
                'date' => $date,
                'code' => $code,
            ]);
    }

    private function makeContext(): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => 'Wali Kelas']);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => 'Wali Kelas']);

        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => 'Ganjil',
            'semester' => 'ganjil',
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-06-30',
        ]);
        $classroom = Classroom::create(['name' => 'M3 Ikhwan']);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => 'M3 Ikhwan',
        ]);
        $student = Student::create(['name' => 'Santri 1', 'gender' => 'male', 'nis' => '001']);
        $enrollment = ClassEnrollment::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);
        HomeroomAssignment::create([
            'classroom_term_id' => $classroomTerm->id,
            'teacher_id' => $teacher->id,
            'starts_at' => '2026-01-01',
        ]);

        return [
            'school' => $school,
            'term' => $term,
            'classroomTerm' => $classroomTerm,
            'enrollment' => $enrollment,
            'teacher' => $teacher,
        ];
    }
}