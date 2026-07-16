<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import data santri (TANPA wali) dari file CSV.
 *
 * Dipakai ketika data santri ingin masuk dulu, sementara penghubungan
 * ayah/ibu (wali) menyusul lewat menu Siswa atau import "Siswa & Wali"
 * (import ulang dgn NIS yang sama → santri di-update, wali dibuat & di-link,
 *  tanpa menghapus data yang sudah ada berkat syncWithoutDetaching).
 *
 * Kolom CSV (header bisa huruf besar/kecil, spasi -> snake_case):
 *   - nis              (wajib)
 *   - nama             (wajib)  — juga menerima header "nama_siswa"
 *   - jenis_kelamin    (wajib)  — l/ikhwan/laki-laki/male | p/akhwat/perempuan/female
 *   - status           (opsional, default: active) — active | inactive
 *   - nik              (opsional)
 */
#[Signature('students:import {path : Path ke file CSV santri}')]
#[Description('Import data santri (tanpa wali) dari CSV. Kolom: nis, nama, jenis_kelamin, status(opsional), nik(opsional).')]
class ImportStudents extends Command
{
    /** @var array<int, string> */
    private const REQUIRED_HEADERS = ['nis', 'nama', 'jenis_kelamin'];

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("File tidak bisa dibaca: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error('File gagal dibuka.');

            return self::FAILURE;
        }

        $headers = $this->readHeaders($handle);
        if ($headers === []) {
            $this->error('Header CSV kosong.');
            fclose($handle);

            return self::FAILURE;
        }

        // Alias: terima header template "Siswa & Wali" (nama_siswa / jenis_kelamin_siswa)
        // sehingga CSV template import-wali-siswa bisa langsung dipakai untuk santri doang.
        $headers = $this->applyHeaderAliases($headers);

        foreach (self::REQUIRED_HEADERS as $required) {
            if (! in_array($required, $headers, true)) {
                $this->error("Kolom wajib '{$required}' belum ada di header.");
                fclose($handle);

                return self::FAILURE;
            }
        }

        $created = 0;
        $updated = 0;
        $errors = [];

        DB::transaction(function () use ($handle, $headers, &$created, &$updated, &$errors): void {
            $lineNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $data = $this->combineRow($headers, $row);

                if (($data['nis'] ?? '') === '' || ($data['nama'] ?? '') === '' || ($data['jenis_kelamin'] ?? '') === '') {
                    $errors[] = "Baris {$lineNumber}: nis, nama, jenis_kelamin wajib diisi. ( dilewati )";
                    continue;
                }

                $student = Student::withTrashed()->where('nis', $data['nis'])->first();
                $payload = [
                    'name' => $data['nama'],
                    'gender' => $this->normalizeGender($data['jenis_kelamin']),
                    'status' => ($data['status'] ?? '') ?: 'active',
                    'nik' => ($data['nik'] ?? '') ?: null,
                ];

                if ($student) {
                    $student->restore();
                    $student->update($payload);
                    $updated++;
                } else {
                    Student::create(['nis' => $data['nis']] + $payload);
                    $created++;
                }
            }
        });

        fclose($handle);

        foreach ($errors as $error) {
            $this->warn($error);
        }

        $this->info("Import santri selesai. Dibuat: {$created} | Diperbarui: {$updated} | Error: " . count($errors));

        return self::SUCCESS;
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
     * Alias header: terima nama_siswa / jenis_kelamin_siswa dari template
     * "Siswa & Wali" sehingga CSV yang sama bisa dipakai untuk import santri doang.
     *
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    private function applyHeaderAliases(array $headers): array
    {
        return array_map(function (string $header): string {
            return match ($header) {
                'nama_siswa' => 'nama',
                'jenis_kelamin_siswa' => 'jenis_kelamin',
                default => $header,
            };
        }, $headers);
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
}