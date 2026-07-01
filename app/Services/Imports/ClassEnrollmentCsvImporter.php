<?php

namespace App\Services\Imports;

use App\Models\AcademicTerm;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClassEnrollmentCsvImporter
{
    /** @var array<int, string> */
    private const REQUIRED_HEADERS = [
        'tahun_ajaran',
        'periode',
        'nama_kelas',
    ];

    public function import(string $path): ClassEnrollmentImportResult
    {
        $result = new ClassEnrollmentImportResult;

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

                $this->importRow($data, $result, $lineNumber);
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

        if (($data['nis'] ?? '') === '' && ($data['no_absen'] ?? '') !== '') {
            $errors[] = "Baris {$lineNumber}: nis wajib diisi jika no_absen diisi.";
        }

        return $errors;
    }

    /** @param array<string, string> $data */
    private function importRow(array $data, ClassEnrollmentImportResult $result, int $lineNumber): void
    {
        $term = $this->findAcademicTerm($data);

        if (! $term) {
            $result->errors[] = "Baris {$lineNumber}: tahun_ajaran '{$data['tahun_ajaran']}' periode '{$data['periode']}' tidak ditemukan.";

            return;
        }

        $classroom = Classroom::withTrashed()->where('name', $data['nama_kelas'])->first();
        $classroomPayload = [
            'level_name' => ($data['level'] ?? '') ?: null,
            'gender_group' => $this->normalizeGenderGroup($data['kelompok_gender'] ?? ''),
            'sort_order' => ($data['urutan'] ?? '') !== '' ? (int) $data['urutan'] : 0,
            'is_active' => $this->truthy($data['kelas_aktif'] ?? '1'),
        ];

        if ($classroom) {
            $classroom->restore();
            $classroom->update($classroomPayload);
            $result->classroomsUpdated++;
        } else {
            $classroom = Classroom::create(['name' => $data['nama_kelas']] + $classroomPayload);
            $result->classroomsCreated++;
        }

        $classroomTerm = ClassroomTerm::where('academic_term_id', $term->id)
            ->where('classroom_id', $classroom->id)
            ->first();
        $classroomTermPayload = [
            'name' => ($data['nama_kelas_periode'] ?? '') ?: $data['nama_kelas'],
            'capacity' => ($data['kapasitas'] ?? '') !== '' ? (int) $data['kapasitas'] : null,
            'status' => ($data['status_kelas_periode'] ?? '') ?: 'active',
        ];

        if ($classroomTerm) {
            $classroomTerm->update($classroomTermPayload);
            $result->classroomTermsUpdated++;
        } else {
            $classroomTerm = ClassroomTerm::create([
                'academic_term_id' => $term->id,
                'classroom_id' => $classroom->id,
            ] + $classroomTermPayload);
            $result->classroomTermsCreated++;
        }

        if (($data['nis'] ?? '') === '') {
            return;
        }

        $student = Student::where('nis', $data['nis'])->first();

        if (! $student) {
            $result->errors[] = "Baris {$lineNumber}: siswa dengan nis '{$data['nis']}' tidak ditemukan.";

            return;
        }

        $enrollment = ClassEnrollment::where('academic_term_id', $term->id)
            ->where('student_id', $student->id)
            ->first();
        $enrollmentPayload = [
            'classroom_term_id' => $classroomTerm->id,
            'roll_number' => ($data['no_absen'] ?? '') !== '' ? (int) $data['no_absen'] : null,
            'status' => ($data['status_enrollment'] ?? '') ?: 'active',
        ];

        if ($enrollment) {
            $enrollment->update($enrollmentPayload);
            $result->enrollmentsUpdated++;
        } else {
            ClassEnrollment::create([
                'academic_term_id' => $term->id,
                'student_id' => $student->id,
            ] + $enrollmentPayload);
            $result->enrollmentsCreated++;
        }
    }

    /** @param array<string, string> $data */
    private function findAcademicTerm(array $data): ?AcademicTerm
    {
        $semester = $this->normalizeSemester($data['periode']);

        return AcademicTerm::query()
            ->whereHas('academicYear', fn ($query) => $query->where('name', $data['tahun_ajaran']))
            ->where(function ($query) use ($data, $semester): void {
                $query->where('semester', $semester)
                    ->orWhere('name', $data['periode']);
            })
            ->first();
    }

    private function normalizeSemester(string $value): string
    {
        $normalized = Str::of($value)->lower()->trim()->toString();

        return match ($normalized) {
            'ganjil', 'ganil', 'gasal', '1', 'semester 1' => 'ganjil',
            'genap', '2', 'semester 2' => 'genap',
            default => $normalized,
        };
    }

    private function normalizeGenderGroup(?string $value): string
    {
        $normalized = Str::of((string) $value)->lower()->trim()->toString();

        return match ($normalized) {
            'l', 'lk', 'laki-laki', 'ikhwan', 'male' => 'male',
            'p', 'pr', 'perempuan', 'akhwat', 'female' => 'female',
            default => $normalized !== '' ? $normalized : 'mixed',
        };
    }

    private function truthy(?string $value): bool
    {
        return in_array(Str::of((string) $value)->lower()->trim()->toString(), ['1', 'ya', 'yes', 'y', 'true', 'aktif'], true);
    }
}
