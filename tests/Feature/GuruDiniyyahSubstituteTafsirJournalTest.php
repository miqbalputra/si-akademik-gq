<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\ClassSession;
use App\Models\DiniyyahClassJournal;
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

/**
 * Jurnal Pengganti Tafsir (serentak): pengganti centang kelas Tafsir milik
 * guru lain yang dia gantikan; 1 materi → 1 jurnal pengganti per kelas
 * tercentang (substitute_teacher_id = pengganti, JP ke pengganti).
 */
class GuruDiniyyahSubstituteTafsirJournalTest extends TestCase
{
    use RefreshDatabase;

    /** 2026-07-16 = Kamis. */
    private const KAMIS = '2026-07-16';

    public function test_index_lists_other_teachers_tafsir_assignments_grouped_by_teacher(): void
    {
        $original = $this->makeTafsirTeacher('Farhan Dhia Alauddin', ['Mustawa 2 Ikhwan', 'Mustawa 3 Ikhwan']);
        $substitute = $this->makeSubstitute('Pengganti');

        $response = $this->actingAs($substitute['user'])
            ->get(route('guru.diniyyah-substitute-tafsir-journals.index', ['date' => self::KAMIS]));

        $response->assertOk()
            ->assertSee('Farhan Dhia Alauddin')
            ->assertSee('Mustawa 2 Ikhwan')
            ->assertSee('Mustawa 3 Ikhwan');
    }

    public function test_scheduleless_male_tafsir_substitute_only_sees_ikhwan_classes(): void
    {
        $original = $this->makeTafsirTeacher('Guru Tafsir', [
            'Mustawa 2 Ikhwan',
            'Mustawa 3 Ikhwan',
            'Mustawa 2 Akhwat',
        ]);
        $substitute = $this->makeSubstitute('Pengganti', 'male');

        $this->actingAs($substitute['user'])
            ->get(route('guru.diniyyah-substitute-tafsir-journals.index', ['date' => self::KAMIS]))
            ->assertOk()
            ->assertSee('Mustawa 2 Ikhwan')
            ->assertSee('Mustawa 3 Ikhwan')
            ->assertDontSee('Mustawa 2 Akhwat');
    }

