<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\School;
use App\Models\Student;
use App\Services\Imports\ClassEnrollmentCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ClassEnrollmentCsvImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_importer_creates_classroom_classroom_term_and_enrollment(): void
    {
        $this->makeAcademicTerm();
        Student::create(['name' => 'Ahmad', 'gender' => 'male', 'nis' => '001']);

        $path = $this->makeCsv([
            [
                'tahun_ajaran' => '2026/2027',
                'periode' => 'ganjil',
                'nama_kelas' => 'Mustawa 1 Ikhwan',
                'nama_kelas_periode' => 'Mustawa 1 Ikhwan',
                'level' => 'mustawa 1',
                'kelompok_gender' => 'ikhwan',
                'urutan' => '10',
                'kelas_aktif' => 'ya',
                'kapasitas' => '30',
                'status_kelas_periode' => 'active',
                'nis' => '001',
                'no_absen' => '1',
                'status_enrollment' => 'active',
            ],
        ]);

        $result = app(ClassEnrollmentCsvImporter::class)->import($path);

        $this->assertFalse($result->hasErrors());
        $this->assertSame(1, $result->processedRows);
        $this->assertSame(1, $result->classroomsCreated);
        $this->assertSame(1, $result->classroomTermsCreated);
        $this->assertSame(1, $result->enrollmentsCreated);
        $this->assertDatabaseHas('classrooms', [
            'name' => 'Mustawa 1 Ikhwan',
            'gender_group' => 'male',
            'sort_order' => 10,
        ]);
        $this->assertDatabaseHas('classroom_terms', [
            'name' => 'Mustawa 1 Ikhwan',
            'capacity' => 30,
        ]);
        $this->assertDatabaseHas('class_enrollments', [
            'roll_number' => 1,
            'status' => 'active',
        ]);

        File::delete($path);
    }

    public function test_importer_updates_existing_enrollment_to_new_classroom_term(): void
    {
        $term = $this->makeAcademicTerm();
        $student = Student::create(['name' => 'Ahmad', 'gender' => 'male', 'nis' => '001']);
        $oldClassroom = Classroom::create(['name' => 'Kelas Lama', 'gender_group' => 'mixed']);
        $oldClassroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $oldClassroom->id,
            'name' => 'Kelas Lama',
        ]);
        $student->enrollments()->create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $oldClassroomTerm->id,
            'roll_number' => 9,
            'status' => 'active',
        ]);

        $path = $this->makeCsv([
            [
                'tahun_ajaran' => '2026/2027',
                'periode' => 'ganjil',
                'nama_kelas' => 'Kelas Baru',
                'nama_kelas_periode' => 'Kelas Baru',
                'level' => '',
                'kelompok_gender' => 'mixed',
                'urutan' => '',
                'kelas_aktif' => 'ya',
                'kapasitas' => '',
                'status_kelas_periode' => 'active',
                'nis' => '001',
                'no_absen' => '2',
                'status_enrollment' => 'active',
            ],
        ]);

        $result = app(ClassEnrollmentCsvImporter::class)->import($path);

        $this->assertFalse($result->hasErrors());
        $this->assertSame(1, $result->enrollmentsUpdated);
        $this->assertDatabaseHas('class_enrollments', [
            'academic_term_id' => $term->id,
            'student_id' => $student->id,
            'roll_number' => 2,
        ]);

        File::delete($path);
    }

    public function test_importer_can_create_classroom_term_without_student_enrollment(): void
    {
        $this->makeAcademicTerm();

        $path = $this->makeCsv([
            [
                'tahun_ajaran' => '2026/2027',
                'periode' => 'ganjil',
                'nama_kelas' => 'Mustawa 2',
                'nama_kelas_periode' => 'Mustawa 2',
                'level' => 'mustawa 2',
                'kelompok_gender' => 'mixed',
                'urutan' => '30',
                'kelas_aktif' => 'ya',
                'kapasitas' => '25',
                'status_kelas_periode' => 'active',
                'nis' => '',
                'no_absen' => '',
                'status_enrollment' => '',
            ],
        ]);

        $result = app(ClassEnrollmentCsvImporter::class)->import($path);

        $this->assertFalse($result->hasErrors());
        $this->assertSame(1, $result->classroomsCreated);
        $this->assertSame(1, $result->classroomTermsCreated);
        $this->assertSame(0, $result->enrollmentsCreated);

        File::delete($path);
    }

    public function test_importer_reports_missing_academic_term_or_student(): void
    {
        $this->makeAcademicTerm();

        $path = $this->makeCsv([
            [
                'tahun_ajaran' => '2026/2027',
                'periode' => 'genap',
                'nama_kelas' => 'Mustawa 1',
                'nama_kelas_periode' => 'Mustawa 1',
                'level' => '',
                'kelompok_gender' => '',
                'urutan' => '',
                'kelas_aktif' => 'ya',
                'kapasitas' => '',
                'status_kelas_periode' => 'active',
                'nis' => '404',
                'no_absen' => '1',
                'status_enrollment' => 'active',
            ],
        ]);

        $result = app(ClassEnrollmentCsvImporter::class)->import($path);

        $this->assertTrue($result->hasErrors());
        $this->assertSame(1, $result->processedRows);
        $this->assertDatabaseCount('class_enrollments', 0);

        File::delete($path);
    }

    private function makeAcademicTerm(): AcademicTerm
    {
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);

        return AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => 'Semester Ganjil',
            'semester' => 'ganjil',
        ]);
    }

    /** @param array<int, array<string, string>> $rows */
    private function makeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'class-enrollment-import-').'.csv';
        $handle = fopen($path, 'w');
        $headers = [
            'tahun_ajaran',
            'periode',
            'nama_kelas',
            'nama_kelas_periode',
            'level',
            'kelompok_gender',
            'urutan',
            'kelas_aktif',
            'kapasitas',
            'status_kelas_periode',
            'nis',
            'no_absen',
            'status_enrollment',
        ];

        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $header): string => $row[$header] ?? '', $headers));
        }

        fclose($handle);

        return $path;
    }
}
