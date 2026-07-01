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
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolHolidayAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_holiday_is_hidden_from_attendance_grid_and_reason_is_shown(): void
    {
        [$classroomTerm, $teacherUser] = $this->makeAttendanceClass();

        SchoolHoliday::create([
            'school_id' => $classroomTerm->academicTerm->academicYear->school_id,
            'academic_term_id' => $classroomTerm->academic_term_id,
            'holiday_date' => '2025-07-16',
            'title' => 'Libur Muharram',
            'description' => 'Agenda sekolah khusus internal.',
        ]);

        $this->actingAs($teacherUser)
            ->get(route('attendance.edit', ['classroomTerm' => $classroomTerm, 'month' => '2025-07']))
            ->assertOk()
            ->assertSee('Libur sekolah di Juli 2025')
            ->assertSee('Libur Muharram')
            ->assertSee('Agenda sekolah khusus internal.')
            ->assertDontSee('2025-07-16', false);
    }

    public function test_school_holiday_date_is_ignored_during_attendance_update(): void
    {
        [$classroomTerm, $teacherUser, $enrollment] = $this->makeAttendanceClass();

        SchoolHoliday::create([
            'school_id' => $classroomTerm->academicTerm->academicYear->school_id,
            'academic_term_id' => $classroomTerm->academic_term_id,
            'holiday_date' => '2025-07-16',
            'title' => 'Libur Muharram',
        ]);

        $this->actingAs($teacherUser)
            ->put(route('attendance.update', $classroomTerm), [
                'month' => '2025-07',
                'attendance' => [
                    $enrollment->id => [
                        '2025-07-14' => 'H',
                        '2025-07-15' => 'S',
                        '2025-07-16' => 'A',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertTrue(StudentAttendance::query()
            ->where('class_enrollment_id', $enrollment->id)
            ->whereDate('attendance_date', '2025-07-15')
            ->where('status', StudentAttendance::STATUS_SICK)
            ->exists());
        $this->assertFalse(StudentAttendance::query()
            ->where('class_enrollment_id', $enrollment->id)
            ->whereDate('attendance_date', '2025-07-16')
            ->exists());
    }

    /** @return array{ClassroomTerm, User, ClassEnrollment} */
    private function makeAttendanceClass(): array
    {
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => 'Semester Ganjil',
            'semester' => 'ganjil',
            'starts_at' => '2025-07-14',
            'ends_at' => '2025-12-31',
            'is_active' => true,
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
            'roll_number' => 1,
        ]);
        $teacherUser = User::factory()->create();
        $teacherUser->assignRole($this->role('guru'));
        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'name' => 'Ustadz Wali',
            'gender' => 'male',
            'status' => 'active',
        ]);
        HomeroomAssignment::create([
            'classroom_term_id' => $classroomTerm->id,
            'teacher_id' => $teacher->id,
            'starts_at' => '2025-07-14',
        ]);

        return [$classroomTerm->load('academicTerm.academicYear'), $teacherUser, $enrollment];
    }

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
}
