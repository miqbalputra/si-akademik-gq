<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\ClassSession;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SessionTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruTafsirJournalMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_simultaneous_tafsir_teacher_sees_menu_on_a_non_teaching_day_and_can_open_the_form(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 08:00:00', 'Asia/Jakarta'));
        $context = $this->makeTafsirTeacher(['Mustawa 2 Ikhwan', 'Mustawa 3 Ikhwan']); // Jadwal Kamis.

        $this->actingAs($context['user'])
            ->get(route('guru.dashboard'))
            ->assertOk()
            ->assertSee('Jurnal Tafsir serentak')
            ->assertSee(route('guru.diniyyah-tafsir-journals.index'), false);

        $this->actingAs($context['user'])
            ->get(route('guru.diniyyah-tafsir-journals.index', ['date' => '2026-08-13']))
            ->assertOk()
            ->assertSee('Mustawa 2 Ikhwan')
            ->assertSee('Mustawa 3 Ikhwan');
    }

    public function test_navigation_hides_tafsir_menu_for_individual_tafsir_and_teachers_without_schedule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 08:00:00', 'Asia/Jakarta'));
        $individual = $this->makeTafsirTeacher(['Mustawa 2 Akhwat']);

        $this->actingAs($individual['user'])
            ->get(route('guru.dashboard'))
            ->assertOk()
            ->assertDontSee('Jurnal Tafsir serentak')
            ->assertDontSee(route('guru.diniyyah-tafsir-journals.index'), false);

        $withoutSchedule = $this->makeTeacherWithoutSchedule();

        $this->actingAs($withoutSchedule)
            ->get(route('guru.dashboard'))
            ->assertOk()
            ->assertDontSee('Jurnal Tafsir serentak')
            ->assertDontSee(route('guru.diniyyah-tafsir-journals.index'), false);
    }

    public function test_navigation_hides_tafsir_menu_for_guru_account_without_linked_teacher(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user->assignRole('guru');

        $this->actingAs($user)
            ->get(route('guru.dashboard'))
            ->assertOk()
            ->assertDontSee('Jurnal Tafsir serentak')
            ->assertDontSee(route('guru.diniyyah-tafsir-journals.index'), false);
    }

    /** @param string[] $classroomNames */
    private function makeTafsirTeacher(array $classroomNames): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => 'Ustadz Tafsir']);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => 'Ustadz Tafsir', 'status' => 'active']);
        $termId = $this->academicTermId();
        $subject = DiniyyahSubject::firstOrCreate(
            ['code' => 'tafsir'],
            ['name' => 'Tafsir Al Quran', 'default_assessment_method' => 'weighted'],
        );
        $session = ClassSession::where('session_name', SessionTimetable::SESSION_TAFSIR)->firstOrFail();

        foreach ($classroomNames as $name) {
            $classroom = Classroom::create(['name' => $name]);
            SessionTimetable::seedForClassroom($classroom);
            $classroomTerm = ClassroomTerm::create([
                'academic_term_id' => $termId,
                'classroom_id' => $classroom->id,
                'name' => $name,
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
            DiniyyahTeachingSchedule::create([
                'diniyyah_teacher_assignment_id' => $assignment->id,
                'class_session_id' => $session->id,
                'day_of_week' => 4,
            ]);
        }

        return compact('user', 'teacher');
    }

    private function makeTeacherWithoutSchedule(): User
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => 'Guru Tanpa Jadwal']);
        $user->assignRole('guru');
        Teacher::create(['user_id' => $user->id, 'name' => 'Guru Tanpa Jadwal', 'status' => 'active']);

        return $user;
    }

    private function academicTermId(): int
    {
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);

        return AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => 'Ganjil',
            'semester' => 'ganjil',
        ])->id;
    }
}
