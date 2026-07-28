<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SessionTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Matrix jam sesi diniyyah per gender + hari (ikhwan/akhwat, Senin-Jum'at).
 * Sumber: jadwal_mustawa_ikhwan.md & jadwal_mustawa_akhwat_2026_2027.md.
 * Matrix di-seed oleh migration 2026_07_28_000003 (jalan via RefreshDatabase).
 */
class SessionTimetableTest extends TestCase
{
    use RefreshDatabase;

    public function test_label_for_tafsir_and_numeric(): void
    {
        $this->assertSame('Sesi Lainnya (Tafsir)', SessionTimetable::label('tafsir'));
        $this->assertSame('Sesi 1', SessionTimetable::label('1'));
        $this->assertSame('Sesi 2', SessionTimetable::label('2'));
    }

    public function test_slots_for_ikhwan_senin_uses_early_times(): void
    {
        $slots = SessionTimetable::slotsFor(SessionTimetable::GROUP_IKHWAN, 1);

        $this->assertCount(2, $slots);
        $this->assertSame('1', $slots[0]->session_name);
        $this->assertSame('07:40', $this->hm($slots[0]->starts_at));
        $this->assertSame('08:10', $this->hm($slots[0]->ends_at));
        $this->assertSame('2', $slots[1]->session_name);
        $this->assertSame('08:10', $this->hm($slots[1]->starts_at));
        $this->assertSame('08:40', $this->hm($slots[1]->ends_at));
    }

    public function test_slots_for_akhwat_senin_uses_late_times(): void
    {
        $slots = SessionTimetable::slotsFor(SessionTimetable::GROUP_AKHWAT, 1);

        $this->assertCount(2, $slots);
        $this->assertSame('1', $slots[0]->session_name);
        $this->assertSame('10:30', $this->hm($slots[0]->starts_at));
        $this->assertSame('11:00', $this->hm($slots[0]->ends_at));
        $this->assertSame('11:00', $this->hm($slots[1]->starts_at));
        $this->assertSame('11:30', $this->hm($slots[1]->ends_at));
    }

    public function test_slots_for_kamis_tafsir_appears_first(): void
    {
        $slots = SessionTimetable::slotsFor(SessionTimetable::GROUP_IKHWAN, 4);

        $this->assertCount(3, $slots);
        $this->assertSame('tafsir', $slots[0]->session_name);
        $this->assertSame('09:50', $this->hm($slots[0]->starts_at));
        $this->assertSame('1', $slots[1]->session_name);
        $this->assertSame('2', $slots[2]->session_name);
    }

    public function test_slots_for_sabtu_is_empty(): void
    {
        $this->assertTrue(SessionTimetable::slotsFor(SessionTimetable::GROUP_IKHWAN, 6)->isEmpty());
        $this->assertTrue(SessionTimetable::slotsFor(SessionTimetable::GROUP_AKHWAT, 7)->isEmpty());
    }

    public function test_resolve_returns_times_or_null(): void
    {
        $this->assertSame(
            ['starts_at' => '07:40:00', 'ends_at' => '08:10:00'],
            SessionTimetable::resolve(SessionTimetable::GROUP_IKHWAN, 1, '1'),
        );
        $this->assertNull(SessionTimetable::resolve(SessionTimetable::GROUP_IKHWAN, 6, '1'));
        $this->assertNull(SessionTimetable::resolve(SessionTimetable::GROUP_IKHWAN, 1, 'nonexistent'));
    }

    public function test_gender_for_falls_back_to_name_when_column_unset(): void
    {
        $termId = $this->academicTermId();

        $ikhwanTerm = ClassroomTerm::create([
            'academic_term_id' => $termId,
            'name' => 'Mustawa 1 Ikhwan',
            'classroom_id' => Classroom::create(['name' => 'Mustawa 1'])->id, // gender_group default 'mixed'
        ]);

        $akhwatTerm = ClassroomTerm::create([
            'academic_term_id' => $termId,
            'name' => 'Mustawa 1 Akhwat',
            'classroom_id' => Classroom::create(['name' => 'Mustawa 1 A'])->id,
        ]);

        $this->assertSame(SessionTimetable::GROUP_IKHWAN, SessionTimetable::genderFor($ikhwanTerm));
        $this->assertSame(SessionTimetable::GROUP_AKHWAT, SessionTimetable::genderFor($akhwatTerm));
    }

    public function test_gender_for_prefers_classroom_column_over_name(): void
    {
        // Kolom gender_group 'akhwat' menang meski nama mengandung 'Ikhwan'.
        $term = ClassroomTerm::create([
            'academic_term_id' => $this->academicTermId(),
            'name' => 'Mustawa 1 Ikhwan',
            'classroom_id' => Classroom::create(['name' => 'M1', 'gender_group' => 'akhwat'])->id,
        ]);

        $this->assertSame(SessionTimetable::GROUP_AKHWAT, SessionTimetable::genderFor($term));
    }

    public function test_gender_for_maps_prod_male_female_values(): void
    {
        // Prod memakai gender_group 'male'/'female' (bukan ikhwan/akhwat).
        $ikhwan = ClassroomTerm::create([
            'academic_term_id' => $this->academicTermId(),
            'name' => 'Mustawa 1 Ikhwan',
            'classroom_id' => Classroom::create(['name' => 'Mustawa 1 Ikhwan', 'gender_group' => 'male'])->id,
        ]);
        $akhwat = ClassroomTerm::create([
            'academic_term_id' => $this->academicTermId(),
            'name' => 'Mustawa 1 Akhwat',
            'classroom_id' => Classroom::create(['name' => 'Mustawa 1 Akhwat', 'gender_group' => 'female'])->id,
        ]);

        $this->assertSame(SessionTimetable::GROUP_IKHWAN, SessionTimetable::genderFor($ikhwan));
        $this->assertSame(SessionTimetable::GROUP_AKHWAT, SessionTimetable::genderFor($akhwat));
    }

    public function test_store_persists_session_time_snapshot(): void
    {
        $ctx = $this->makeContext(SessionTimetable::GROUP_IKHWAN);

        // 2026-07-13 = Senin. Ikhwan Senin Sesi 1 = 07:40-08:10.
        $this->actingAs($ctx['user'])
            ->post(route('guru.diniyyah-journals.store'), [
                'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
                'date' => '2026-07-13',
                'session_hour' => '1',
                'material' => 'Bab 1',
                'classroom_term_id' => $ctx['classroomTerm']->id,
                'absences' => [],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('diniyyah_class_journals', [
            'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
            'session_hour' => '1',
            'session_starts_at' => '07:40:00',
            'session_ends_at' => '08:10:00',
        ]);
    }

    public function test_store_persists_akhwat_senin_snapshot(): void
    {
        $ctx = $this->makeContext(SessionTimetable::GROUP_AKHWAT);

        $this->actingAs($ctx['user'])
            ->post(route('guru.diniyyah-journals.store'), [
                'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
                'date' => '2026-07-13', // Senin
                'session_hour' => '1',
                'material' => 'Bab 1',
                'classroom_term_id' => $ctx['classroomTerm']->id,
                'absences' => [],
            ])
            ->assertRedirect();

        // Akhwat Senin Sesi 1 = 10:30-11:00 (bukan 07:40 seperti Ikhwan).
        $this->assertDatabaseHas('diniyyah_class_journals', [
            'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
            'session_starts_at' => '10:30:00',
            'session_ends_at' => '11:00:00',
        ]);
    }

    private function hm(string $time): string
    {
        return substr($time, 0, 5);
    }

    private function academicTermId(): int
    {
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);

        return AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Genap', 'semester' => 'genap'])->id;
    }

    private function makeContext(string $genderGroup): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => 'Guru Fiqih']);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => 'Guru Fiqih']);

        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Genap', 'semester' => 'genap']);
        $classroom = Classroom::create([
            'name' => 'Mustawa 1',
            'gender_group' => $genderGroup,
        ]);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => 'Mustawa 1 '.ucfirst($genderGroup),
        ]);
        $subject = DiniyyahSubject::create(['code' => 'fiqih', 'name' => 'Fiqih', 'default_assessment_method' => 'weighted']);
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
            'classroomTerm' => $classroomTerm,
            'assignment' => $assignment,
        ];
    }
}