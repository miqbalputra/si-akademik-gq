<?php

namespace App\Services\Imports;

class AttendanceSpreadsheetImportResult
{
    /** @param array<int, string> $errors */
    public function __construct(
        public int $processedSheets = 0,
        public int $processedRows = 0,
        public int $matchedStudents = 0,
        public int $attendancesCreated = 0,
        public int $attendancesUpdated = 0,
        public int $blankCellsSkipped = 0,
        public int $unknownStudentsSkipped = 0,
        public int $invalidCodesSkipped = 0,
        public array $errors = [],
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function summary(): string
    {
        return sprintf(
            '%d sheet diproses. %d baris santri dibaca, %d santri cocok. Presensi: %d baru, %d update. Sel kosong dilewati: %d. NIS tidak cocok: %d. Kode tidak valid: %d.',
            $this->processedSheets,
            $this->processedRows,
            $this->matchedStudents,
            $this->attendancesCreated,
            $this->attendancesUpdated,
            $this->blankCellsSkipped,
            $this->unknownStudentsSkipped,
            $this->invalidCodesSkipped,
        );
    }
}
