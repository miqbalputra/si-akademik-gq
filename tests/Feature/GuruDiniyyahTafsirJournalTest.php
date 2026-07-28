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
 * Jurnal Tafsir serentak: 1 input materi → 1 jurnal per kelas Tafsir guru.
 * Kamis 09:50-10:20, 1 Ustadz ke M2-M6 Ikhwan (atau Ustadzah ke M2-M6 Akhwat).
 */
class GuruDiniyyahTafsirJournalTest extends TestCase
{
    use RefreshDatabase;

    /** 2026-07-16 = Kamis. */
    private const KAMIS = '2026-07-16';

    public function test_store_creates_one_journal_per_tafsir_class(): void
    {
        $ctx = $this->makeTafsirTeacher(['Mustawa 2 Ikhwan', 'Mustawa 3 Ikhwan', 'Mustawa 4 Ikhwan', 'Mustawa 5 Ikhwan', 'Mustawa 6 Ikhwan']);

        $response = $this->actingAs($ctx['user'])
            ->post(route('guru.diniyyah-tafsir-journals.store'), [
                'date' => self::KAMIS,
                'material' => 'Surat Al-Baqarah ayat 1-5',
            ]);

        $response->assertRedirect();

        $this->assertSame(5, DiniyyahClassJournal::where('session_hour', 'tafsir')->count());
        foreach ($ctx['assignments'] as $assignment) {
            $this->assertDatabaseHas('diniyyah_class_journals', [
                'diniyyah_teacher_assignment_id' => $assignment->id,
                'session_hour' => 'tafsir',
                'session_starts_at' => '09:50:00',
                'session_ends_at' => '10:20:00',
                'material' => 'Surat Al-Baqarah ayat 1-5',
            ]);
        }
    }

    public function test_store_skips_classes_that_already_have_tafsir_journal(): void
    {
        $ctx = $this->makeTafsirTeacher(['Mustawa 2 Ikhwan', 'Mustawa 3 Ikhwan']);

        // Isi pertama kali → 2 jurnal.
        $this->actingAs($ctx['user'])
            ->post(route('guru.diniyyah-tafsir-journals.store'), ['date' => self::KAMIS, 'material' => 'Materi 1'])
            ->assertRedirect();

        // Isi ulang tanggal sama → 0 created, 2 skipped.
        $response = $this->actingAs($ctx['user'])
            ->post(route('guru.diniyyah-tafsir-journals.store'), ['date' => self::KAMIS, 'material' => 'Materi 2']);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(2, DiniyyahClassJournal::where('session_hour', 'tafsir')->count());
    }

    public function test_store_rejects_non_kamis_date(): void
    {
        $ctx = $this->makeTafsirTeacher(['Mustawa 2 Ikhwan']);

        // 2026-07-14 = Selasa.
        $response = $this->actingAs($ctx['user'])
            ->post(route('guru.diniyyah-tafsir-journals.store'), ['date' => '2026-07-14', 'material' => 'Materi']);

        $response->assertSessionHasErrors('date');
        $this->assertSame(0, DiniyyahClassJournal::count());
    }

    public function test_index_shows_message_when_teacher_has_no_tafsir_assignment(): void
    {
        $ctx = $this->makeTafsirTeacher([], withFiqihAssignment: true);

        $this->actingAs($ctx['user'])
            ->get(route('guru.diniyyah-tafsir-journals.index'))
            ->assertOk()
            ->assertSee('Anda belum memiliki penugasan Tafsir');
    }

    public function test_store_without_tafsir_assignment_redirects_with_error(): void
    {
        $ctx = $this->makeTafsirTeacher([], withFiqihAssignment: true);

        $response = $this->actingAs($ctx['user'])
            ->post(route('guru.diniyyah-tafsir-journals.store'), ['date' => self::KAMIS, 'material' => 'Materi']);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(0, DiniyyahClassJournal::count());
    }

    public function test_each_class_journal_is_distinct_per_assignment(): void
    {
        // 5 assignment berbeda, tanggal sama, session_hour='tafsir' → tidak konflik
        // (unique index = assignment_id + date + session_hour).
        $ctx = $this->makeTafsirTeacher(['Mustawa 2 Ikhwan', 'Mustawa 3 Ikhwan', 'Mustawa 4 Ikhwan', 'Mustawa 5 Ikhwan', 'Mustawa 6 Ikhwan']);

        $this->actingAs($ctx['user'])
            ->post(route('guru.diniyyah-tafsir-journals.store'), ['date' => self::KAMIS, 'material' => 'Materi'])
            ->assertRedirect();

        // Unique index = (assignment_id, date, session_hour). 5 assignment berbeda
        // dengan date + session_hour='tafsir' yang sama tidak saling konflik.
        $this->assertSame(5, DiniyyahClassJournal::where('session_hour', 'tafsir')->count());
        $this->assertSame(
            5,
            collect($ctx['assignments'])->pluck('id')
                ->filter(fn ($id) => DiniyyahClassJournal::where('diniyyah_teacher_assignment_id', $id)->exists())
                ->count(),
        );
    }

    /**
     * Buat guru + tafsir assignment untuk tiap nama kelas (satu gender, M2-M6).
     *
     * @param string[] $classroomNames
     */
    private function makeTafsirTeacher(array $classroomNames, bool $withFiqihAssignment = false): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => 'Ustadz Tafsir']);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => 'Ustadz Tafsir']);

        $termId = $this->academicTermId();
        $tafsirSubject = DiniyyahSubject::firstOrCreate(
            ['code' => 'tafsir'],
            ['name' => 'Tafsir Al Quran', 'default_assessment_method' => 'weighted', 'is_active' => true],
        );

        $assignments = [];
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
                'subject_id' => $tafsirSubject->id,
                'assessment_method' => 'weighted',
                'kkm' => 70,
                'daily_weight' => 40,
                'exam_weight' => 60,
            ]);
            $assignments[] = DiniyyahTeacherAssignment::create([
                'diniyyah_class_subject_id' => $classSubject->id,
                'teacher_id' => $teacher->id,
                'assignment_role' => 'primary',
            ]);
        }

        if ($withFiqihAssignment) {
            $classroom = Classroom::create(['name' => 'Mustawa 1 Ikhwan']);
            SessionTimetable::seedForClassroom($classroom);
            $classroomTerm = ClassroomTerm::create(['academic_term_id' => $termId, 'classroom_id' => $classroom->id, 'name' => 'Mustawa 1 Ikhwan']);
            $fiqih = DiniyyahSubject::create(['code' => 'fiqih', 'name' => 'Fiqih', 'default_assessment_method' => 'weighted']);
            $classSubject = DiniyyahClassSubject::create(['classroom_term_id' => $classroomTerm->id, 'subject_id' => $fiqih->id, 'assessment_method' => 'weighted', 'kkm' => 70, 'daily_weight' => 40, 'exam_weight' => 60]);
            DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $classSubject->id, 'teacher_id' => $teacher->id, 'assignment_role' => 'primary']);
        }

        return [
            'user' => $user,
            'teacher' => $teacher,
            'assignments' => $assignments,
        ];
    }

    private function academicTermId(): int
    {
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);

        return AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil'])->id;
    }
}