<?php

namespace App\Services\Imports;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class GuardianStudentCsvImporter
{
    /**
     * Header wajib. Wali (nama_wali/hubungan) bersifat OPSIONAL: baris tanpa
     * wali hanya membuat/memperbarui data santri. Memungkinkan import santri
     * dulu, lalu wali/ayah/ibu di-link menyusul (re-import NIS sama dgn wali
     * menambah tanpa menghapus data lama berkat syncWithoutDetaching).
     *
     * @var array<int, string>
     */
    private const REQUIRED_HEADERS = [
        'nis',
        'nama_siswa',
        'jenis_kelamin_siswa',
    ];

    public function import(string $path): GuardianStudentImportResult
    {
        $result = new GuardianStudentImportResult;

        if (! is_file($path) || ! is_readable($path)) {
            $result->errors[] = 'File import tidak bisa dibaca.';

            return $result;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $result->errors[] = 'File import gagal dibuka.';

            return $result;
        }

        $headers = $this->readHeaders($handle);

        if ($headers === []) {
            $result->errors[] = 'Header CSV kosong.';
            fclose($handle);

            return $result;
        }

        foreach (self::REQUIRED_HEADERS as $requiredHeader) {
            if (! in_array($requiredHeader, $headers, true)) {
                $result->errors[] = "Kolom wajib '{$requiredHeader}' belum ada.";
            }
        }

        if ($result->hasErrors()) {
            fclose($handle);

            return $result;
        }

        Role::firstOrCreate(['name' => 'wali_santri', 'guard_name' => 'web']);

        DB::transaction(function () use ($handle, $headers, $result): void {
            $lineNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $data = $this->combineRow($headers, $row);
                $rowErrors = $this->validateRow($data, $lineNumber);

                if ($rowErrors !== []) {
                    array_push($result->errors, ...$rowErrors);

                    continue;
                }

                $this->importRow($data, $result);
                $result->processedRows++;
            }
        });

        fclose($handle);