    public function test_store_creates_substitute_journals_for_checked_classes(): void
    {
        $original = $this->makeTafsirTeacher('Farhan Dhia Alauddin', ['Mustawa 2 Ikhwan', 'Mustawa 3 Ikhwan', 'Mustawa 4 Ikhwan']);
        $substitute = $this->makeSubstitute('Pengganti');

        [$a2, $a3, $a4] = $original['assignments'];

        $response = $this->actingAs($substitute['user'])
            ->post(route('guru.diniyyah-substitute-tafsir-journals.store'), [
                'date' => self::KAMIS,
                'material' => 'Tafsir Surat Al-Fatihah',
                'assignments' => [$a2->id, $a4->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame(2, DiniyyahClassJournal::where('session_hour', 'tafsir')->count());
        $this->assertDatabaseHas('diniyyah_class_journals', [
            'diniyyah_teacher_assignment_id' => $a2->id,
            'substitute_teacher_id' => $substitute['teacher']->id,
            'session_hour' => 'tafsir',
            'session_starts_at' => '09:50:00',
            'session_ends_at' => '10:20:00',
            'material' => 'Tafsir Surat Al-Fatihah',
        ]);
        $this->assertDatabaseHas('diniyyah_class_journals', [
            'diniyyah_teacher_assignment_id' => $a4->id,
            'substitute_teacher_id' => $substitute['teacher']->id,
        ]);
        $this->assertDatabaseMissing('diniyyah_class_journals', [
            'diniyyah_teacher_assignment_id' => $a3->id,
        ]);

        // effectiveTeacher() = pengganti (JP ke pengganti).
        $journal = DiniyyahClassJournal::where('diniyyah_teacher_assignment_id', $a2->id)->first();
        $this->assertSame($substitute['teacher']->id, $journal->effectiveTeacher()->id);
    }

    public function test_store_rejects_mixed_assignments_from_own_and_other_groups(): void
    {
        // Pengganti kebetulan juga guru Tafsir dengan assignment sendiri.
        $substitute = $this->makeTafsirTeacher('Pengganti', ['Mustawa 2 Ikhwan']);
        $ownId = $substitute['assignments'][0]->id;

        $original = $this->makeTafsirTeacher('Farhan', ['Mustawa 3 Ikhwan', 'Mustawa 4 Ikhwan']);
        $otherId = $original['assignments'][0]->id;

        // Satu request hanya boleh memuat kelas dari satu kelompok serentak
        // milik guru asli yang sama.
        $response = $this->actingAs($substitute['user'])
            ->post(route('guru.diniyyah-substitute-tafsir-journals.store'), [
                'date' => self::KAMIS,
                'material' => 'Materi',
                'assignments' => [$ownId, $otherId],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('diniyyah_class_journals', [
            'diniyyah_teacher_assignment_id' => $otherId,
        ]);
        $this->assertDatabaseMissing('diniyyah_class_journals', [
            'diniyyah_teacher_assignment_id' => $ownId,
        ]);
    }

    public function test_store_requires_at_least_one_assignment(): void
    {
        $original = $this->makeTafsirTeacher('Farhan', ['Mustawa 2 Ikhwan']);
        $substitute = $this->makeSubstitute('Pengganti');

        $response = $this->actingAs($substitute['user'])
            ->post(route('guru.diniyyah-substitute-tafsir-journals.store'), [
                'date' => self::KAMIS,
                'material' => 'Materi',
                'assignments' => [],
            ]);

        $response->assertSessionHasErrors('assignments');
        $this->assertSame(0, DiniyyahClassJournal::count());
    }

    public function test_store_skips_when_slot_already_filled(): void
    {
        $original = $this->makeTafsirTeacher('Farhan', ['Mustawa 2 Ikhwan', 'Mustawa 3 Ikhwan']);
        $substitute = $this->makeSubstitute('Pengganti');
        [$a2, $a3] = $original['assignments'];

        // Guru asli sudah mengisi jurnal Tafsir M2 di tanggal ini.
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $a2->id,
            'date' => self::KAMIS,
            'session_hour' => SessionTimetable::SESSION_TAFSIR,
            'session_starts_at' => '09:50:00',
            'session_ends_at' => '10:20:00',
            'material' => 'Sudah diisi guru asli',
            'jp_count' => 1,
        ]);

        $response = $this->actingAs($substitute['user'])
            ->post(route('guru.diniyyah-substitute-tafsir-journals.store'), [
                'date' => self::KAMIS,
                'material' => 'Pengganti',
                'assignments' => [$a2->id, $a3->id],
            ]);

        $response->assertRedirect();
        // M2 di-skip (slot sudah terisi), M3 berhasil → total 2 jurnal (1 asli + 1 pengganti).
        $this->assertSame(2, DiniyyahClassJournal::where('session_hour', 'tafsir')->count());
        $this->assertDatabaseHas('diniyyah_class_journals', [
            'diniyyah_teacher_assignment_id' => $a3->id,
            'substitute_teacher_id' => $substitute['teacher']->id,
        ]);
        // M2 tetap milik guru asli (tidak ditimpa pengganti).
        $this->assertSame(
            null,
            DiniyyahClassJournal::where('diniyyah_teacher_assignment_id', $a2->id)->value('substitute_teacher_id'),
        );
    }

    public function test_store_rejects_date_without_simultaneous_tafsir_schedule(): void
    {
        $original = $this->makeTafsirTeacher('Farhan', ['Mustawa 2 Ikhwan']);
        $substitute = $this->makeSubstitute('Pengganti');

        $response = $this->actingAs($substitute['user'])
            ->post(route('guru.diniyyah-substitute-tafsir-journals.store'), [
                'date' => '2026-07-14', // Selasa
                'material' => 'Materi',
                'assignments' => [$original['assignments'][0]->id],
            ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, DiniyyahClassJournal::count());
    }

    /**
     * Buat guru Tafsir asli + tafsir assignment untuk tiap nama kelas.
     *
     * @param  string[]  $classroomNames
     */
    private function makeTafsirTeacher(string $teacherName, array $classroomNames): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => $teacherName]);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => $teacherName]);

        $termId = $this->academicTermId();
        $tafsirSubject = DiniyyahSubject::firstOrCreate(
            ['code' => 'tafsir'],
            ['name' => 'Tafsir Al Quran', 'default_assessment_method' => 'weighted', 'is_active' => true],
        );

        $assignments = [];
        foreach ($classroomNames as $name) {
            $classroom = Classroom::create(['name' => $name]);
            SessionTimetable::seedForClassroom($classroom);
            $classroomTerm = ClassroomTerm::create(['academic_term_id' => $termId, 'classroom_id' => $classroom->id, 'name' => $name]);
            $classSubject = DiniyyahClassSubject::create(['classroom_term_id' => $classroomTerm->id, 'subject_id' => $tafsirSubject->id, 'assessment_method' => 'weighted', 'kkm' => 70, 'daily_weight' => 40, 'exam_weight' => 60]);
            $assignments[] = DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $classSubject->id, 'teacher_id' => $teacher->id, 'assignment_role' => 'primary']);
            $tafsirSession = ClassSession::where('session_name', SessionTimetable::SESSION_TAFSIR)->firstOrFail();
            DiniyyahTeachingSchedule::create([
                'diniyyah_teacher_assignment_id' => $assignments[array_key_last($assignments)]->id,
                'class_session_id' => $tafsirSession->id,
                'day_of_week' => 4,
            ]);
        }

        return ['user' => $user, 'teacher' => $teacher, 'assignments' => $assignments];
    }

    private function makeSubstitute(string $name, ?string $gender = null): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => $name, 'gender' => $gender]);

        return ['user' => $user, 'teacher' => $teacher];
    }

    private function academicTermId(): int
    {
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);

        return AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil'])->id;
    }
}
