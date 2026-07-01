<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolEvent;
use App\Models\SchoolEventResponse;
use App\Models\SchoolHoliday;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolEventVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_dashboard_shows_visible_school_events(): void
    {
        [$guardianUser, $term] = $this->makeGuardianContext();

        SchoolEvent::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'title' => 'Outdoor Akhir Pekan',
            'event_type' => 'outdoor',
            'starts_on' => today()->addDays(2)->toDateString(),
            'ends_on' => today()->addDays(2)->toDateString(),
            'location' => 'Bumi Perkemahan',
            'description' => 'Kegiatan luar kelas untuk santri.',
            'show_to_teachers' => true,
            'show_to_guardians' => true,
        ]);

        SchoolEvent::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'title' => 'Rapat Internal Guru',
            'event_type' => 'meeting',
            'starts_on' => today()->addDays(3)->toDateString(),
            'ends_on' => today()->addDays(3)->toDateString(),
            'show_to_teachers' => true,
            'show_to_guardians' => false,
        ]);

        $this->actingAs($guardianUser)
            ->get(route('wali.dashboard'))
            ->assertOk()
            ->assertSee('Pengingat 7 Hari ke Depan')
            ->assertSee('Agenda Sekolah untuk Wali Santri')
            ->assertSee('Outdoor Akhir Pekan')
            ->assertDontSee('Rapat Internal Guru');
    }

    public function test_teacher_dashboard_shows_visible_school_events(): void
    {
        [$teacherUser, $term] = $this->makeTeacherContext();

        SchoolEvent::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'title' => 'Ujian Tengah Semester',
            'event_type' => 'exam',
            'starts_on' => today()->addDays(1)->toDateString(),
            'ends_on' => today()->addDays(2)->toDateString(),
            'description' => 'Jadwal ujian tengah semester.',
            'show_to_teachers' => true,
            'show_to_guardians' => false,
        ]);

        SchoolEvent::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'title' => 'Pengambilan Seragam',
            'event_type' => 'general',
            'starts_on' => today()->addDays(4)->toDateString(),
            'ends_on' => today()->addDays(4)->toDateString(),
            'show_to_teachers' => false,
            'show_to_guardians' => true,
        ]);

        $this->actingAs($teacherUser)
            ->get(route('guru.diniyyah-scores.index'))
            ->assertOk()
            ->assertSee('Pengingat 7 Hari ke Depan')
            ->assertSee('Agenda Sekolah untuk Guru')
            ->assertSee('Ujian Tengah Semester')
            ->assertSee('Prioritas Tinggi')
            ->assertDontSee('Pengambilan Seragam');
    }

    public function test_dashboard_alerts_include_upcoming_school_holiday(): void
    {
        [$guardianUser, $term] = $this->makeGuardianContext();

        SchoolHoliday::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'holiday_date' => today()->addDays(1)->toDateString(),
            'title' => 'Libur Persiapan Outdoor',
            'description' => 'Sekolah libur untuk persiapan kegiatan outdoor.',
        ]);

        $this->actingAs($guardianUser)
            ->get(route('wali.dashboard'))
            ->assertOk()
            ->assertSee('Libur Persiapan Outdoor')
            ->assertSee('1 hari lagi');
    }

    public function test_teacher_calendar_shows_events_and_holidays(): void
    {
        [$teacherUser, $term] = $this->makeTeacherContext();

        SchoolEvent::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'title' => 'Outdoor Guru',
            'event_type' => 'outdoor',
            'starts_on' => today()->startOfMonth()->toDateString(),
            'ends_on' => today()->startOfMonth()->toDateString(),
            'show_to_teachers' => true,
            'show_to_guardians' => false,
        ]);

        \App\Models\SchoolHoliday::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'holiday_date' => today()->startOfMonth()->toDateString(),
            'title' => 'Libur Guru',
        ]);

        $this->actingAs($teacherUser)
            ->get(route('guru.calendar', ['term' => $term->id, 'month' => today()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Kalender Guru')
            ->assertSee('Outdoor Guru')
            ->assertSee('Libur Guru')
            ->assertSee('Senin');
    }

    public function test_guardian_calendar_shows_only_guardian_visible_events(): void
    {
        [$guardianUser, $term] = $this->makeGuardianContext();

        SchoolEvent::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'title' => 'Outdoor Keluarga',
            'event_type' => 'outdoor',
            'starts_on' => today()->startOfMonth()->toDateString(),
            'ends_on' => today()->startOfMonth()->toDateString(),
            'show_to_teachers' => true,
            'show_to_guardians' => true,
        ]);

        SchoolEvent::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'title' => 'Briefing Guru',
            'event_type' => 'meeting',
            'starts_on' => today()->startOfMonth()->toDateString(),
            'ends_on' => today()->startOfMonth()->toDateString(),
            'show_to_teachers' => true,
            'show_to_guardians' => false,
        ]);

        $this->actingAs($guardianUser)
            ->get(route('wali.calendar', ['term' => $term->id, 'month' => today()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Kalender Wali Santri')
            ->assertSee('Outdoor Keluarga')
            ->assertDontSee('Briefing Guru');
    }

    public function test_guardian_calendar_can_filter_only_school_holidays(): void
    {
        [$guardianUser, $term] = $this->makeGuardianContext();

        SchoolHoliday::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'holiday_date' => today()->startOfMonth()->toDateString(),
            'title' => 'Libur Khusus',
        ]);

        SchoolEvent::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'title' => 'Outdoor Keluarga',
            'event_type' => 'outdoor',
            'starts_on' => today()->startOfMonth()->toDateString(),
            'ends_on' => today()->startOfMonth()->toDateString(),
            'show_to_teachers' => true,
            'show_to_guardians' => true,
        ]);

        $this->actingAs($guardianUser)
            ->get(route('wali.calendar', ['term' => $term->id, 'month' => today()->format('Y-m'), 'category' => 'holiday']))
            ->assertOk()
            ->assertSee('Filter Libur Sekolah')
            ->assertSee('Libur Khusus')
            ->assertDontSee('Outdoor Keluarga');
    }

    public function test_teacher_calendar_can_filter_exam_category(): void
    {
        [$teacherUser, $term] = $this->makeTeacherContext();

        SchoolEvent::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'title' => 'Ujian Tengah Semester',
            'event_type' => 'exam',
            'starts_on' => today()->startOfMonth()->toDateString(),
            'ends_on' => today()->startOfMonth()->toDateString(),
            'show_to_teachers' => true,
            'show_to_guardians' => false,
        ]);

        SchoolEvent::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'title' => 'Outdoor Guru',
            'event_type' => 'outdoor',
            'starts_on' => today()->startOfMonth()->toDateString(),
            'ends_on' => today()->startOfMonth()->toDateString(),
            'show_to_teachers' => true,
            'show_to_guardians' => false,
        ]);

        $this->actingAs($teacherUser)
            ->get(route('guru.calendar', ['term' => $term->id, 'month' => today()->format('Y-m'), 'category' => 'exam']))
            ->assertOk()
            ->assertSee('Filter Ujian')
            ->assertSee('Ujian Tengah Semester')
            ->assertDontSee('Outdoor Guru');
    }

    public function test_guardian_dashboard_hides_event_for_other_classroom(): void
    {
        [$guardianUser, $term] = $this->makeGuardianContext();

        $currentClassroomTerm = ClassroomTerm::query()
            ->where('academic_term_id', $term->id)
            ->firstOrFail();
        $otherClassroomTerm = $this->createAdditionalClassroomTerm($term, 'Mustawa 2 Banin');

        $visibleEvent = SchoolEvent::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'title' => 'Outdoor Kelas Sendiri',
            'event_type' => 'outdoor',
            'target_scope' => 'classes',
            'starts_on' => today()->addDays(2)->toDateString(),
            'ends_on' => today()->addDays(2)->toDateString(),
            'show_to_teachers' => true,
            'show_to_guardians' => true,
        ]);
        $visibleEvent->targetClassroomTerms()->sync([$currentClassroomTerm->id]);

        $hiddenEvent = SchoolEvent::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'title' => 'Outdoor Kelas Lain',
            'event_type' => 'outdoor',
            'target_scope' => 'classes',
            'starts_on' => today()->addDays(3)->toDateString(),
            'ends_on' => today()->addDays(3)->toDateString(),
            'show_to_teachers' => true,
            'show_to_guardians' => true,
        ]);
        $hiddenEvent->targetClassroomTerms()->sync([$otherClassroomTerm->id]);

        $this->actingAs($guardianUser)
            ->get(route('wali.dashboard'))
            ->assertOk()
            ->assertSee('Outdoor Kelas Sendiri')
            ->assertDontSee('Outdoor Kelas Lain');
    }

    public function test_teacher_calendar_hides_event_for_other_classroom(): void
    {
        [$teacherUser, $term] = $this->makeTeacherContext();

        $currentClassroomTerm = ClassroomTerm::query()
            ->where('academic_term_id', $term->id)
            ->firstOrFail();
        $otherClassroomTerm = $this->createAdditionalClassroomTerm($term, 'Mustawa 2 Ikhwan');

        $visibleEvent = SchoolEvent::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'title' => 'Ujian Kelas Sendiri',
            'event_type' => 'exam',
            'target_scope' => 'classes',
            'starts_on' => today()->startOfMonth()->toDateString(),
            'ends_on' => today()->startOfMonth()->toDateString(),
            'show_to_teachers' => true,
            'show_to_guardians' => false,
        ]);
        $visibleEvent->targetClassroomTerms()->sync([$currentClassroomTerm->id]);

        $hiddenEvent = SchoolEvent::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'title' => 'Ujian Kelas Lain',
            'event_type' => 'exam',
            'target_scope' => 'classes',
            'starts_on' => today()->startOfMonth()->toDateString(),
            'ends_on' => today()->startOfMonth()->toDateString(),
            'show_to_teachers' => true,
            'show_to_guardians' => false,
        ]);
        $hiddenEvent->targetClassroomTerms()->sync([$otherClassroomTerm->id]);

        $this->actingAs($teacherUser)
            ->get(route('guru.calendar', ['term' => $term->id, 'month' => today()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Ujian Kelas Sendiri')
            ->assertDontSee('Ujian Kelas Lain');
    }

    public function test_guardian_dashboard_shows_level_gender_targeted_event(): void
    {
        [$guardianUser, $term] = $this->makeGuardianContext();

        SchoolEvent::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'title' => 'Ujian Mustawa 1 Ikhwan',
            'event_type' => 'exam',
            'target_scope' => 'level_gender',
            'target_level_name' => 'Mustawa 1',
            'target_gender_group' => 'male',
            'starts_on' => today()->addDays(2)->toDateString(),
            'ends_on' => today()->addDays(2)->toDateString(),
            'show_to_teachers' => true,
            'show_to_guardians' => true,
        ]);

        SchoolEvent::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'title' => 'Ujian Mustawa 1 Akhwat',
            'event_type' => 'exam',
            'target_scope' => 'level_gender',
            'target_level_name' => 'Mustawa 1',
            'target_gender_group' => 'female',
            'starts_on' => today()->addDays(2)->toDateString(),
            'ends_on' => today()->addDays(2)->toDateString(),
            'show_to_teachers' => true,
            'show_to_guardians' => true,
        ]);

        $this->actingAs($guardianUser)
            ->get(route('wali.dashboard'))
            ->assertOk()
            ->assertSee('Ujian Mustawa 1 Ikhwan')
            ->assertDontSee('Ujian Mustawa 1 Akhwat');
    }

    public function test_teacher_calendar_shows_gender_targeted_event_for_matching_classroom(): void
    {
        [$teacherUser, $term] = $this->makeTeacherContext();

        SchoolEvent::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'title' => 'Pertemuan Semua Ikhwan',
            'event_type' => 'meeting',
            'target_scope' => 'gender',
            'target_gender_group' => 'male',
            'starts_on' => today()->startOfMonth()->toDateString(),
            'ends_on' => today()->startOfMonth()->toDateString(),
            'show_to_teachers' => true,
            'show_to_guardians' => false,
        ]);

        SchoolEvent::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'title' => 'Pertemuan Semua Akhwat',
            'event_type' => 'meeting',
            'target_scope' => 'gender',
            'target_gender_group' => 'female',
            'starts_on' => today()->startOfMonth()->toDateString(),
            'ends_on' => today()->startOfMonth()->toDateString(),
            'show_to_teachers' => true,
            'show_to_guardians' => false,
        ]);

        $this->actingAs($teacherUser)
            ->get(route('guru.calendar', ['term' => $term->id, 'month' => today()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Pertemuan Semua Ikhwan')
            ->assertSee('Target: Ikhwan')
            ->assertDontSee('Pertemuan Semua Akhwat');
    }

    public function test_guardian_can_submit_event_attendance_response(): void
    {
        [$guardianUser, $term] = $this->makeGuardianContext();

        $event = SchoolEvent::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'title' => 'Pertemuan Wali Mustawa 1',
            'event_type' => 'meeting',
            'target_scope' => 'level_gender',
            'target_level_name' => 'Mustawa 1',
            'target_gender_group' => 'male',
            'starts_on' => today()->addDays(5)->toDateString(),
            'ends_on' => today()->addDays(5)->toDateString(),
            'show_to_teachers' => true,
            'show_to_guardians' => true,
        ]);

        $this->actingAs($guardianUser)
            ->post(route('wali.events.response', $event), [
                'attendance_status' => 'attending',
                'notes' => 'InsyaAllah hadir.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('school_event_responses', [
            'school_event_id' => $event->id,
            'attendance_status' => 'attending',
            'notes' => 'InsyaAllah hadir.',
        ]);
    }

    public function test_guardian_cannot_submit_response_for_unrelated_event(): void
    {
        [$guardianUser, $term] = $this->makeGuardianContext();

        $event = SchoolEvent::create([
            'school_id' => $term->academicYear->school_id,
            'academic_term_id' => $term->id,
            'title' => 'Pertemuan Akhwat',
            'event_type' => 'meeting',
            'target_scope' => 'gender',
            'target_gender_group' => 'female',
            'starts_on' => today()->addDays(5)->toDateString(),
            'ends_on' => today()->addDays(5)->toDateString(),
            'show_to_teachers' => true,
            'show_to_guardians' => true,
        ]);

        $this->actingAs($guardianUser)
            ->post(route('wali.events.response', $event), [
                'attendance_status' => 'attending',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('school_event_responses', [
            'school_event_id' => $event->id,
        ]);
    }

    /** @return array{User, AcademicTerm} */
    private function makeGuardianContext(): array
    {
        Role::firstOrCreate(['name' => 'wali_santri', 'guard_name' => 'web']);

        $guardianUser = User::factory()->create(['name' => 'Wali Santri']);
        $guardianUser->assignRole('wali_santri');
        $guardian = Guardian::create([
            'user_id' => $guardianUser->id,
            'name' => 'Wali Santri',
        ]);

        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => 'Semester Genap',
            'semester' => 'genap',
            'starts_at' => today()->subMonth()->toDateString(),
            'ends_at' => today()->addMonths(3)->toDateString(),
        ]);
        $classroom = Classroom::create([
            'name' => 'Mustawa 1 Ikhwan',
            'level_name' => 'Mustawa 1',
            'gender_group' => 'male',
        ]);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => 'Mustawa 1 Ikhwan',
        ]);
        $student = Student::create(['name' => 'Anak Sendiri', 'gender' => 'male', 'nis' => '001']);
        $guardian->students()->attach($student->id, ['relationship' => 'father', 'can_login' => true]);
        ClassEnrollment::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);

        return [$guardianUser, $term->load('academicYear')];
    }

    /** @return array{User, AcademicTerm} */
    private function makeTeacherContext(): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);

        $teacherUser = User::factory()->create(['name' => 'Guru']);
        $teacherUser->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'name' => 'Guru Fiqih']);

        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => 'Semester Genap',
            'semester' => 'genap',
            'starts_at' => today()->subMonth()->toDateString(),
            'ends_at' => today()->addMonths(3)->toDateString(),
        ]);
        $classroom = Classroom::create([
            'name' => 'Mustawa 1 Ikhwan',
            'level_name' => 'Mustawa 1',
            'gender_group' => 'male',
        ]);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => 'Mustawa 1 Ikhwan',
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
        DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $classSubject->id,
            'teacher_id' => $teacher->id,
            'assignment_role' => 'primary',
        ]);
        DiniyyahAssessmentSet::create([
            'diniyyah_class_subject_id' => $classSubject->id,
            'title' => 'Fiqih',
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
            'status' => 'active',
        ]);

        return [$teacherUser, $term->load('academicYear')];
    }

    private function createAdditionalClassroomTerm(AcademicTerm $term, string $classroomName): ClassroomTerm
    {
        $classroom = Classroom::create([
            'name' => $classroomName,
            'level_name' => str_contains($classroomName, 'Mustawa 2') ? 'Mustawa 2' : 'Mustawa Tambahan',
            'gender_group' => 'male',
            'sort_order' => 99,
            'is_active' => true,
        ]);

        return ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => $classroomName,
        ]);
    }
}
