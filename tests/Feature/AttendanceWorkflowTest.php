<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\HomeroomAssignment;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_homeroom_teacher_can_input_monthly_attendance_grid(): void
    {
        [$classroomTerm, $teacherUser, $enrollment] = $this->makeAttendanceClass();

        $this->actingAs($teacherUser)
            ->get(route('attendance.edit', ['classroomTerm' => $classroomTerm, 'month' => '2025-07']))
            ->assertOk()
            ->assertSee('Presensi')
            ->assertSee('Santri 1')
            ->assertSee('Senin')
            ->assertDontSee('2025-07-19', false)
            ->assertDontSee('2025-07-20', false);

        $this->actingAs($teacherUser)
            ->put(route('attendance.update', $classroomTerm), [
                'month' => '2025-07',
                'attendance' => [
                    $enrollment->id => [
                        '2025-07-14' => 'S',
                        '2025-07-15' => 'I',
                        '2025-07-16' => 'A',
                        '2025-07-17' => 'L',
                    ],
                ],
            ])
            ->assertRedirect(route('attendance.edit', ['classroomTerm' => $classroomTerm, 'month' => '2025-07']));

        $this->assertTrue(StudentAttendance::query()
            ->where('class_enrollment_id', $enrollment->id)
            ->whereDate('attendance_date', '2025-07-14')
            ->where('status', StudentAttendance::STATUS_SICK)
            ->where('input_by', $teacherUser->id)
            ->exists());
        $this->assertTrue(StudentAttendance::query()
            ->where('class_enrollment_id', $enrollment->id)
            ->whereDate('attendance_date', '2025-07-15')
            ->where('status', StudentAttendance::STATUS_PERMISSION)
            ->exists());
        $this->assertTrue(StudentAttendance::query()
            ->where('class_enrollment_id', $enrollment->id)
            ->whereDate('attendance_date', '2025-07-16')
            ->where('status', StudentAttendance::STATUS_ABSENT)
            ->exists());
        $this->assertTrue(StudentAttendance::query()
            ->where('class_enrollment_id', $enrollment->id)
            ->whereDate('attendance_date', '2025-07-17')
            ->where('status', StudentAttendance::STATUS_HOLIDAY)
            ->exists());
    }

    public function test_weekend_days_are_not_rendered_in_attendance_grid(): void
    {
        [$classroomTerm, $teacherUser] = $this->makeAttendanceClass();

        $this->actingAs($teacherUser)
            ->get(route('attendance.edit', ['classroomTerm' => $classroomTerm, 'month' => '2025-07']))
            ->assertOk()
            ->assertSee('Senin')
            ->assertDontSee('2025-07-19', false)
            ->assertDontSee('2025-07-20', false);
    }

    public function test_unassigned_teacher_cannot_input_attendance_for_other_class(): void
    {
        [$classroomTerm] = $this->makeAttendanceClass();
        $otherUser = User::factory()->create();
        $otherUser->assignRole($this->role('guru'));
        Teacher::create([
            'user_id' => $otherUser->id,
            'name' => 'Guru Lain',
            'gender' => 'male',
            'status' => 'active',
        ]);

        $this->actingAs($otherUser)
            ->get(route('attendance.edit', ['classroomTerm' => $classroomTerm, 'month' => '2025-07']))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->put(route('attendance.update', $classroomTerm), [
                'month' => '2025-07',
                'attendance' => [],
            ])
            ->assertForbidden();
    }

    public function test_admin_can_open_attendance_for_all_classes(): void
    {
        [$classroomTerm] = $this->makeAttendanceClass();
        $admin = User::factory()->create();
        $admin->assignRole($this->role('admin'));

        $this->actingAs($admin)
            ->get(route('attendance.edit', ['classroomTerm' => $classroomTerm, 'month' => '2025-07']))
            ->assertOk()
            ->assertSee('Santri 1');
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

        return [$classroomTerm, $teacherUser, $enrollment];
    }

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
}
