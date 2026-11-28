<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ClassroomTerm;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TahfidzReportWorkbook
{
    private const SHEET = 'Nilai Tahfidz';

    private const FORMAT = 'Tahfidz Rapor v1';

    private const HEADER_ROW = 4;

    public function create(AcademicTerm $term, ?ClassroomTerm $classroom, Collection $enrollments, Collection $versions): Spreadsheet
    {
        $workbook = new Spreadsheet;
        $sheet = $workbook->getActiveSheet()->setTitle(self::SHEET);
        $sheet->mergeCells('A1:H1')->mergeCells('A2:H2')->mergeCells('A3:H3');
        $sheet->setCellValueExplicit(
            'A1',
            implode(' · ', array_filter(['Nilai Tahfidz Rapor', $term->academicYear?->name, $term->name, $classroom?->name ?? 'Semua Kelas'])),
            DataType::TYPE_STRING
        );
        $sheet->setCellValue('A2', 'Isi juz yang diujikan dan nilai 0–100. Kolom kosong mempertahankan nilai yang sudah ada.');
        $sheet->setCellValue('A3', 'Biarkan identitas dan judul kolom. Simpan sebagai .xlsx, lalu unggah dari portal Kabag Tahfidz.');
        $sheet->setCellValue('I1', self::FORMAT)->setCellValue('J1', $term->id)->setCellValue('K1', $classroom?->id ?? 0);
        $sheet->fromArray($this->headings(), null, 'A'.self::HEADER_ROW);

        $row = self::HEADER_ROW;
        foreach ($enrollments as $enrollment) {
            $row++;
            $sheet->setCellValueExplicit('A'.$row, (string) $enrollment->student->nis, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B'.$row, $enrollment->student->name, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C'.$row, $enrollment->classroomTerm->name, DataType::TYPE_STRING);
            $sheet->setCellValue('I'.$row, $enrollment->id);
            $sheet->setCellValue('J'.$row, $enrollment->classroom_term_id);
            $sheet->setCellValue('K'.$row, (int) $versions->get($enrollment->student_id, 0));
        }

        foreach (['A' => 15, 'B' => 32, 'C' => 23, 'D' => 22, 'E' => 22, 'F' => 12, 'G' => 12, 'H' => 12] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        foreach (['I', 'J', 'K'] as $column) {
            $sheet->getColumnDimension($column)->setVisible(false);
        }
        $sheet->getStyle('A1:H1')->getFont()->setBold(true)->setSize(14);
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(22);
        $header = $sheet->getStyle('A'.self::HEADER_ROW.':H'.self::HEADER_ROW);
        $header->getFont()->setBold(true)->setColor(new Color(Color::COLOR_WHITE));
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('155E52');
        if ($row > self::HEADER_ROW) {
            $first = self::HEADER_ROW + 1;
            $sheet->getStyle("D{$first}:H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF4CE');
            foreach (['A', 'D'] as $textColumn) {
                $sheet->getStyle("{$textColumn}{$first}:{$textColumn}{$row}")->getNumberFormat()->setFormatCode('@');
            }
            $sheet->setAutoFilter('A'.self::HEADER_ROW.':K'.$row);
        }
        $sheet->freezePane('D'.(self::HEADER_ROW + 1));

        return $workbook;
    }

    public function read(string $path, int $termId): array
    {
        try {
            $workbook = (new Xlsx)->setReadDataOnly(true)->setLoadSheetsOnly(self::SHEET)->load($path);
        } catch (ReaderException) {
            throw ValidationException::withMessages(['file' => 'Berkas Excel tidak dapat dibaca. Gunakan template .xlsx dari portal.']);
        }

        try {
            $sheet = $workbook->getSheetByName(self::SHEET);
            $headings = $sheet?->rangeToArray('A'.self::HEADER_ROW.':K'.self::HEADER_ROW, null, false, false)[0];
            if (! $sheet || $sheet->getCell('I1')->getValue() !== self::FORMAT || $headings !== $this->headings()) {
                throw ValidationException::withMessages(['file' => 'Format template tidak sesuai. Unduh ulang dari portal dan pertahankan judul kolom.']);
            }
            $classId = $this->readId($sheet->getCell('K1')->getValue(), 0);
            if ($this->readId($sheet->getCell('J1')->getValue(), 1) !== $termId || $classId === null) {
                throw ValidationException::withMessages(['file' => 'Semester template berbeda dari semester yang dipilih.']);
            }

            $rows = $this->readRows($sheet);
            if ($rows->isEmpty()) {
                throw ValidationException::withMessages(['file' => 'Isi minimal satu kolom nilai atau juz sebelum mengunggah.']);
            }

            return [$classId, $rows];
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    private function readRows(Worksheet $sheet): Collection
    {
        $rows = collect();
        $ruleLabels = TahfidzReportWorkflow::FIELDS;
        $last = $sheet->getHighestDataRow();
        for ($number = self::HEADER_ROW + 1; $number <= $last; $number++) {
            $values = [];
            foreach (array_keys($ruleLabels) as $offset => $field) {
                $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($offset + 4).$number);
                $value = $cell->getValue();
                if ($cell->getDataType() === DataType::TYPE_FORMULA || ! (is_scalar($value) || $value === null)) {
                    throw ValidationException::withMessages(['file' => "Baris {$number}: masukkan nilai langsung pada kolom {$ruleLabels[$field]}."]);
                }
                if (! is_bool($value) && ($value === null || trim((string) $value) === '')) {
                    continue;
                }
                if ($field === 'juz_tested' && (is_string($value) || is_numeric($value))) {
                    $value = trim((string) $value);
                } elseif (is_string($value)) {
                    $value = str_replace(',', '.', trim($value));
                }
                $values[$field] = $value;
            }
            if ($values === []) {
                continue;
            }
            $validator = Validator::make($values, TahfidzReportWorkflow::valueRules());
            if ($validator->fails()) {
                $field = array_key_first($validator->errors()->getMessages());
                $rule = $field === 'juz_tested' ? 'harus berupa teks maksimal 255 karakter' : 'harus berupa angka 0–100';
                throw ValidationException::withMessages(['file' => "Baris {$number}: {$ruleLabels[$field]} {$rule}."]);
            }

            $id = $this->readId($sheet->getCell('I'.$number)->getValue(), 1);
            $classId = $this->readId($sheet->getCell('J'.$number)->getValue(), 1);
            $version = $this->readId($sheet->getCell('K'.$number)->getValue(), 0);
            $nis = $sheet->getCell('A'.$number)->getValue();
            if ($id === null || $classId === null || $version === null || ! (is_scalar($nis) || $nis === null) || $rows->has($id)) {
                throw ValidationException::withMessages(['file' => "Baris {$number}: identitas santri tidak valid atau muncul lebih dari sekali."]);
            }
            $rows->put($id, ['number' => $number, 'class_id' => $classId, 'version' => $version, 'nis' => (string) $nis, 'values' => $values]);
        }

        return $rows;
    }

    private function headings(): array
    {
        return ['NIS', 'Nama Santri', 'Kelas', ...array_values(TahfidzReportWorkflow::FIELDS), 'ID Pendaftaran', 'ID Kelas', 'Versi'];
    }

    private function readId(mixed $value, int $minimum): ?int
    {
        if ((! is_int($value) && ! is_float($value) && ! is_string($value)) || ! ctype_digit((string) $value)) {
            return null;
        }

        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum]]);

        return $number === false ? null : $number;
    }
}
