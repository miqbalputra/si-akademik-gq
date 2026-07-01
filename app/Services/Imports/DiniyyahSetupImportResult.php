<?php

namespace App\Services\Imports;

class DiniyyahSetupImportResult
{
    /** @param array<int, string> $errors */
    public function __construct(
        public int $processedRows = 0,
        public int $subjectsCreated = 0,
        public int $subjectsUpdated = 0,
        public int $classSubjectsCreated = 0,
        public int $classSubjectsUpdated = 0,
        public int $assessmentSetsCreated = 0,
        public int $assessmentSetsUpdated = 0,
        public int $componentsCreated = 0,
        public int $componentsUpdated = 0,
        public int $defaultComponentSetsApplied = 0,
        public array $errors = [],
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function summary(): string
    {
        return sprintf(
            '%d baris diproses. Mapel: %d baru, %d update. Mapel kelas: %d baru, %d update. Set nilai: %d baru, %d update. Komponen: %d baru, %d update. Default komponen diterapkan: %d.',
            $this->processedRows,
            $this->subjectsCreated,
            $this->subjectsUpdated,
            $this->classSubjectsCreated,
            $this->classSubjectsUpdated,
            $this->assessmentSetsCreated,
            $this->assessmentSetsUpdated,
            $this->componentsCreated,
            $this->componentsUpdated,
            $this->defaultComponentSetsApplied,
        );
    }
}
