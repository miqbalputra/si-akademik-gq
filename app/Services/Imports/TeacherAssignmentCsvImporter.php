<?php

namespace App\Services\Imports;

use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class TeacherAssignmentCsvImporter
{
    /** @var array<int, string> */
    private const REQUIRED_HEADERS = [
        'nama_guru',
        'email',
    ];

    public function import(string $path, ?User $assignedBy = null): TeacherAssignmentImportResult
    {
        $result = new TeacherAssignmentImportResult;

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

        foreach (self::REQUIRED_HEADERS as $requiredHeader) {
            if (! in_array($requiredHeader, $headers, true)) {
                $result->errors[] = "Kolom wajib '{$requiredHeader}' belum ada.";
            }
        }

        if ($headers === []) {
            $result->errors[] = 'Header CSV kosong.';
        }

        if ($result->hasErrors()) {
            fclose($handle);

            return $result;
        }

        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);

        DB::transaction(function () use ($handle, $headers, $result, $assignedBy): void {
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

                $teacher = $this->importTeacher($data, $result);

                if ($this->truthy($data['buat_akun'] ?? 'ya')) {
                    $user = $this->syncTeacherUser($teacher, $data, $result);
                    $teacher->forceFill(['user_id' => $user->id])->save();
                }

                $this->syncTeacherRole($teacher, $data['tugas'] ?? 'guru_diniyyah', $result);

                if (($data['kelas_periode'] ?? '') !== '' || ($data['kode_mapel'] ?? '') !== '' || ($data['nama_mapel'] ?? '') !== '') {
                    $this->importDiniyyahAssignment($teacher, $data, $assignedBy, $result, $lineNumber);
                }

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

        if (($data['kelas_periode'] ?? '') !== '' && (($data['kode_mapel'] ?? '') === '' && ($data['nama_mapel'] ?? '') === '')) {
            $errors[] = "Baris {$lineNumber}: kode_mapel atau nama_mapel wajib diisi jika kelas_periode diisi.";
        }

        if ((($data['kode_mapel'] ?? '') !== '' || ($data['nama_mapel'] ?? '') !== '') && ($data['kelas_periode'] ?? '') === '') {
            $errors[] = "Baris {$lineNumber}: kelas_periode wajib diisi jika mapel diisi.";
        }

        return $errors;
    }

    /** @param array<string, string> $data */
    private function importTeacher(array $data, TeacherAssignmentImportResult $result): Teacher
    {
        $teacher = Teacher::withTrashed()
            ->where('email', $data['email'])
            ->first();

        $payload = [
            'name' => $data['nama_guru'],
            'gender' => $this->normalizeGender($data['jenis_kelamin'] ?? ''),
            'phone' => ($data['no_hp'] ?? '') ?: null,
            'whatsapp' => ($data['whatsapp'] ?? '') ?: (($data['no_hp'] ?? '') ?: null),
            'email' => $data['email'],
            'address' => ($data['alamat'] ?? '') ?: null,
            'started_at' => $this->parseDate($data['tanggal_bertugas'] ?? ''),
            'status' => ($data['status'] ?? '') ?: 'active',
        ];

        if ($teacher) {
            $teacher->restore();
            $teacher->update($payload);
            $result->teachersUpdated++;

            return $teacher;
        }

        $result->teachersCreated++;

        return Teacher::create($payload);
    }

    /** @param array<string, string> $data */
    private function syncTeacherUser(Teacher $teacher, array $data, TeacherAssignmentImportResult $result): User
    {
        $user = $teacher->user ?: User::where('email', $data['email'])->first();
        $payload = [
            'name' => $teacher->name,
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
                'password' => ($data['password'] ?? '') ?: $this->defaultPassword($data),
            ]);
            $result->usersCreated++;
        }

        $user->assignRole('guru');

        return $user;
    }

    private function syncTeacherRole(Teacher $teacher, string $roleType, TeacherAssignmentImportResult $result): void
    {
        $roleType = Str::of($roleType)->lower()->trim()->replace(' ', '_')->toString() ?: 'guru_diniyyah';

        $role = $teacher->teacherRoles()->firstOrCreate(['role_type' => $roleType]);

        if ($role->wasRecentlyCreated) {
            $result->teacherRolesCreated++;
        }
    }

    /** @param array<string, string> $data */
    private function importDiniyyahAssignment(Teacher $teacher, array $data, ?User $assignedBy, TeacherAssignmentImportResult $result, int $lineNumber): void
    {
        $classroomTerm = ClassroomTerm::where('name', $data['kelas_periode'])->first();

        if (! $classroomTerm) {
            $result->errors[] = "Baris {$lineNumber}: kelas_periode '{$data['kelas_periode']}' tidak ditemukan.";

            return;
        }

        $subject = DiniyyahSubject::query()
            ->when(($data['kode_mapel'] ?? '') !== '', fn ($query) => $query->where('code', $data['kode_mapel']))
            ->when(($data['kode_mapel'] ?? '') === '' && ($data['nama_mapel'] ?? '') !== '', fn ($query) => $query->where('name', $data['nama_mapel']))
            ->first();

        if (! $subject) {
            $label = ($data['kode_mapel'] ?? '') ?: ($data['nama_mapel'] ?? '');
            $result->errors[] = "Baris {$lineNumber}: mapel '{$label}' tidak ditemukan.";

            return;
        }

        $classSubject = DiniyyahClassSubject::withTrashed()->firstOrCreate(
            [
                'classroom_term_id' => $classroomTerm->id,
                'subject_id' => $subject->id,
            ],
            [
                'assessment_method' => ($data['assessment_method'] ?? '') ?: $subject->default_assessment_method,
                'kkm' => ($data['kkm'] ?? '') !== '' ? (float) $data['kkm'] : null,
                'daily_weight' => ($data['bobot_harian'] ?? '') !== '' ? (int) $data['bobot_harian'] : null,
                'exam_weight' => ($data['bobot_ujian'] ?? '') !== '' ? (int) $data['bobot_ujian'] : null,
                'appears_on_ledger' => $this->truthy($data['masuk_leger'] ?? '1'),
                'appears_on_report' => $this->truthy($data['masuk_rapor'] ?? '1'),
                'is_active' => true,
                'created_by' => $assignedBy?->id,
                'updated_by' => $assignedBy?->id,
            ],
        );

        if ($classSubject->trashed()) {
            $classSubject->restore();
        }

        if ($classSubject->wasRecentlyCreated) {
            $result->classSubjectsCreated++;
        }

        $assignment = $classSubject->teacherAssignments()->updateOrCreate(
            ['teacher_id' => $teacher->id],
            [
                'assignment_role' => ($data['assignment_role'] ?? '') ?: 'primary',
                'starts_at' => $this->parseDate($data['mulai_mengajar'] ?? ''),
                'ends_at' => $this->parseDate($data['selesai_mengajar'] ?? ''),
                'assigned_by' => $assignedBy?->id,
            ],
        );

        $assignment->wasRecentlyCreated ? $result->assignmentsCreated++ : $result->assignmentsUpdated++;
    }

    /** @param array<string, string> $data */
    private function defaultPassword(array $data): string
    {
        return Str::before($data['email'], '@').'-guru';
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

    private function parseDate(?string $value): ?string
    {
        if (trim((string) $value) === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    private function truthy(?string $value): bool
    {
        return in_array(Str::of((string) $value)->lower()->trim()->toString(), ['1', 'ya', 'yes', 'y', 'true', 'aktif'], true);
    }
}
