<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahSubject;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Imports\TeacherAssignmentCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TeacherAssignmentCsvImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_importer_creates_teacher_user_role_class_subject_and_assignment(): void
    {
        $this->makeAcademicContext();

        $path = $this->makeCsv([
            [
                'nama_guru' => 'Ustadz Ahmad',
                'jenis_kelamin' => 'laki-laki',
                'no_hp' => '081234567800',
                'whatsapp' => '081234567800',
                'email' => 'ustadz.ahmad@example.com',
                'alamat' => 'Jl. Guru',
                'tanggal_bertugas' => '2026-01-01',
                'status' => 'active',
                'tugas' => 'guru_diniyyah',
                'buat_akun' => 'ya',
                'password' => 'secret123',
                'kelas_periode' => 'Mustawa 1 Ikhwan',
                'kode_mapel' => 'AKD',
                'nama_mapel' => 'Akidah Akhlak',
                'assessment_method' => 'weighted',
                'kkm' => '75',
                'bobot_harian' => '60',
                'bobot_ujian' => '40',
                'masuk_leger' => 'ya',
                'masuk_rapor' => 'ya',
                'assignment_role' => 'primary',
                'mulai_mengajar' => '2026-01-01',
                'selesai_mengajar' => '',
            ],
        ]);

        $result = app(TeacherAssignmentCsvImporter::class)->import($path);

        $this->assertFalse($result->hasErrors());
        $this->assertSame(1, $result->processedRows);
        $this->assertSame(1, $result->teachersCreated);
        $this->assertSame(1, $result->usersCreated);
        $this->assertSame(1, $result->teacherRolesCreated);
        $this->assertSame(1, $result->classSubjectsCreated);
        $this->assertSame(1, $result->assignmentsCreated);

        $teacher = Teacher::where('email', 'ustadz.ahmad@example.com')->firstOrFail();
        $user = User::where('email', 'ustadz.ahmad@example.com')->firstOrFail();

        $this->assertSame($user->id, $teacher->user_id);
        $this->assertTrue($user->hasRole('guru'));
        $this->assertDatabaseHas('teacher_roles', [
            'teacher_id' => $teacher->id,
            'role_type' => 'guru_diniyyah',
        ]);
        $this->assertDatabaseHas('diniyyah_class_subjects', [
            'kkm' => 75,
            'daily_weight' => 60,
            'exam_weight' => 40,
        ]);
        $this->assertDatabaseHas('diniyyah_teacher_assignments', [
            'teacher_id' => $teacher->id,
            'assignment_role' => 'primary',
        ]);

        File::delete($path);
    }

    public function test_importer_updates_existing_teacher_and_assignment(): void
    {
        $context = $this->makeAcademicContext();
        $teacher = Teacher::create([
            'name' => 'Nama Lama',
            'email' => 'guru@example.com',
            'status' => 'active',
        ]);
        $classSubject = $context['classroomTerm']->diniyyahClassSubjects()->create([
            'subject_id' => $context['subject']->id,
            'assessment_method' => 'weighted',
            'is_active' => true,
        ]);
        $classSubject->teacherAssignments()->create([
            'teacher_id' => $teacher->id,
            'assignment_role' => 'primary',
        ]);

        $path = $this->makeCsv([
            [
                'nama_guru' => 'Nama Baru',
                'jenis_kelamin' => 'perempuan',
                'no_hp' => '081111111111',
                'whatsapp' => '081111111111',
                'email' => 'guru@example.com',
                'alamat' => 'Alamat Baru',
                'tanggal_bertugas' => '2026-02-01',
                'status' => 'active',
                'tugas' => 'guru_diniyyah',
                'buat_akun' => 'tidak',
                'password' => '',
                'kelas_periode' => 'Mustawa 1 Ikhwan',
                'kode_mapel' => 'AKD',
                'nama_mapel' => 'Akidah Akhlak',
                'assessment_method' => 'weighted',
                'kkm' => '',
                'bobot_harian' => '',
                'bobot_ujian' => '',
                'masuk_leger' => 'ya',
                'masuk_rapor' => 'ya',
                'assignment_role' => 'assistant',
                'mulai_mengajar' => '2026-02-01',
                'selesai_mengajar' => '',
            ],
        ]);

        $result = app(TeacherAssignmentCsvImporter::class)->import($path);

        $this->assertFalse($result->hasErrors());
        $this->assertSame(1, $result->teachersUpdated);
        $this->assertSame(1, $result->assignmentsUpdated);
        $this->assertDatabaseHas('teachers', [
            'email' => 'guru@example.com',
            'name' => 'Nama Baru',
            'gender' => 'female',
        ]);
        $this->assertDatabaseHas('diniyyah_teacher_assignments', [
            'teacher_id' => $teacher->id,
            'assignment_role' => 'assistant',
        ]);

        File::delete($path);
    }

    public function test_importer_reports_missing_class_or_subject_without_creating_assignment(): void
    {
        $path = $this->makeCsv([
            [
                'nama_guru' => 'Ustadz Baru',
                'jenis_kelamin' => 'laki-laki',
                'no_hp' => '',
                'whatsapp' => '',
                'email' => 'baru@example.com',
                'alamat' => '',
                'tanggal_bertugas' => '',
                'status' => 'active',
                'tugas' => 'guru_diniyyah',
                'buat_akun' => 'ya',
                'password' => '',
                'kelas_periode' => 'Kelas Tidak Ada',
                'kode_mapel' => 'AKD',
                'nama_mapel' => 'Akidah Akhlak',
                'assessment_method' => 'weighted',
                'kkm' => '75',
                'bobot_harian' => '60',
                'bobot_ujian' => '40',
                'masuk_leger' => 'ya',
                'masuk_rapor' => 'ya',
                'assignment_role' => 'primary',
                'mulai_mengajar' => '',
                'selesai_mengajar' => '',
            ],
        ]);

        $result = app(TeacherAssignmentCsvImporter::class)->import($path);

        $this->assertTrue($result->hasErrors());
        $this->assertSame(1, $result->processedRows);
        $this->assertDatabaseHas('teachers', ['email' => 'baru@example.com']);
        $this->assertDatabaseCount('diniyyah_teacher_assignments', 0);

        File::delete($path);
    }

    /** @return array{classroomTerm: ClassroomTerm, subject: DiniyyahSubject} */
    private function makeAcademicContext(): array
    {
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Semester Ganjil', 'semester' => 'ganjil']);
        $classroom = Classroom::create(['name' => 'Mustawa 1 Ikhwan']);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => 'Mustawa 1 Ikhwan',
        ]);
        $subject = DiniyyahSubject::create([
            'code' => 'AKD',
            'name' => 'Akidah Akhlak',
            'default_assessment_method' => 'weighted',
            'is_active' => true,
        ]);

        return ['classroomTerm' => $classroomTerm, 'subject' => $subject];
    }

    /** @param array<int, array<string, string>> $rows */
    private function makeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'teacher-assignment-import-').'.csv';
        $handle = fopen($path, 'w');
        $headers = [
            'nama_guru',
            'jenis_kelamin',
            'no_hp',
            'whatsapp',
            'email',
            'alamat',
            'tanggal_bertugas',
            'status',
            'tugas',
            'buat_akun',
            'password',
            'kelas_periode',
            'kode_mapel',
            'nama_mapel',
            'assessment_method',
            'kkm',
            'bobot_harian',
            'bobot_ujian',
            'masuk_leger',
            'masuk_rapor',
            'assignment_role',
            'mulai_mengajar',
            'selesai_mengajar',
        ];

        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $header): string => $row[$header] ?? '', $headers));
        }

        fclose($handle);

        return $path;
    }
}
