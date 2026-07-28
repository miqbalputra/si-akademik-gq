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
 * Matrix jam sesi diniyyah per-classroom + hari (Mustawa 1-6 Ikhwan/Akhwat).
 * Sumber: jadwal_mustawa_ikhwan.md & jadwal_mustawa_akhwat_2026_2027.md.
 * Matrix di-seed per test via SessionTimetable::seedForClassroom().
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
        $classroom = $this->createClassroom('Mustawa 1 Ikhwan');

        $slots = SessionTimetable::slotsFor($classroom->id, 1);

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
        $classroom = $this->createClassroom('Mustawa 1 Akhwat');

        $slots = SessionTimetable::slotsFor($classroom->id, 1);

        $this->assertCount(2, $slots);
        $this->assertSame('1', $slots[0]->session_name);
        $this->assertSame('10:30', $this->hm($slots[0]->starts_at));
        $this->assertSame('11:00', $this->hm($slots[0]->ends_at));
        $this->assertSame('11:00', $this->hm($slots[1]->starts_at));
        $this->assertSame('11:30', $this->hm($slots[1]->ends_at));
    }

    public function test_m1_kamis_has_no_tafsir_slot(): void
    {
        $classroom = $this->createClassroom('Mustawa 1 Ikhwan');

        $slots = SessionTimetable::slotsFor($classroom->id, 4);

        $this->assertCount(2, $slots);
        $this->assertSame('1', $slots[0]->session_name);
        $this->assertSame('2', $slots[1]->session_name);
        $this->assertNull(SessionTimetable::resolve($classroom->id, 4, 'tafsir'));
    }

    public function test_m2_kamis_tafsir_appears_first(): void
    {
        $classroom = $this->createClassroom('Mustawa 2 Ikhwan');

        $slots = SessionTimetable::slotsFor($classroom->id, 4);

        $this->assertCount(3, $slots);
        $this->assertSame('tafsir', $slots[0]->session_name);
        $this->assertSame('09:50', $this->hm($slots[0]->starts_at));
        $this->assertSame('1', $slots[1]->session_name);
        $this->assertSame('2', $slots[2]->session_name);
    }

    public function test_slots_for_sabtu_is_empty(): void
    {
        $classroom = $this->createClassroom('Mustawa 1 Ikhwan');

        $this->assertTrue(SessionTimetable::slotsFor($classroom->id, 6)->isEmpty());
    }

    public function test_resolve_returns_times_or_null(): void
    {
        $classroom = $this->createClassroom('Mustawa 1 Ikhwan');

        $this->assertSame(
            ['starts_at' => '07:40:00', 'ends_at' => '08:10:00'],
            SessionTimetable::resolve($classroom->id, 1, '1'),
        );
        $this->assertNull(SessionTimetable::resolve($classroom->id, 6, '1'));
        $this->assertNull(SessionTimetable::resolve($classroom->id, 1, 'nonexistent'));
    }

    public function test_parse_classroom_extracts_gender_and_level(): void
    {
        $this->assertSame(['ikhwan', 1], SessionTimetable::parseClassroom(new Classroom(['name' => 'Mustawa 1 Ikhwan'])));
        $this->assertSame(['akhwat', 3], SessionTimetable::parseClassroom(new Classroom(['name' => 'Mustawa 3 Akhwat'])));
        $this->assertNull(SessionTimetable::parseClassroom(new Classroom(['name' => 'Kelas PKBM A'])));
    }

    public function test_store_persists_session_time_snapshot(): void
    {
        $ctx = $this->makeContext('ikhwan', 1);

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
        $ctx = $this->makeContext('akhwat', 1);

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

    /**
     * Buat classroom Mustawa ber-nama prod + seed matrix sesi-nya.
     */
    private function createClassroom(string $name): Classroom
    {
        $classroom = Classroom::create(['name' => $name]);
        SessionTimetable::seedForClassroom($classroom);

        return $classroom;
    }

    private function makeContext(string $gender, int $level = 1): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => 'Guru Fiqih']);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => 'Guru Fiqih']);

        $termId = $this->academicTermId();
        $classroom = $this->createClassroom(sprintf('Mustawa %d %s', $level, ucfirst($gender)));
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $termId,
            'classroom_id' => $classroom->id,
            'name' => $classroom->name,
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