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
use Tests\TestCase;

/**
 * searchOptions() pada DiniyyahClassSubject & DiniyyahTeacherAssignment: dasar
 * pencarian Select Filament "Mapel Kelas" / "Tugas Mengajar" agar record baru
 * (mis. Imla Mustawa 6 Akhwat) bisa ditemukan dengan mengetik nama kelas/mapel.
 */
class DiniyyahClassSubjectSearchOptionsTest extends TestCase
{
    use RefreshDatabase;

    private int $subjectSeq = 0;

    public function test_search_by_subject_name_finds_class_subject(): void
    {
        [$imla, ] = $this->makeClassSubjects();
        $this->seedFillers(60); // pastikan Imla (id tertinggi) di luar preload-50

        $results = DiniyyahClassSubject::searchOptions('Imla');

        $this->assertArrayHasKey($imla->id, $results);
        $this->assertSame('Mustawa 6 Akhwat - Imla', $results[$imla->id]);
    }

    public function test_search_by_classroom_name_finds_class_subject(): void
    {
        [$imla, ] = $this->makeClassSubjects();

        $results = DiniyyahClassSubject::searchOptions('Mustawa 6');

        $this->assertArrayHasKey($imla->id, $results);
    }

    public function test_search_excludes_non_matching_subjects(): void
    {
        [$imla, $tajwid] = $this->makeClassSubjects();

        $results = DiniyyahClassSubject::searchOptions('Tajwid');

        $this->assertArrayHasKey($tajwid->id, $results);
        $this->assertArrayNotHasKey($imla->id, $results);
    }

    public function test_search_by_exact_id_finds_class_subject(): void
    {
        [$imla, ] = $this->makeClassSubjects();

        $results = DiniyyahClassSubject::searchOptions((string) $imla->id);

        $this->assertArrayHasKey($imla->id, $results);
    }

    public function test_empty_search_returns_no_options(): void
    {
        $this->makeClassSubjects();

        $this->assertSame([], DiniyyahClassSubject::searchOptions(''));
        $this->assertSame([], DiniyyahClassSubject::searchOptions('   '));
    }

    public function test_search_finds_record_beyond_preload_limit(): void
    {
        [$imla, ] = $this->makeClassSubjects();
        // Imla dibuat pertama, lalu 60 record filler → Imla punya id terendah.
        // Untuk mensimulasikan "record baru di luar 50", buat 60 filler DULU lalu
        // satu record target baru terakhir.
        $this->seedFillers(60);
        $target = $this->createClassSubject('Mustawa 5 Akhwat', 'Khat');

        $results = DiniyyahClassSubject::searchOptions('Khat');

        $this->assertArrayHasKey($target->id, $results);
        $this->assertNotSame([], $results);
    }

    public function test_teacher_assignment_search_options_find_by_subject_classroom_and_teacher(): void
    {
        [$imla, ] = $this->makeClassSubjects();
        $guru = Teacher::create(['name' => 'Ustadz Ahmad Nurul Huda']);
        $assignment = DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $imla->id,
            'teacher_id' => $guru->id,
            'assignment_role' => 'primary',
        ]);

        // by mapel
        $this->assertArrayHasKey($assignment->id, DiniyyahTeacherAssignment::searchOptions('Imla'));
        // by kelas
        $this->assertArrayHasKey($assignment->id, DiniyyahTeacherAssignment::searchOptions('Mustawa 6'));
        // by guru
        $this->assertArrayHasKey($assignment->id, DiniyyahTeacherAssignment::searchOptions('Ahmad Nurul'));
        // label lengkap
        $this->assertSame(
            'Mustawa 6 Akhwat - Imla (Ustadz Ahmad Nurul Huda)',
            DiniyyahTeacherAssignment::searchOptions('Imla')[$assignment->id],
        );
    }

    /**
     * Buat Imla (Mustawa 6 Akhwat) + Tajwid (Mustawa 2 Ikhwan) sebagai data dasar.
     *
     * @return array{0: DiniyyahClassSubject, 1: DiniyyahClassSubject}
     */
    private function makeClassSubjects(): array
    {
        $termId = $this->academicTermId();

        $imla = $this->createClassSubject('Mustawa 6 Akhwat', 'Imla', $termId);
        $tajwid = $this->createClassSubject('Mustawa 2 Ikhwan', 'Tajwid', $termId);

        return [$imla, $tajwid];
    }

    private function createClassSubject(string $classroomName, string $subjectName, ?int $termId = null): DiniyyahClassSubject
    {
        $termId ??= $this->academicTermId();

        $classroom = Classroom::create(['name' => $classroomName]);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $termId,
            'classroom_id' => $classroom->id,
            'name' => $classroomName,
        ]);
        // Buat subject baru (bukan firstOrCreate by code) supaya nama persis
        // $subjectName — firstOrCreate by code bisa menemukan subject seeder
        // (mis. code 'imla' → name "Imla'") dan mengembalikan nama itu.
        $subject = DiniyyahSubject::create([
            'code' => 'test_'.++$this->subjectSeq.'_'.strtolower(str_replace(' ', '_', $subjectName)),
            'name' => $subjectName,
            'default_assessment_method' => 'weighted',
            'is_active' => true,
        ]);

        return DiniyyahClassSubject::create([
            'classroom_term_id' => $classroomTerm->id,
            'subject_id' => $subject->id,
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
        ]);
    }

    /**
     * Buat $count class subject filler (kelas/mapel acak) untuk mendorong record
     * target keluar dari batas preload-50.
     */
    private function seedFillers(int $count): void
    {
        $termId = $this->academicTermId();
        for ($i = 1; $i <= $count; $i++) {
            $this->createClassSubject("Kelas Filler {$i}", "Mapel Filler {$i}", $termId);
        }
    }

    private function academicTermId(): int
    {
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);

        return AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil'])->id;
    }
}