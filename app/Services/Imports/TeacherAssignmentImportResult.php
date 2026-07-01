<?php

namespace App\Services\Imports;

class TeacherAssignmentImportResult
{
    /** @param array<int, string> $errors */
    public function __construct(
        public int $processedRows = 0,
        public int $teachersCreated = 0,
        public int $teachersUpdated = 0,
        public int $usersCreated = 0,
        public int $usersUpdated = 0,
        public int $teacherRolesCreated = 0,
        public int $classSubjectsCreated = 0,
        public int $assignmentsCreated = 0,
        public int $assignmentsUpdated = 0,
        public array $errors = [],
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function summary(): string
    {
        return sprintf(
            '%d baris diproses. Guru: %d baru, %d update. Akun: %d baru, %d update. Role guru: %d baru. Mapel kelas: %d baru. Penugasan: %d baru, %d update.',
            $this->processedRows,
            $this->teachersCreated,
            $this->teachersUpdated,
            $this->usersCreated,
            $this->usersUpdated,
            $this->teacherRolesCreated,
            $this->classSubjectsCreated,
            $this->assignmentsCreated,
            $this->assignmentsUpdated,
        );
    }
}