        return $result;
    }

    /** @return array<int, string> */
    private function readHeaders(mixed $handle): array
    {
        $headers = fgetcsv($handle);

        if ($headers === false) {
            return [];
        }

        return array_map(fn (?string $header): string => Str::of((string) $header)
            ->replace("\xEF\xBB\xBF", '')
            ->trim()
            ->lower()
            ->snake()
            ->toString(), $headers);
    }

    /** @param array<int, string|null> $row */
    private function isEmptyRow(array $row): bool
    {
        return collect($row)->every(fn ($value): bool => trim((string) $value) === '');
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string|null>  $row
     * @return array<string, string>
     */
    private function combineRow(array $headers, array $row): array
    {
        $data = [];

        foreach ($headers as $index => $header) {
            $data[$header] = trim((string) ($row[$index] ?? ''));
        }

        return $data;
    }

    /**
     * @param  array<string, string>  $data
     * @return array<int, string>
     */
    private function validateRow(array $data, int $lineNumber): array
    {
        $errors = [];

        foreach (self::REQUIRED_HEADERS as $header) {
            if (($data[$header] ?? '') === '') {
                $errors[] = "Baris {$lineNumber}: kolom '{$header}' wajib diisi.";
            }
        }

        if (($data['email'] ?? '') !== '' && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = "Baris {$lineNumber}: format email tidak valid.";
        }

        if ($this->truthy($data['buat_akun'] ?? '') && ($data['email'] ?? '') === '') {
            $errors[] = "Baris {$lineNumber}: email wajib diisi jika buat_akun bernilai ya/true.";
        }

        // buat_akun membuat akun login untuk wali -> wajib ada nama_wali.
        if ($this->truthy($data['buat_akun'] ?? '') && ($data['nama_wali'] ?? '') === '') {
            $errors[] = "Baris {$lineNumber}: nama_wali wajib diisi jika buat_akun bernilai ya/true.";
        }

        return $errors;
    }

    /** @param array<string, string> $data */
    private function importRow(array $data, GuardianStudentImportResult $result): void
    {
        $student = Student::withTrashed()->where('nis', $data['nis'])->first();
        $studentPayload = [
            'name' => $data['nama_siswa'],
            'gender' => $this->normalizeGender($data['jenis_kelamin_siswa']),
            'status' => $data['status_siswa'] ?: 'active',
        ];

        if ($student) {
            $student->restore();
            $student->update($studentPayload);
            $result->studentsUpdated++;
        } else {
            $student = Student::create(['nis' => $data['nis']] + $studentPayload);
            $result->studentsCreated++;
        }

        // Wali opsional: jika baris tanpa nama_wali, cukup santri (wali menyusul).
        if (($data['nama_wali'] ?? '') === '') {
            return;
        }

        $guardian = $this->findGuardian($data);
        $guardianPayload = [
            'name' => $data['nama_wali'],
            'nik' => $data['nik_wali'] ?: null,
            'gender' => $this->normalizeGender($data['jenis_kelamin_wali'] ?? ''),
            'phone' => $data['no_hp'] ?: null,
            'whatsapp' => ($data['whatsapp'] ?? '') ?: ($data['no_hp'] ?: null),
            'email' => $data['email'] ?: null,
            'address' => $data['alamat'] ?: null,
            'status' => $data['status_wali'] ?: 'active',
        ];

        if ($guardian) {
            $guardian->restore();
            $guardian->update($guardianPayload);
            $result->guardiansUpdated++;
        } else {
            $guardian = Guardian::create($guardianPayload);
            $result->guardiansCreated++;
        }

        if ($this->truthy($data['buat_akun'] ?? '')) {
            $user = $this->syncGuardianUser($guardian, $data, $result);
            $guardian->forceFill(['user_id' => $user->id])->save();
        }

        $existingRelation = $student->guardians()
            ->where('guardians.id', $guardian->id)
            ->exists();

        $student->guardians()->syncWithoutDetaching([
            $guardian->id => [
                'relationship' => $data['hubungan'],
                'is_primary' => $this->truthy($data['is_primary'] ?? ''),
                'can_login' => $this->truthy($data['can_login'] ?? '1'),
            ],
        ]);

        $existingRelation ? $result->relationsUpdated++ : $result->relationsCreated++;
    }

    /** @param array<string, string> $data */
    private function findGuardian(array $data): ?Guardian
    {
        if (($data['nik_wali'] ?? '') !== '') {
            return Guardian::withTrashed()->where('nik', $data['nik_wali'])->first();
        }

        if (($data['email'] ?? '') !== '') {
            return Guardian::withTrashed()->where('email', $data['email'])->first();
        }

        return Guardian::withTrashed()
            ->where('name', $data['nama_wali'])
            ->when(($data['no_hp'] ?? '') !== '', fn ($query) => $query->where('phone', $data['no_hp']))
            ->first();
    }

    /** @param array<string, string> $data */
    private function syncGuardianUser(Guardian $guardian, array $data, GuardianStudentImportResult $result): User
    {
        $user = $guardian->user ?: User::where('email', $data['email'])->first();
        $payload = [
            'name' => $guardian->name,
            'email' => $data['email'],
        ];

        if ($user) {
            $user->update($payload);

            if (($data['password'] ?? '') !== '') {
                $user->forceFill(['password' => Hash::make($data['password'])])->save();
            }

            $result->usersUpdated++;
        } else {
            $user = User::create($payload + [
                'password' => $data['password'] ?: $this->defaultPassword($data),
            ]);
            $result->usersCreated++;
        }

        $user->assignRole('wali_santri');

        return $user;
    }

    /** @param array<string, string> $data */
    private function defaultPassword(array $data): string
    {
        return Str::lower($data['nis']).'-wali';
    }

    private function normalizeGender(?string $value): ?string
    {
        $normalized = Str::of((string) $value)->lower()->trim()->toString();

        return match ($normalized) {
            'l', 'lk', 'laki-laki', 'ikhwan', 'male' => 'male',
            'p', 'pr', 'perempuan', 'akhwat', 'female' => 'female',
            default => $normalized !== '' ? $normalized : null,
        };
    }

    private function truthy(?string $value): bool
    {
        return in_array(Str::of((string) $value)->lower()->trim()->toString(), ['1', 'ya', 'yes', 'y', 'true', 'aktif'], true);
    }
}
