<?php

namespace App\Services\Imports;

class ClassEnrollmentImportResult
{
    /** @param array<int, string> $errors */
    public function __construct(
        public int $processedRows = 0,
        public int $classroomsCreated = 0,
        public int $classroomsUpdated = 0,
        public int $classroomTermsCreated = 0,
        public int $classroomTermsUpdated = 0,
        public int $enrollmentsCreated = 0,
        public int $enrollmentsUpdated = 0,
        public array $errors = [],
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function summary(): string
    {
        return sprintf(
            '%d baris diproses. Kelas: %d baru, %d update. Kelas periode: %d baru, %d update. Enrollment: %d baru, %d update.',
            $this->processedRows,
            $this->classroomsCreated,
            $this->classroomsUpdated,
            $this->classroomTermsCreated,
            $this->classroomTermsUpdated,
            $this->enrollmentsCreated,
            $this->enrollmentsUpdated,
        );
    }
}
