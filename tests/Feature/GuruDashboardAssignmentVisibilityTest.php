<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Visibilitas penugasan diniyyah di dashboard guru.
 *
 * Kronik bug Yusuf: penugasan dgn starts_at masa depan disembunyikan dashboard
 * oleh filter starts_at <= today. Dashboard kini menampilkan penugasan aktif +
 * yang akan datang, tetap menyembunyikan yang sudah berakhir (ends_at < hari ini,
 * WIB). Guru tanpa Teacher ter-link user melihat kosong — diuji terpisah.
 */
class GuruDashboardAssignmentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_assignment_with_future_starts_at(): void
    {
        $ctx = $this->makeContext();

        DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $ctx['classSubject']->id,
            'teacher_id' => $ctx['teacher']->id,
            'assignment_role' => 'primary',
            'starts_at' => now()->addDays(30)->toDateString(),
            'ends_at' => null,
        ]);

        $response = $this->actingAs($ctx['user'])->get(route('guru.dashboard'));

        $response->assertOk();
        // Widget "Guru Diniyyah" + label "Jadwal Mengajar" hanya muncul bila
        // ada assignment aktif/akan-datang. Sebelum fix, starts_at masa depan
        // menyembunyikan widget ini.
        $response->assertSee('Guru Diniyyah');
        $response->assertSee('Jadwal Mengajar');
    }

    public function test_dashboard_hides_ended_assignment(): void
    {
        $ctx = $this->makeContext();

        DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $ctx['classSubject']->id,
            'teacher_id' => $ctx['teacher']->id,
            'assignment_role' => 'primary',
            'starts_at' => null,
            'ends_at' => now()->subDays(2)->toDateString(),
        ]);

        $response = $this->actingAs($ctx['user'])->get(route('guru.dashboard'));

        $response->assertOk();
        // ends_at di masa lalu → penugasan berakhir → widget diniyyah tersembunyi.
        $response->assertDontSee('Jadwal Mengajar');
    }

    public function test_dashboard_shows_null_dates_assignment(): void
    {
        $ctx = $this->makeContext();

        DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $ctx['classSubject']->id,
            'teacher_id' => $ctx['teacher']->id,
            'assignment_role' => 'primary',
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $response = $this->actingAs($ctx['user'])->get(route('guru.dashboard'));

        $response->assertOk();
        $response->assertSee('Jadwal Mengajar');
    }

    public function test_dashboard_empty_when_user_has_no_linked_teacher(): void
    {
        $ctx = $this->makeContext();

        // User terlink Teacher A, tapi assignment ditugaskan ke Teacher B (user_id null).
        $unlinked = Teacher::create([
            'user_id' => null,
            'name' => 'Guru Lain',
            'niy' => 'N999',
            'status' => 'active',
        ]);
        DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $ctx['classSubject']->id,
            'teacher_id' => $unlinked->id,
            'assignment_role' => 'primary',
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $response = $this->actingAs($ctx['user'])->get(route('guru.dashboard'));

        $response->assertOk();
        // teacher_id tersimpan ≠ Teacher ter-link akun user → dashboard kosong.
        $response->assertDontSee('Jadwal Mengajar');
    }

    private function makeContext(): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => 'Guru A']);
        $user->assignRole('guru');
        $teacher = Teacher::create([
            'user_id' => $user->id,
            'name' => 'Guru A',
            'niy' => 'N001',
            'status' => 'active',
        ]);

        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => 'Genap',
            'semester' => 'genap',
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-12-31',
            'is_active' => true,
        ]);
        $classroom = Classroom::create(['name' => 'Mustawa 5']);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => 'Mustawa 5 Ikhwan',
        ]);
        $subject = DiniyyahSubject::create([
            'code' => 'akidah',
            'name' => 'Akidah Akhlak',
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

        return [
            'user' => $user,
            'teacher' => $teacher,
            'term' => $term,
            'classroomTerm' => $classroomTerm,
            'classSubject' => $classSubject,
        ];
    }
}