<?php

namespace Tests\Feature;

use App\Filament\Resources\StudentDepartures\StudentDepartureResource;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\Guardian;
use App\Models\HomeroomAssignment;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentDeparture;
use App\Models\Teacher;
use App\Models\TahfidzHalaqah;
use App\Models\TahfidzHalaqahMember;
use App\Models\User;
use App\Services\StudentDepartureWorkflow;
use App\Services\PlacementService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StudentDepartureWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_departure_archives_student_and_preserves_related_data(): void
    {
        [$student, $enrollment, $attendance, , $guardian, $halaqahMember] = $this->makeStudentContext();
        $admin = $this->userWithRole('admin');

        $departure = app(StudentDepartureWorkflow::class)->depart($student, [
            'type' => StudentDeparture::TYPE_TRANSFER,
            'effective_date' => '2026-10-01',
            'reason' => 'Keluarga pindah domisili.',
            'destination_school' => 'Sekolah Tujuan',
        ], $admin);

        $archivedStudent = Student::withTrashed()->findOrFail($student->id);
        $this->assertNotNull($archivedStudent->deleted_at);
        $this->assertSame('inactive', $archivedStudent->status);
        $this->assertSame('inactive', $enrollment->fresh()->status);
        $this->assertSame('inactive', $halaqahMember->fresh()->status);
        $this->assertSame('2026-10-01', $halaqahMember->fresh()->left_at->toDateString());
        $this->assertDatabaseHas('student_departures', [
            'id' => $departure->id,
            'student_id' => $student->id,
            'type' => StudentDeparture::TYPE_TRANSFER,
            'destination_school' => 'Sekolah Tujuan',
            'reason' => 'Keluarga pindah domisili.',
            'exited_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('student_attendances', [
            'id' => $attendance->id,
            'student_id' => $student->id,
            'status' => StudentAttendance::STATUS_SICK,
        ]);
        $this->assertDatabaseHas('student_guardians', [
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
        ]);
        $this->assertDatabaseHas('class_enrollments', ['id' => $enrollment->id]);
        $this->assertDatabaseHas('tahfidz_halaqah_members', ['id' => $halaqahMember->id]);
        $this->assertSame(1, StudentAttendance::query()->where('student_id', $student->id)->count());
        $this->assertSame(1, $archivedStudent->guardians()->count());
        $this->assertSame(1, $archivedStudent->enrollments()->count());
    }

    public function test_transfer_requires_destination_school_before_changing_any_data(): void
    {
        [$student, $enrollment] = $this->makeStudentContext();
        $admin = $this->userWithRole('admin');

        try {
            app(StudentDepartureWorkflow::class)->depart($student, [
                'type' => StudentDeparture::TYPE_TRANSFER,
                'effective_date' => '2026-10-01',
                'reason' => 'Pindah domisili.',
            ], $admin);
            $this->fail('Validasi sekolah tujuan seharusnya gagal.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('destination_school', $exception->errors());
        }

        $this->assertDatabaseCount('student_departures', 0);
        $this->assertNull($student->fresh()->deleted_at);
        $this->assertSame('active', $enrollment->fresh()->status);
    }

    public function test_a_failure_midway_rolls_back_the_entire_departure_transaction(): void
    {
        [$student, $enrollment] = $this->makeStudentContext();
        $admin = $this->userWithRole('admin');

        DB::unprepared("CREATE TRIGGER fail_student_departure BEFORE UPDATE ON class_enrollments BEGIN SELECT RAISE(ABORT, 'forced failure'); END");

        try {
            app(StudentDepartureWorkflow::class)->depart($student, [
                'type' => StudentDeparture::TYPE_LEFT,
                'effective_date' => '2026-10-01',
                'reason' => 'Pindah domisili.',
            ], $admin);
            $this->fail('Database failure seharusnya membatalkan workflow.');
        } catch (QueryException) {
            // The trigger fails after the departure row is inserted, exercising transaction rollback.
        }

        $this->assertDatabaseCount('student_departures', 0);
        $this->assertNull(Student::withTrashed()->findOrFail($student->id)->deleted_at);
        $this->assertSame('active', $student->fresh()->status);
        $this->assertSame('active', $enrollment->fresh()->status);
    }

    public function test_non_admin_cannot_depart_a_student(): void
    {
        [$student, $enrollment] = $this->makeStudentContext();
        $teacher = $this->userWithRole('guru');

        try {
            app(StudentDepartureWorkflow::class)->depart($student, [
                'type' => StudentDeparture::TYPE_LEFT,
                'effective_date' => '2026-10-01',
                'reason' => 'Pindah domisili.',
            ], $teacher);
            $this->fail('Role non-admin seharusnya ditolak.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('student_departures', 0);
        $this->assertNull($student->fresh()->deleted_at);
        $this->assertSame('active', $enrollment->fresh()->status);

        $admin = $this->userWithRole('admin');
        $departure = app(StudentDepartureWorkflow::class)->depart($student, [
            'type' => StudentDeparture::TYPE_LEFT,
            'effective_date' => '2026-10-01',
            'reason' => 'Keluar.',
        ], $admin);
        try {
            app(StudentDepartureWorkflow::class)->restoreProfile($departure, $teacher);
            $this->fail('Role non-admin seharusnya tidak dapat memulihkan profil.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertNotNull(Student::withTrashed()->findOrFail($student->id)->deleted_at);
    }

    public function test_restoring_profile_keeps_all_enrollments_inactive_and_records_who_restored_it(): void
    {
        [$student, $enrollment, , $term, , $halaqahMember, , , $classroomTerm] = $this->makeStudentContext();
        $admin = $this->userWithRole('admin');
        $departure = app(StudentDepartureWorkflow::class)->depart($student, [
            'type' => StudentDeparture::TYPE_LEFT,
            'effective_date' => '2026-10-01',
            'reason' => 'Keluar atas permintaan keluarga.',
        ], $admin);

        app(StudentDepartureWorkflow::class)->restoreProfile($departure, $admin);

        $this->assertNull($student->fresh()->deleted_at);
        $this->assertSame('active', $student->fresh()->status);
        $this->assertSame('inactive', $enrollment->fresh()->status);
        $this->assertSame('inactive', $halaqahMember->fresh()->status);
        $this->assertNotNull($departure->fresh()->restored_at);
        $this->assertSame($admin->id, $departure->fresh()->restored_by);

        app(PlacementService::class)->assignClass($term->id, $student->id, $classroomTerm->id);
        app(PlacementService::class)->assignHalaqah($term->id, $student->id, $halaqahMember->tahfidz_halaqah_id);
        $this->assertSame('active', $enrollment->fresh()->status);
        $this->assertSame('active', $halaqahMember->fresh()->status);
    }

    public function test_archived_student_is_hidden_from_teacher_roster_and_guardian_dashboard(): void
    {
        [$student, $enrollment, , $term, , , $teacherUser, $guardianUser, $classroomTerm] = $this->makeStudentContext(true);
        $reportCard = ReportCard::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'class_enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'report_type' => 'diniyyah',
            'status' => 'draft',
        ]);
        DB::table('report_cards')->where('id', $reportCard->id)->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
        $admin = $this->userWithRole('admin');
        app(StudentDepartureWorkflow::class)->depart($student, [
            'type' => StudentDeparture::TYPE_LEFT,
            'effective_date' => '2026-10-01',
            'reason' => 'Keluar.',
        ], $admin);

        $this->actingAs($teacherUser, 'web')
            ->get(route('attendance.edit', ['classroomTerm' => $classroomTerm, 'month' => '2025-07']))
            ->assertOk()
            ->assertDontSee('Santri Uji');
        $this->actingAs($teacherUser, 'web')
            ->putJson(route('attendance.update-single', $classroomTerm), [
                'class_enrollment_id' => $enrollment->id,
                'date' => '2025-07-14',
                'code' => 'H',
            ])
            ->assertUnprocessable();

        $this->actingAs($guardianUser, 'web')
            ->get(route('wali.dashboard'))
            ->assertOk()
            ->assertDontSee('Santri Uji');
        $this->actingAs($guardianUser, 'web')
            ->get(route('report-cards.show', $reportCard))
            ->assertForbidden();

        $this->assertSame(0, ClassEnrollment::query()->forVisibleStudents()->where('id', $enrollment->id)->count());
    }

    public function test_archive_resource_is_only_available_to_admins(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin, 'admin')
            ->get('/admin/student-departures')
            ->assertOk();

        $managementUser = $this->userWithRole('kabag_diniyyah');
        $this->actingAs($managementUser, 'admin')
            ->get('/admin/student-departures')
            ->assertForbidden();

        $this->actingAs($managementUser, 'web');
        $this->assertFalse(StudentDepartureResource::canViewAny());
    }

    /** @return array{Student, ClassEnrollment, StudentAttendance, AcademicTerm, Guardian, TahfidzHalaqahMember, User, User, ClassroomTerm} */
    private function makeStudentContext(bool $withPortalUsers = false): array
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
        $student = Student::create(['name' => 'Santri Uji', 'gender' => 'male', 'nis' => 'UJI-001']);
        $enrollment = ClassEnrollment::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'student_id' => $student->id,
            'roll_number' => 1,
        ]);
        $attendance = StudentAttendance::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'class_enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'attendance_date' => '2025-07-14',
            'status' => StudentAttendance::STATUS_SICK,
            'notes' => 'Data harus tetap ada',
        ]);

        $guardianUser = User::factory()->create();
        $guardianUser->assignRole($this->role('wali_santri'));
        $guardian = Guardian::create(['user_id' => $guardianUser->id, 'name' => 'Wali Keluarga']);
        $student->guardians()->attach($guardian->id, [
            'relationship' => 'ayah',
            'is_primary' => true,
            'can_login' => true,
        ]);

        $halaqah = TahfidzHalaqah::create([
            'academic_term_id' => $term->id,
            'name' => 'Halaqah Uji',
            'status' => 'active',
        ]);
        $halaqahMember = TahfidzHalaqahMember::create([
            'tahfidz_halaqah_id' => $halaqah->id,
            'student_id' => $student->id,
            'class_enrollment_id' => $enrollment->id,
            'joined_at' => '2025-07-14',
            'status' => 'active',
        ]);

        $teacherUser = User::factory()->create();
        $teacherUser->assignRole($this->role('guru'));
        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'name' => 'Guru Wali Uji',
            'gender' => 'male',
            'status' => 'active',
        ]);
        HomeroomAssignment::create([
            'classroom_term_id' => $classroomTerm->id,
            'teacher_id' => $teacher->id,
            'starts_at' => '2025-07-14',
        ]);

        return [$student, $enrollment, $attendance, $term, $guardian, $halaqahMember, $teacherUser, $guardianUser, $classroomTerm];
    }

    private function userWithRole(string $name): User
    {
        $user = User::factory()->create();
        $user->assignRole($this->role($name));

        return $user;
    }

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
}
