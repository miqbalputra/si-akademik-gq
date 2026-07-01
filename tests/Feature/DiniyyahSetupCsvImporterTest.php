<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\School;
use App\Services\Imports\DiniyyahSetupCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DiniyyahSetupCsvImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_importer_creates_subject_class_subject_assessment_set_and_default_components(): void
    {
        $this->makeClassroomTerm();

        $path = $this->makeCsv([
            [
                'tahun_ajaran' => '2026/2027',
                'periode' => 'ganjil',
                'kelas_periode' => 'Mustawa 1 Ikhwan',
                'kode_mapel' => 'AKD',
                'nama_mapel' => 'Akidah Akhlak',
                'nama_arab' => '',
                'assessment_method' => 'weighted',
                'kkm' => '75',
                'daily_weight' => '40',
                'exam_weight' => '60',
                'masuk_leger' => 'ya',
                'masuk_rapor' => 'ya',
                'urutan_mapel' => '10',
                'mapel_aktif' => 'ya',
                'mapel_kelas_aktif' => 'ya',
                'judul_set_nilai' => 'Akidah Akhlak Semester Ganjil',
                'materi' => 'Bab I - III',
                'status_set' => 'active',
                'urutan_set' => '10',
                'buat_default_komponen' => 'ya',
                'component_code' => '',
                'component_name' => '',
                'component_group' => '',
                'component_sort' => '',
                'component_required' => '',
            ],
        ]);

        $result = app(DiniyyahSetupCsvImporter::class)->import($path);

        $this->assertFalse($result->hasErrors());
        $this->assertSame(1, $result->subjectsCreated);
        $this->assertSame(1, $result->classSubjectsCreated);
        $this->assertSame(1, $result->assessmentSetsCreated);
        $this->assertSame(5, $result->componentsCreated);
        $this->assertSame(1, $result->defaultComponentSetsApplied);
        $this->assertDatabaseHas('diniyyah_subjects', [
            'code' => 'AKD',
            'name' => 'Akidah Akhlak',
        ]);
        $this->assertDatabaseHas('diniyyah_assessment_sets', [
            'title' => 'Akidah Akhlak Semester Ganjil',
            'status' => 'active',
            'kkm' => 75,
        ]);
        $this->assertDatabaseHas('diniyyah_score_components', [
            'code' => 'keaktifan_presensi',
            'component_group' => 'daily',
        ]);

        File::delete($path);
    }

    public function test_importer_creates_custom_components_across_multiple_rows(): void
    {
        $this->makeClassroomTerm();

        $path = $this->makeCsv([
            $this->rowWithComponent('keaktifan_presensi', 'Keaktifan/Presensi', 'daily', '10'),
            $this->rowWithComponent('nilai_ujian', 'Nilai Ujian', 'exam', '50'),
        ]);

        $result = app(DiniyyahSetupCsvImporter::class)->import($path);

        $this->assertFalse($result->hasErrors());
        $this->assertSame(2, $result->processedRows);
        $this->assertSame(1, $result->subjectsCreated);
        $this->assertSame(1, $result->subjectsUpdated);
        $this->assertSame(1, $result->assessmentSetsCreated);
        $this->assertSame(1, $result->assessmentSetsUpdated);
        $this->assertSame(2, $result->componentsCreated);
        $this->assertSame(0, $result->defaultComponentSetsApplied);
        $this->assertDatabaseCount('diniyyah_score_components', 2);
        $this->assertDatabaseHas('diniyyah_score_components', [
            'code' => 'nilai_ujian',
            'component_group' => 'exam',
        ]);

        File::delete($path);
    }

    public function test_importer_reports_missing_classroom_term_without_creating_setup(): void
    {
        $path = $this->makeCsv([
            [
                'tahun_ajaran' => '2026/2027',
                'periode' => 'ganjil',
                'kelas_periode' => 'Kelas Tidak Ada',
                'kode_mapel' => 'AKD',
                'nama_mapel' => 'Akidah Akhlak',
                'nama_arab' => '',
                'assessment_method' => 'weighted',
                'kkm' => '75',
                'daily_weight' => '40',
                'exam_weight' => '60',
                'masuk_leger' => 'ya',
                'masuk_rapor' => 'ya',
                'urutan_mapel' => '10',
                'mapel_aktif' => 'ya',
                'mapel_kelas_aktif' => 'ya',
                'judul_set_nilai' => 'Akidah Akhlak Semester Ganjil',
                'materi' => '',
                'status_set' => 'active',
                'urutan_set' => '10',
                'buat_default_komponen' => 'ya',
                'component_code' => '',
                'component_name' => '',
                'component_group' => '',
                'component_sort' => '',
                'component_required' => '',
            ],
        ]);

        $result = app(DiniyyahSetupCsvImporter::class)->import($path);

        $this->assertTrue($result->hasErrors());
        $this->assertSame(1, $result->processedRows);
        $this->assertDatabaseCount('diniyyah_subjects', 0);
        $this->assertDatabaseCount('diniyyah_assessment_sets', 0);

        File::delete($path);
    }

    private function makeClassroomTerm(): ClassroomTerm
    {
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Semester Ganjil', 'semester' => 'ganjil']);
        $classroom = Classroom::create(['name' => 'Mustawa 1 Ikhwan']);

        return ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => 'Mustawa 1 Ikhwan',
        ]);
    }

    /** @return array<string, string> */
    private function rowWithComponent(string $code, string $name, string $group, string $sort): array
    {
        return [
            'tahun_ajaran' => '2026/2027',
            'periode' => 'ganjil',
            'kelas_periode' => 'Mustawa 1 Ikhwan',
            'kode_mapel' => 'FIQ',
            'nama_mapel' => 'Fiqih',
            'nama_arab' => '',
            'assessment_method' => 'weighted',
            'kkm' => '75',
            'daily_weight' => '40',
            'exam_weight' => '60',
            'masuk_leger' => 'ya',
            'masuk_rapor' => 'ya',
            'urutan_mapel' => '20',
            'mapel_aktif' => 'ya',
            'mapel_kelas_aktif' => 'ya',
            'judul_set_nilai' => 'Fiqih Semester Ganjil',
            'materi' => 'Bab I - III',
            'status_set' => 'active',
            'urutan_set' => '20',
            'buat_default_komponen' => 'tidak',
            'component_code' => $code,
            'component_name' => $name,
            'component_group' => $group,
            'component_sort' => $sort,
            'component_required' => 'ya',
        ];
    }

    /** @param array<int, array<string, string>> $rows */
    private function makeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'diniyyah-setup-import-').'.csv';
        $handle = fopen($path, 'w');
        $headers = [
            'tahun_ajaran',
            'periode',
            'kelas_periode',
            'kode_mapel',
            'nama_mapel',
            'nama_arab',
            'assessment_method',
            'kkm',
            'daily_weight',
            'exam_weight',
            'masuk_leger',
            'masuk_rapor',
            'urutan_mapel',
            'mapel_aktif',
            'mapel_kelas_aktif',
            'judul_set_nilai',
            'materi',
            'status_set',
            'urutan_set',
            'buat_default_komponen',
            'component_code',
            'component_name',
            'component_group',
            'component_sort',
            'component_required',
        ];

        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $header): string => $row[$header] ?? '', $headers));
        }

        fclose($handle);

        return $path;
    }
}
