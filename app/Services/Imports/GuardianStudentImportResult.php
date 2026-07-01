<?php

namespace App\Services\Imports;

class GuardianStudentImportResult
{
    /** @param array<int, string> $errors */
    public function __construct(
        public int $processedRows = 0,
        public int $studentsCreated = 0,
        public int $studentsUpdated = 0,
        public int $guardiansCreated = 0,
        public int $guardiansUpdated = 0,
        public int $usersCreated = 0,
        public int $usersUpdated = 0,
        public int $relationsCreated = 0,
        public int $relationsUpdated = 0,
        public array $errors = [],
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function summary(): string
    {
        return sprintf(
            '%d baris diproses. Siswa: %d baru, %d update. Wali: %d baru, %d update. Akun: %d baru, %d update. Relasi: %d baru, %d update.',
            $this->processedRows,
            $this->studentsCreated,
            $this->studentsUpdated,
            $this->guardiansCreated,
            $this->guardiansUpdated,
            $this->usersCreated,
            $this->usersUpdated,
            $this->relationsCreated,
            $this->relationsUpdated,
        );
    }
}
