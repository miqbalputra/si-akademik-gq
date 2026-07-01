<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolEvent;
use App\Models\SchoolEventResponse;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolEventRecapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_open_school_event_recap_page_with_father_and_mother_statistics(): void
    {
        [$admin, $event] = $this->makeEventContext();

        $this->actingAs($admin)
            ->get('/admin/school-events/'.$event->id.'/recap')
            ->assertOk()
            ->assertSee('Rekap Event Sekolah')
            ->assertSee('Pertemuan Wali Mustawa 3')
            ->assertSee('Rekap Bapak')
            ->assertSee('Rekap Ibu')
            ->assertSee('Bapak Ahmad')
            ->assertSee('Ibu Ahmad')
            ->assertSee('Sudah Respon')
            ->assertSee('Belum Respon');
    }

    public function test_admin_can_export_school_event_recap_csv(): void
    {
        [$admin, $event] = $this->makeEventContext();

        $response = $this->actingAs($admin)
            ->get(route('school-events.recap.export', $event));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Rekap Event Sekolah', $content);
        $this->assertStringContainsString('Pertemuan Wali Mustawa 3', $content);
        $this->assertStringContainsString('Bapak Ahmad', $content);
        $this->assertStringContainsString('Ibu Ahmad', $content);
        $this->assertStringContainsString('Hadir', $content);
        $this->assertStringContainsString('Belum Konfirmasi', $content);
    }

    public function test_admin_can_export_filtered_pending_follow_up_csv(): void
    {
        [$admin, $event] = $this->makeEventContext();

        $response = $this->actingAs($admin)
            ->get(route('school-events.recap.export', [
                'event' => $event,
                'status' => 'pending',
            ]));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('"Filter Status",pending', $content);
        $this->assertStringContainsString('Ibu Ahmad', $content);
        $this->assertStringNotContainsString('Bapak Ahmad', $content);
    }

    /** @return array{User, SchoolEvent} */
    private function makeEventContext(): array
    {
        $admin = User::factory()->create(['name' => 'Admin']);
        $admin->assignRole('admin');

        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => 'Semester Ganjil',
            'semester' => 'ganjil',
            'starts_at' => today()->subMonth()->toDateString(),
            'ends_at' => today()->addMonths(2)->toDateString(),
        ]);
        $classroom = Classroom::create([
            'name' => 'Mustawa 3 Ikhwan',
            'level_name' => 'Mustawa 3',
            'gender_group' => 'male',
        ]);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => 'Mustawa 3 Ikhwan',
        ]);
        $student = Student::create([
            'name' => 'Ahmad',
            'gender' => 'male',
            'nis' => '00991',
        ]);
        ClassEnrollment::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);

        $father = Guardian::create([
            'name' => 'Bapak Ahmad',
            'gender' => 'male',
            'whatsapp' => '081200000001',
        ]);
        $mother = Guardian::create([
            'name' => 'Ibu Ahmad',
            'gender' => 'female',
            'whatsapp' => '081200000002',
        ]);

        $father->students()->attach($student->id, ['relationship' => 'father', 'can_login' => true]);
        $mother->students()->attach($student->id, ['relationship' => 'mother', 'can_login' => false]);

        $event = SchoolEvent::create([
            'school_id' => $school->id,
            'academic_term_id' => $term->id,
            'title' => 'Pertemuan Wali Mustawa 3',
            'event_type' => 'meeting',
            'target_scope' => 'level_gender',
            'target_level_name' => 'Mustawa 3',
            'target_gender_group' => 'male',
            'starts_on' => today()->addDays(5)->toDateString(),
            'ends_on' => today()->addDays(5)->toDateString(),
            'show_to_teachers' => true,
            'show_to_guardians' => true,
        ]);

        SchoolEventResponse::create([
            'school_event_id' => $event->id,
            'guardian_id' => $father->id,
            'attendance_status' => 'attending',
            'notes' => 'Siap hadir.',
            'responded_at' => now(),
        ]);

        return [$admin, $event];
    }
}
