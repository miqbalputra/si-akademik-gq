<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\School;
use App\Models\Student;
use App\Models\TahfidzHalaqah;
use App\Models\TahfidzHalaqahMember;
use App\Models\TahfidzUasCategory;
use App\Models\TahfidzUasDay;
use App\Models\TahfidzUasScore;
use App\Models\TahfidzWeek;
use App\Models\TahfidzWeeklyScore;
use App\Services\TahfidzSabaqParser;
use App\Services\TahfidzScoreCalculator;
use App\Services\TahfidzUasCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TahfidzScoreCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_sabaq_parser_converts_muka_and_baris(): void
    {
        $parser = new TahfidzSabaqParser;

        $this->assertSame(8, $parser->parseToBaris('8 Baris'));
        $this->assertSame(22, $parser->parseToBaris('1 Muka 7 Baris'));
        $this->assertSame(30, $parser->parseToBaris('2 Muka'));
        $this->assertSame(15, $parser->parseToBaris('1 Muka'));
        $this->assertSame(15, $parser->parseToBaris('1 Halaman'));
        $this->assertNull($parser->parseToBaris('Muraja\'ah'));
        $this->assertNull($parser->parseToBaris('Libur'));
        $this->assertNull($parser->parseToBaris(null));
    }

    public function test_sabaq_parser_formats_baris_back_to_text(): void
    {
        $parser = new TahfidzSabaqParser;

        $this->assertSame('8 Baris', $parser->formatFromBaris(8));
        $this->assertSame('1 Muka', $parser->formatFromBaris(15));
        $this->assertSame('2 Muka 7 Baris', $parser->formatFromBaris(37));
        $this->assertSame('3 Muka', $parser->formatFromBaris(45));
    }

    public function test_category_from_score(): void
    {
        $calc = new TahfidzScoreCalculator(new TahfidzSabaqParser);

        $this->assertSame('L', $calc->category(95));
        $this->assertSame('HL', $calc->category(75));
        $this->assertSame('KL', $calc->category(60));
        $this->assertSame('BL', $calc->category(40));
        $this->assertNull($calc->category(null));
    }

    public function test_uas_calculator_calculates_final_score(): void
    {
        [$termId, $studentId] = $this->makeUasContext();

        $calc = new TahfidzUasCalculator;
        $result = $calc->calculateForStudent($termId, $studentId);

        $this->assertTrue($result->is_complete);
        $this->assertSame('94.00', $result->final_score);
        $this->assertSame('Mumtaz', $result->predicate);
    }

    public function test_uas_predicate_ranges(): void
    {
        $calc = new TahfidzUasCalculator;

        $this->assertSame('Mumtaz', $calc->predicate(95));
        $this->assertSame('Jayyid Jiddan', $calc->predicate(85));
        $this->assertSame('Jayyid', $calc->predicate(75));
        $this->assertSame('Maqbul', $calc->predicate(65));
        $this->assertSame('Daif', $calc->predicate(50));
        $this->assertNull($calc->predicate(null));
    }

    private function makeUasContext(): array
    {
        $school = School::create(['name' => 'Test']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil']);
        $student = Student::create(['name' => 'Test Santri', 'gender' => 'male', 'nis' => 'TST-001', 'status' => 'active']);

        $categories = [
            ['kelancaran', 'KELANCARAN', 30, 10],
            ['makhroj', 'MAKHROJ', 20, 20],
            ['tajwid', 'TAJWID', 30, 30],
            ['sifat', 'SIFAT', 20, 40],
        ];

        foreach ($categories as [$code, $name, $max, $sort]) {
            TahfidzUasCategory::create([
                'academic_term_id' => $term->id,
                'code' => $code,
                'name' => $name,
                'max_score' => $max,
                'sort_order' => $sort,
                'is_active' => true,
            ]);
        }

        // 2 days
        for ($d = 1; $d <= 2; $d++) {
            TahfidzUasDay::create([
                'academic_term_id' => $term->id,
                'day_number' => $d,
                'label' => "Hari $d",
                'is_active' => true,
            ]);
        }

        $days = TahfidzUasDay::where('academic_term_id', $term->id)->orderBy('day_number')->get();
        $cats = TahfidzUasCategory::where('academic_term_id', $term->id)->orderBy('sort_order')->get();

        // Day 1: K=28, M=18, T=27, S=19 = 92
        // Day 2: K=29, M=19, T=28, S=20 = 96
        // Average = 94
        $scores = [
            [28, 18, 27, 19],
            [29, 19, 28, 20],
        ];

        foreach ($days as $di => $day) {
            foreach ($cats as $ci => $cat) {
                TahfidzUasScore::create([
                    'academic_term_id' => $term->id,
                    'tahfidz_uas_day_id' => $day->id,
                    'tahfidz_uas_category_id' => $cat->id,
                    'student_id' => $student->id,
                    'score' => $scores[$di][$ci],
                ]);
            }
        }

        // Actual: day1 = 28+18+27+19 = 92, day2 = 29+19+28+20 = 96, avg = 94
        return [$term->id, $student->id];
    }
}