<?php

namespace App\Services\Imports;

use App\Models\ClassEnrollment;
use App\Models\ClassroomTerm;
use App\Models\StudentAttendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class AttendanceSpreadsheetImporter
{
    /** @var array<string, int> */
    private const SHEET_MONTHS = [
        'JAN' => 1,
        'FEB' => 2,
        'MAR' => 3,
        'APR' => 4,
        'MEI' => 5,
        'MAY' => 5,
        'JUN' => 6,
        'JUL' => 7,
        'AGU' => 8,
        'AUG' => 8,
        'SEP' => 9,
        'OKT' => 10,
        'OCT' => 10,
        'NOV' => 11,
        'DES' => 12,
        'DEC' => 12,
    ];

    public function import(string $path, ClassroomTerm $classroomTerm, ?User $actor = null): AttendanceSpreadsheetImportResult
    {
        $result = new AttendanceSpreadsheetImportResult;

        if (! is_file($path) || ! is_readable($path)) {
            $result->errors[] = 'File absensi tidak bisa dibaca.';

            return $result;
        }

        if (! class_exists(ZipArchive::class)) {
            $result->errors[] = 'Ekstensi ZIP pada server belum aktif, jadi file XLSX belum bisa diimport.';

            return $result;
        }

        $classroomTerm->loadMissing('academicTerm');

        if (! $classroomTerm->academicTerm) {
            $result->errors[] = 'Kelas periode belum terhubung ke tahun/periode ajaran.';

            return $result;
        }

        $enrollments = ClassEnrollment::query()
            ->with('student')
            ->where('classroom_term_id', $classroomTerm->id)
            ->where('status', 'active')
            ->get();

        if ($enrollments->isEmpty()) {
            $result->errors[] = 'Kelas ini belum punya data santri aktif untuk dicocokkan.';

            return $result;
        }

        $enrollmentsByNis = $enrollments->filter(fn (ClassEnrollment $enrollment) => filled($enrollment->student?->nis))
            ->keyBy(fn (ClassEnrollment $enrollment) => trim((string) $enrollment->student?->nis));

        if ($enrollmentsByNis->isEmpty()) {
            $result->errors[] = 'Santri di kelas ini belum memiliki NIS, jadi file absensi belum bisa dicocokkan.';

            return $result;
        }

        try {
            $workbook = $this->openWorkbook($path);
        } catch (RuntimeException $exception) {
            $result->errors[] = $exception->getMessage();

            return $result;
        }

        DB::transaction(function () use ($actor, $classroomTerm, $enrollmentsByNis, $result, $workbook): void {
            $seenStudents = [];

            foreach ($workbook['worksheets'] as $worksheet) {
                $month = $this->resolveMonthFromSheetName($worksheet['name']);

                if (! $month) {
                    continue;
                }

                $dateColumns = $this->resolveDateColumns($worksheet['cells'], $month);

                if ($dateColumns === []) {
                    continue;
                }

                $result->processedSheets++;

                foreach ($this->studentRowNumbers($worksheet['cells']) as $rowNumber) {
                    $nis = trim((string) ($worksheet['cells']['C'.$rowNumber] ?? ''));

                    if ($nis === '') {
                        continue;
                    }

                    $result->processedRows++;
                    $enrollment = $enrollmentsByNis->get($nis);

                    if (! $enrollment) {
                        $result->unknownStudentsSkipped++;
                        $result->errors[] = "Sheet {$worksheet['name']} baris {$rowNumber}: NIS '{$nis}' tidak ditemukan di kelas {$classroomTerm->name}.";

                        continue;
                    }

                    $seenStudents[$enrollment->id] = true;

                    foreach ($dateColumns as $column => $attendanceDate) {
                        $rawCode = trim((string) ($worksheet['cells'][$column.$rowNumber] ?? ''));

                        if ($rawCode === '') {
                            $result->blankCellsSkipped++;

                            continue;
                        }

                        if (! in_array($rawCode, StudentAttendance::acceptedCodes(), true)) {
                            $result->invalidCodesSkipped++;
                            $result->errors[] = "Sheet {$worksheet['name']} baris {$rowNumber} tanggal {$attendanceDate->toDateString()}: kode '{$rawCode}' tidak valid.";

                            continue;
                        }

                        $existing = StudentAttendance::query()
                            ->where('class_enrollment_id', $enrollment->id)
                            ->whereDate('attendance_date', $attendanceDate->toDateString())
                            ->first();

                        $payload = [
                            'academic_term_id' => $classroomTerm->academic_term_id,
                            'classroom_term_id' => $classroomTerm->id,
                            'student_id' => $enrollment->student_id,
                            'attendance_date' => $attendanceDate->toDateString(),
                            'status' => StudentAttendance::statusFromCode($rawCode),
                            'input_by' => $actor?->id,
                        ];

                        if ($existing) {
                            $existing->update($payload);
                            $result->attendancesUpdated++;
                        } else {
                            StudentAttendance::create([
                                'class_enrollment_id' => $enrollment->id,
                                ...$payload,
                            ]);
                            $result->attendancesCreated++;
                        }
                    }
                }
            }

            $result->matchedStudents = count($seenStudents);
        });

        if ($result->processedSheets === 0) {
            $result->errors[] = 'Tidak ada sheet bulanan yang cocok. Pastikan nama sheet seperti JUL-25, AGU-25, dan seterusnya.';
        }

        return $result;
    }

    /** @return array{shared_strings: array<int, string>, worksheets: array<int, array{name: string, cells: array<string, string>}>} */
    private function openWorkbook(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('File XLSX gagal dibuka.');
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $workbookXml = $zip->getFromName('xl/workbook.xml');
            $relationshipXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

            if ($workbookXml === false || $relationshipXml === false) {
                throw new RuntimeException('Struktur workbook XLSX tidak lengkap.');
            }

            $workbook = new SimpleXMLElement($workbookXml);
            $relationships = new SimpleXMLElement($relationshipXml);
            $sheetTargets = [];

            foreach ($relationships->Relationship as $relationship) {
                $attributes = $relationship->attributes();
                $sheetTargets[(string) $attributes['Id']] = 'xl/'.ltrim((string) $attributes['Target'], '/');
            }

            $workbook->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $workbook->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $worksheets = [];

            foreach ($workbook->xpath('//main:sheets/main:sheet') ?: [] as $sheet) {
                $attributes = $sheet->attributes();
                $relation = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $relationshipId = (string) ($relation['id'] ?? '');
                $target = $sheetTargets[$relationshipId] ?? null;

                if (! $target) {
                    continue;
                }

                $sheetXml = $zip->getFromName($target);

                if ($sheetXml === false) {
                    continue;
                }

                $worksheets[] = [
                    'name' => (string) $attributes['name'],
                    'cells' => $this->readWorksheetCells($sheetXml, $sharedStrings),
                ];
            }

            return [
                'shared_strings' => $sharedStrings,
                'worksheets' => $worksheets,
            ];
        } finally {
            $zip->close();
        }
    }

    /** @return array<int, string> */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');

        if ($sharedStringsXml === false) {
            return [];
        }

        $xml = new SimpleXMLElement($sharedStringsXml);
        $xml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = [];

        foreach ($xml->xpath('//main:si') ?: [] as $item) {
            $item->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $parts = $item->xpath('.//main:t') ?: [];
            $strings[] = collect($parts)->map(fn ($part) => (string) $part)->implode('');
        }

        return $strings;
    }

    /** @param array<int, string> $sharedStrings
     *  @return array<string, string>
     */
    private function readWorksheetCells(string $sheetXml, array $sharedStrings): array
    {
        $xml = new SimpleXMLElement($sheetXml);
        $xml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $cells = [];

        foreach ($xml->xpath('//main:sheetData/main:row/main:c') ?: [] as $cell) {
            $attributes = $cell->attributes();
            $reference = (string) ($attributes['r'] ?? '');
            $type = (string) ($attributes['t'] ?? '');

            if ($reference === '') {
                continue;
            }

            $value = match ($type) {
                's' => $sharedStrings[(int) ($cell->v ?? 0)] ?? '',
                'inlineStr' => (function () use ($cell): string {
                    $cell->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

                    return collect($cell->xpath('.//main:t') ?: [])
                        ->map(fn ($part) => (string) $part)
                        ->implode('');
                })(),
                default => (string) ($cell->v ?? ''),
            };

            $cells[$reference] = trim($value);
        }

        return $cells;
    }

    private function resolveMonthFromSheetName(string $sheetName): ?CarbonImmutable
    {
        $normalized = Str::upper(trim($sheetName));

        if (! preg_match('/^([A-Z]{3})[-_ ]?(\d{2,4})$/', $normalized, $matches)) {
            return null;
        }

        $month = self::SHEET_MONTHS[$matches[1]] ?? null;

        if (! $month) {
            return null;
        }

        $year = (int) $matches[2];
        $year += $year < 100 ? 2000 : 0;

        return CarbonImmutable::create($year, $month, 1, 0, 0, 0);
    }

    /**
     * @param  array<string, string>  $cells
     * @return array<string, CarbonImmutable>
     */
    private function resolveDateColumns(array $cells, CarbonImmutable $sheetMonth): array
    {
        $columns = [];

        foreach ($cells as $reference => $value) {
            if (! preg_match('/^([A-Z]+)2$/', $reference, $matches)) {
                continue;
            }

            $column = $matches[1];

            if ($this->columnIndex($column) < $this->columnIndex('D')) {
                continue;
            }

            if (! ctype_digit($value)) {
                continue;
            }

            $day = (int) $value;

            if ($day < 1 || $day > 31) {
                continue;
            }

            try {
                $columns[$column] = $sheetMonth->day($day);
            } catch (\Throwable) {
                continue;
            }
        }

        ksort($columns);

        return $columns;
    }

    /**
     * @param  array<string, string>  $cells
     * @return Collection<int, int>
     */
    private function studentRowNumbers(array $cells): Collection
    {
        return collect(array_keys($cells))
            ->filter(fn (string $reference) => preg_match('/^C(\d+)$/', $reference) === 1)
            ->map(fn (string $reference) => (int) substr($reference, 1))
            ->filter(fn (int $rowNumber) => $rowNumber >= 3)
            ->sort()
            ->values();
    }

    private function columnIndex(string $column): int
    {
        $index = 0;

        foreach (str_split($column) as $character) {
            $index = ($index * 26) + (ord($character) - 64);
        }

        return $index;
    }
}
