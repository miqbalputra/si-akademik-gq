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
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruDiniyyahSchedulelessSubstituteTest extends TestCase
{
    use RefreshDatabase;

    private const SENIN = '2026-07-13';

    public function test_scheduleless_male_can_choose_any_subject_and_session_in_ikhwan_only(): void
    {
        $ctx = $this->makeContext('male');
        $this->makeSchedule($ctx['ikhwanAssignment'], dayOfWeek: 1, sessionName: '1');

        $this->actingAs($ctx['substituteUser'])
            ->get(route('guru.diniyyah-substitute-journals.index', [
                'date' => self::SENIN,
                'classroom_term_id' => $ctx['ikhwanTerm']->id,
            ]))
            ->assertOk()
            ->assertSee('Mustawa 1 Ikhwan')
            ->assertSee('Sesi 2')
            ->assertDontSee('Mustawa 1 Akhwat');

        // Assignment asli punya jadwal hanya pada sesi 1. Guru pengganti tanpa
        // jadwal tetap boleh mengisi sesi 2 di kelas Ikhwan.
        $this->actingAs($ctx['substituteUser'])
            ->post(route('guru.diniyyah-substitute-journals.store'), [
                'diniyyah_teacher_assignment_id' => $ctx['ikhwanAssignment']->id,
                'classroom_term_id' => $ctx['ikhwanTerm']->id,
                'date' => self::SENIN,
                'session_hour' => '2',
                'material' => 'Materi pengganti Ikhwan',
                'absences' => [],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('diniyyah_class_journals', [
            'diniyyah_teacher_assignment_id' => $ctx['ikhwanAssignment']->id,
            'substitute_teacher_id' => $ctx['substituteTeacher']->id,
            'session_hour' => '2',
        ]);
    }

    public function test_scheduleless_male_cannot_store_journal_for_akhwat(): void
    {
        $ctx = $this->makeContext('male');

        $this->actingAs($ctx['substituteUser'])
            ->post(route('guru.diniyyah-substitute-journals.store'), [
                'diniyyah_teacher_assignment_id' => $ctx['akhwatAssignment']->id,
                'classroom_term_id' => $ctx['akhwatTerm']->id,
                'date' => self::SENIN,
                'session_hour' => '1',
                'material' => 'Tidak boleh',
                'absences' => [],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('diniyyah_class_journals', 0);
    }

    public function test_scheduleless_female_is_limited_to_akhwat(): void
    {
        $ctx = $this->makeContext('female');

        $this->actingAs($ctx['substituteUser'])
            ->get(route('guru.diniyyah-substitute-journals.index', [
                'date' => self::SENIN,
            ]))
            ->assertOk()
            ->assertSee('Mustawa 1 Akhwat')
            ->assertDontSee('Mustawa 1 Ikhwan');

        $this->actingAs($ctx['substituteUser'])
            ->post(route('guru.diniyyah-substitute-journals.store'), [
                'diniyyah_teacher_assignment_id' => $ctx['akhwatAssignment']->id,
                'classroom_term_id' => $ctx['akhwatTerm']->id,
                'date' => self::SENIN,
                'session_hour' => '1',
                'material' => 'Materi pengganti Akhwat',
                'absences' => [],
            ])
            ->assertSessionHas('success');
    }

    public function test_teacher_with_own_schedule_keeps_original_schedule_restriction(): void
    {
        $ctx = $this->makeContext('male');
        $this->makeSchedule($ctx['ikhwanAssignment'], dayOfWeek: 1, sessionName: '1');

        // Jadwal milik guru pengganti sendiri membuatnya tetap memakai mode
        // lama, walaupun ia sedang menggantikan guru lain.
        $ownSubject = DiniyyahSubject::create([
            'code' => 'akidah-akhwat',
            'name' => 'Akidah Akhwat',
            'default_assessment_method' => 'weighted',
            'is_active' => true,
        ]);
        $ownClassSubject = DiniyyahClassSubject::create([
            'classroom_term_id' => $ctx['akhwatTerm']->id,
            'subject_id' => $ownSubject->id,
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
        ]);
        $ownAssignment = DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $ownClassSubject->id,
            'teacher_id' => $ctx['substituteTeacher']->id,
            'assignment_role' => 'primary',
        ]);
        $this->makeSchedule($ownAssignment, dayOfWeek: 1, sessionName: '1');

        $this->actingAs($ctx['substituteUser'])
            ->post(route('guru.diniyyah-substitute-journals.store'), [
                'diniyyah_teacher_assignment_id' => $ctx['ikhwanAssignment']->id,
                'classroom_term_id' => $ctx['ikhwanTerm']->id,
                'date' => self::SENIN,
                'session_hour' => '2',
                'material' => 'Harus ditolak',
                'absences' => [],
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('diniyyah_class_journals', 0);
    }

    private function makeContext(string $substituteGender): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);

        $substituteUser = User::factory()->create(['name' => 'Guru Pengganti']);
        $substituteUser->assignRole('guru');
        $substituteTeacher = Teacher::create([
            'user_id' => $substituteUser->id,
            'name' => 'Guru Pengganti',
            'gender' => $substituteGender,
        ]);

        $originalUser = User::factory()->create(['name' => 'Guru Asli']);
        $originalUser->assignRole('guru');
        $originalTeacher = Teacher::create([
            'user_id' => $originalUser->id,
            'name' => 'Guru Asli',
            'gender' => 'male',
        ]);

        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => 'Ganjil',
            'semester' => 'ganjil',
            'is_active' => true,
        ]);

        $ikhwan = Classroom::create([
            'name' => 'Mustawa 1 Ikhwan',
            'gender_group' => 'male',
            'is_active' => true,
        ]);
        $akhwat = Classroom::create([
            'name' => 'Mustawa 1 Akhwat',
            'gender_group' => 'female',
            'is_active' => true,
        ]);
        SessionTimetable::seedForClassroom($ikhwan);
        SessionTimetable::seedForClassroom($akhwat);

        $ikhwanTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $ikhwan->id,
            'name' => $ikhwan->name,
            'status' => 'active',
        ]);
        $akhwatTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $akhwat->id,
            'name' => $akhwat->name,
            'status' => 'active',
        ]);

        $ikhwanSubject = DiniyyahSubject::create([
            'code' => 'fiqih-ikhwan',
            'name' => 'Fiqih Ikhwan',
            'default_assessment_method' => 'weighted',
            'is_active' => true,
        ]);
        $akhwatSubject = DiniyyahSubject::create([
            'code' => 'fiqih-akhwat',
            'name' => 'Fiqih Akhwat',
            'default_assessment_method' => 'weighted',
            'is_active' => true,
        ]);

        $ikhwanClassSubject = DiniyyahClassSubject::create([
            'classroom_term_id' => $ikhwanTerm->id,
            'subject_id' => $ikhwanSubject->id,
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
        ]);
        $akhwatClassSubject = DiniyyahClassSubject::create([
            'classroom_term_id' => $akhwatTerm->id,
            'subject_id' => $akhwatSubject->id,
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
        ]);

        return [
            'substituteUser' => $substituteUser,
            'substituteTeacher' => $substituteTeacher,
            'ikhwanTerm' => $ikhwanTerm,
            'akhwatTerm' => $akhwatTerm,
            'akhwatSubject' => $akhwatSubject,
            'ikhwanAssignment' => DiniyyahTeacherAssignment::create([
                'diniyyah_class_subject_id' => $ikhwanClassSubject->id,
                'teacher_id' => $originalTeacher->id,
                'assignment_role' => 'primary',
            ]),
            'akhwatAssignment' => DiniyyahTeacherAssignment::create([
                'diniyyah_class_subject_id' => $akhwatClassSubject->id,
                'teacher_id' => $originalTeacher->id,
                'assignment_role' => 'primary',
            ]),
        ];
    }

    private function makeSchedule(DiniyyahTeacherAssignment $assignment, int $dayOfWeek, string $sessionName): void
    {
        $session = ClassSession::where('session_name', $sessionName)->firstOrFail();

        DiniyyahTeachingSchedule::create([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'class_session_id' => $session->id,
            'day_of_week' => $dayOfWeek,
        ]);
    }
}
