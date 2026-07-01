<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\User;
use App\Services\Imports\AttendanceSpreadsheetImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class AttendanceSpreadsheetImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_importer_reads_monthly_sheets_and_creates_attendance_records(): void
    {
        [$classroomTerm, $firstEnrollment, $secondEnrollment] = $this->makeAttendanceClassroom();
        $user = User::factory()->create();

        $path = $this->makeAttendanceWorkbook([
            'JUL-25' => [
                2 => ['D' => '14', 'E' => '15', 'F' => '16'],
                3 => ['B' => 'Ahmad', 'C' => '001', 'D' => 'H', 'E' => 's', 'F' => 'A'],
                4 => ['B' => 'Bilal', 'C' => '002', 'D' => 'I', 'E' => 'L', 'F' => 'H'],
            ],
            'REKAP' => [
                2 => ['B' => 'Ringkasan'],
            ],
        ]);

        $result = app(AttendanceSpreadsheetImporter::class)->import($path, $classroomTerm, $user);

        $this->assertFalse($result->hasErrors(), implode("\n", $result->errors));
        $this->assertSame(1, $result->processedSheets);
        $this->assertSame(2, $result->processedRows);
        $this->assertSame(2, $result->matchedStudents);
        $this->assertSame(6, $result->attendancesCreated);
        $this->assertTrue(StudentAttendance::query()
            ->where('class_enrollment_id', $firstEnrollment->id)
            ->whereDate('attendance_date', '2025-07-15')
            ->where('status', StudentAttendance::STATUS_SICK)
            ->where('input_by', $user->id)
            ->exists());
        $this->assertTrue(StudentAttendance::query()
            ->where('class_enrollment_id', $secondEnrollment->id)
            ->whereDate('attendance_date', '2025-07-15')
            ->where('status', StudentAttendance::STATUS_HOLIDAY)
            ->exists());

        File::delete($path);
    }

    public function test_importer_updates_existing_attendance_and_reports_invalid_data(): void
    {
        [$classroomTerm, $firstEnrollment] = $this->makeAttendanceClassroom();

        StudentAttendance::create([
            'academic_term_id' => $classroomTerm->academic_term_id,
            'classroom_term_id' => $classroomTerm->id,
            'class_enrollment_id' => $firstEnrollment->id,
            'student_id' => $firstEnrollment->student_id,
            'attendance_date' => '2025-07-14',
            'status' => StudentAttendance::STATUS_PRESENT,
        ]);

        $path = $this->makeAttendanceWorkbook([
            'JUL-25' => [
                2 => ['D' => '14', 'E' => '15'],
                3 => ['B' => 'Ahmad', 'C' => '001', 'D' => 'A', 'E' => ''],
                4 => ['B' => 'Tidak Ada', 'C' => '404', 'D' => 'H', 'E' => 'X'],
            ],
        ]);

        $result = app(AttendanceSpreadsheetImporter::class)->import($path, $classroomTerm);

        $this->assertTrue($result->hasErrors());
        $this->assertSame(1, $result->attendancesUpdated);
        $this->assertSame(1, $result->blankCellsSkipped);
        $this->assertSame(1, $result->unknownStudentsSkipped);
        $this->assertSame(0, $result->invalidCodesSkipped);
        $this->assertTrue(StudentAttendance::query()
            ->where('class_enrollment_id', $firstEnrollment->id)
            ->whereDate('attendance_date', '2025-07-14')
            ->where('status', StudentAttendance::STATUS_ABSENT)
            ->exists());

        File::delete($path);
    }

    /** @return array{ClassroomTerm, ClassEnrollment, ClassEnrollment} */
    private function makeAttendanceClassroom(): array
    {
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => 'Semester Ganjil',
            'semester' => 'ganjil',
            'starts_at' => '2025-07-14',
            'ends_at' => '2025-12-20',
            'is_active' => true,
        ]);
        $classroom = Classroom::create(['name' => 'M3 Ikhwan']);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => 'M3 Ikhwan',
        ]);
        $firstStudent = Student::create(['name' => 'Ahmad', 'gender' => 'male', 'nis' => '001']);
        $secondStudent = Student::create(['name' => 'Bilal', 'gender' => 'male', 'nis' => '002']);

        $firstEnrollment = ClassEnrollment::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'student_id' => $firstStudent->id,
            'roll_number' => 1,
            'status' => 'active',
        ]);
        $secondEnrollment = ClassEnrollment::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'student_id' => $secondStudent->id,
            'roll_number' => 2,
            'status' => 'active',
        ]);

        return [$classroomTerm, $firstEnrollment, $secondEnrollment];
    }

    /** @param array<string, array<int, array<string, string>>> $sheets */
    private function makeAttendanceWorkbook(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'attendance-import-').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml(count($sheets)));
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml(array_keys($sheets)));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml(count($sheets)));

        $index = 1;

        foreach ($sheets as $name => $rows) {
            $zip->addFromString("xl/worksheets/sheet{$index}.xml", $this->worksheetXml($rows));
            $index++;
        }

        $zip->close();

        return $path;
    }

    private function contentTypesXml(int $sheetCount): string
    {
        $overrides = '';

        for ($index = 1; $index <= $sheetCount; $index++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$index.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .$overrides
            .'</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    /** @param array<int, string> $sheetNames */
    private function workbookXml(array $sheetNames): string
    {
        $sheetsXml = '';

        foreach (array_values($sheetNames) as $index => $name) {
            $sheetId = $index + 1;
            $sheetsXml .= '<sheet name="'.$this->xml($name).'" sheetId="'.$sheetId.'" r:id="rId'.$sheetId.'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheetsXml.'</sheets>'
            .'</workbook>';
    }

    private function workbookRelsXml(int $sheetCount): string
    {
        $relationships = '';

        for ($index = 1; $index <= $sheetCount; $index++) {
            $relationships .= '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$relationships
            .'</Relationships>';
    }

    /** @param array<int, array<string, string>> $rows */
    private function worksheetXml(array $rows): string
    {
        ksort($rows);
        $rowsXml = '';

        foreach ($rows as $rowNumber => $cells) {
            ksort($cells);
            $cellsXml = '';

            foreach ($cells as $column => $value) {
                $reference = $column.$rowNumber;
                $cellsXml .= is_numeric($value)
                    ? '<c r="'.$reference.'"><v>'.$this->xml($value).'</v></c>'
                    : '<c r="'.$reference.'" t="inlineStr"><is><t>'.$this->xml($value).'</t></is></c>';
            }

            $rowsXml .= '<row r="'.$rowNumber.'">'.$cellsXml.'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.$rowsXml.'</sheetData>'
            .'</worksheet>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
