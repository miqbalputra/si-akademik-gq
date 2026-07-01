<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Services\Imports\GuardianStudentCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuardianStudentCsvImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_importer_creates_students_guardians_users_and_relations(): void
    {
        $path = $this->makeCsv([
            [
                'nis' => '001',
                'nama_siswa' => 'Ahmad',
                'jenis_kelamin_siswa' => 'laki-laki',
                'status_siswa' => 'active',
                'nama_wali' => 'Abdullah',
                'nik_wali' => '3276000000000001',
                'jenis_kelamin_wali' => 'laki-laki',
                'no_hp' => '081234567890',
                'whatsapp' => '081234567890',
                'email' => 'abdullah@example.com',
                'alamat' => 'Jl. Contoh',
                'status_wali' => 'active',
                'hubungan' => 'ayah',
                'is_primary' => 'ya',
                'can_login' => 'ya',
                'buat_akun' => 'ya',
                'password' => 'secret123',
            ],
        ]);

        $result = app(GuardianStudentCsvImporter::class)->import($path);

        $this->assertFalse($result->hasErrors());
        $this->assertSame(1, $result->processedRows);
        $this->assertSame(1, $result->studentsCreated);
        $this->assertSame(1, $result->guardiansCreated);
        $this->assertSame(1, $result->usersCreated);
        $this->assertSame(1, $result->relationsCreated);

        $student = Student::where('nis', '001')->firstOrFail();
        $guardian = Guardian::where('nik', '3276000000000001')->firstOrFail();
        $user = User::where('email', 'abdullah@example.com')->firstOrFail();

        $this->assertSame('male', $student->gender);
        $this->assertSame('male', $guardian->gender);
        $this->assertTrue($user->hasRole('wali_santri'));
        $this->assertDatabaseHas('student_guardians', [
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'relationship' => 'ayah',
            'is_primary' => true,
            'can_login' => true,
        ]);

        File::delete($path);
    }

    public function test_importer_updates_existing_records_by_nis_and_nik(): void
    {
        Role::firstOrCreate(['name' => 'wali_santri', 'guard_name' => 'web']);

        Student::create([
            'nis' => '001',
            'name' => 'Nama Lama',
            'gender' => 'male',
            'status' => 'active',
        ]);
        Guardian::create([
            'name' => 'Wali Lama',
            'nik' => '3276000000000001',
            'status' => 'active',
        ]);

        $path = $this->makeCsv([
            [
                'nis' => '001',
                'nama_siswa' => 'Nama Baru',
                'jenis_kelamin_siswa' => 'perempuan',
                'status_siswa' => 'active',
                'nama_wali' => 'Wali Baru',
                'nik_wali' => '3276000000000001',
                'jenis_kelamin_wali' => 'perempuan',
                'no_hp' => '081111111111',
                'whatsapp' => '081111111111',
                'email' => 'wali@example.com',
                'alamat' => 'Alamat Baru',
                'status_wali' => 'active',
                'hubungan' => 'ibu',
                'is_primary' => 'ya',
                'can_login' => 'ya',
                'buat_akun' => 'tidak',
                'password' => '',
            ],
        ]);

        $result = app(GuardianStudentCsvImporter::class)->import($path);

        $this->assertFalse($result->hasErrors());
        $this->assertSame(1, $result->studentsUpdated);
        $this->assertSame(1, $result->guardiansUpdated);
        $this->assertSame(0, $result->usersCreated);
        $this->assertDatabaseHas('students', ['nis' => '001', 'name' => 'Nama Baru', 'gender' => 'female']);
        $this->assertDatabaseHas('guardians', ['nik' => '3276000000000001', 'name' => 'Wali Baru', 'gender' => 'female']);

        File::delete($path);
    }

    public function test_importer_reports_invalid_rows_without_creating_records(): void
    {
        $path = $this->makeCsv([
            [
                'nis' => '',
                'nama_siswa' => 'Tanpa NIS',
                'jenis_kelamin_siswa' => 'laki-laki',
                'status_siswa' => 'active',
                'nama_wali' => 'Wali',
                'nik_wali' => '',
                'jenis_kelamin_wali' => '',
                'no_hp' => '',
                'whatsapp' => '',
                'email' => 'email-salah',
                'alamat' => '',
                'status_wali' => 'active',
                'hubungan' => 'ayah',
                'is_primary' => 'ya',
                'can_login' => 'ya',
                'buat_akun' => 'ya',
                'password' => '',
            ],
        ]);

        $result = app(GuardianStudentCsvImporter::class)->import($path);

        $this->assertTrue($result->hasErrors());
        $this->assertSame(0, $result->processedRows);
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('guardians', 0);

        File::delete($path);
    }

    /** @param array<int, array<string, string>> $rows */
    private function makeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'guardian-student-import-').'.csv';
        $handle = fopen($path, 'w');
        $headers = [
            'nis',
            'nama_siswa',
            'jenis_kelamin_siswa',
            'status_siswa',
            'nama_wali',
            'nik_wali',
            'jenis_kelamin_wali',
            'no_hp',
            'whatsapp',
            'email',
            'alamat',
            'status_wali',
            'hubungan',
            'is_primary',
            'can_login',
            'buat_akun',
            'password',
        ];

        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $header): string => $row[$header] ?? '', $headers));
        }

        fclose($handle);

        return $path;
    }
}
