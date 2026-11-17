<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\ClassRankingReport;
use App\Services\Exports\ClassRankingExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminClassRankingTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        foreach (['admin', 'guru', 'wali_santri', 'kepala_sekolah'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil', 'is_active' => true]);
        $classes = [];
        foreach (['A Ikhwan', 'B Akhwat', 'C Belum Ada Leger'] as $name) {
            $room = Classroom::create(['name' => $name]);
            $classes[] = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $room->id, 'name' => $name]);
        }
        foreach (array_slice($classes, 0, 2) as $i => $class) {
            $snapshot = DiniyyahLedgerSnapshot::create(['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'title' => 'Leger '.$class->name, 'status' => $i ? 'draft' : 'locked', 'generated_at' => now(), 'snapshot_data' => ['layout_version' => $i ? 'diniyyah-v1' : 'combined-v1']]);
            foreach ([['Santri Urutan Dua', 2, 999.5, true], ['Santri Urutan Satu', 1, 1000, true], ['Santri Belum Lengkap', null, null, true], ['Santri Arsip', 3, 900, false]] as $n => [$name, $rank, $total, $active]) {
                $student = Student::create(['name' => $name.' '.$class->name, 'nis' => '000'.($i * 10 + $n), 'gender' => $i ? 'female' : 'male', 'status' => $active ? 'active' : 'inactive']);
                $enrollment = ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'student_id' => $student->id, 'status' => 'active']);
                $row = $snapshot->rows()->create(['class_enrollment_id' => $enrollment->id, 'row_number' => $n + 1, 'student_name' => $student->name, 'student_nis' => $student->nis, 'total_diniyyah_score' => $total === null ? null : 170, 'average_diniyyah_score' => $total === null ? null : 85, 'rank_in_class' => $rank]);
                if (! $i) {
                    foreach (['combined_total' => $total, 'combined_average' => $total === null ? null : 99.95, 'combined_rank' => $rank, 'tahfidz_uas' => 0, 'tahfidz_sabaq' => 80, 'tahfidz_sabqi' => 81, 'tahfidz_manzil' => 82] as $key => $value) {
                        $row->cells()->create(['column_key' => $key, 'label' => $key, 'source_type' => str_starts_with($key, 'combined') ? 'combined_summary' : 'tahfidz_snapshot', 'value_numeric' => $value]);
                    }
                }
            }
        }

        return [$admin, $term, $classes];
    }

    public function test_admin_without_homeroom_can_view_all_classes_but_other_roles_cannot(): void
    {
        [$admin, $term, $classes] = $this->context();
        $this->actingAs($admin, 'admin')->get(route('admin.class-rankings.index'))->assertOk()
            ->assertSee('A Ikhwan')->assertSee('B Akhwat')->assertSee('C Belum Ada Leger')->assertSee('999,50')->assertSee('DRAF')->assertDontSee('Santri Arsip');
        foreach (['guru', 'wali_santri', 'kepala_sekolah'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user, 'admin')->get(route('admin.class-rankings.index'))->assertForbidden();
            foreach (['xlsx', 'pdf'] as $format) {
                $this->get(route('admin.class-rankings.export', ['format' => $format]))->assertForbidden();
            }
        }
    }

    public function test_rankings_keep_saved_combined_totals_and_rank_and_sort_unranked_students_last_without_writes(): void
    {
        [$admin, $term, $classes] = $this->context();
        $before = DiniyyahLedgerSnapshot::with('rows.cells')->get()->toArray();
        $report = app(ClassRankingReport::class)->build($term);
        $first = $report['classes'][0]['rows'];
        $this->assertSame([1, 2, null], $first->pluck('rank')->all());
        $this->assertSame('999.50', $first[1]['total']);
        $this->assertSame('99.95', $first[1]['average']);
        $this->assertSame(243.0, $first[1]['tahfidz_total']);
        $this->assertSame('170.00', $report['classes'][1]['rows'][1]['total']);
        $this->assertFalse($first[2]['complete']);
        $this->assertNull($report['classes'][2]['snapshot_id']);
        $this->assertSame($before, DiniyyahLedgerSnapshot::with('rows.cells')->get()->toArray());
        $this->actingAs($admin, 'admin')->get(route('admin.class-rankings.index', ['academic_term_id' => $term->id, 'classroom_term_id' => $classes[0]->id, 'include_archived' => 1]))->assertOk()->assertSee('Santri Arsip')->assertViewHas('report', fn ($data) => count($data['classes']) === 1);
    }

    public function test_class_filter_cannot_leak_a_class_from_another_period(): void
    {
        [$admin, $term, $classes] = $this->context();
        $other = AcademicTerm::create(['academic_year_id' => $term->academic_year_id, 'name' => 'Genap', 'semester' => 'genap']);
        $this->actingAs($admin, 'admin')->get(route('admin.class-rankings.index', ['academic_term_id' => $other->id, 'classroom_term_id' => $classes[0]->id]))->assertNotFound();
        $this->get(route('admin.class-rankings.export', ['format' => 'xlsx', 'academic_term_id' => $other->id, 'classroom_term_id' => $classes[0]->id]))->assertNotFound();
    }

    public function test_excel_download_has_summary_class_sheets_numeric_totals_and_string_nis(): void
    {
        [$admin, $term, $classes] = $this->context();
        $response = $this->actingAs($admin, 'admin')->get(route('admin.class-rankings.export', ['format' => 'xlsx', 'academic_term_id' => $term->id]))->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'rankings-test-');
        file_put_contents($path, $response->streamedContent());
        try {
            $book = IOFactory::load($path);
        } finally {
            @unlink($path);
        }
        $this->assertSame(4, $book->getSheetCount());
        $this->assertSame('Ringkasan Kelas', $book->getSheet(0)->getTitle());
        $sheet = $book->getSheet(1);
        $this->assertEquals(1, $sheet->getCell('A9')->getValue());
        $this->assertSame('0001', $sheet->getCell('C9')->getValue());
        $this->assertSame('s', $sheet->getCell('C9')->getDataType());
        $this->assertEquals(999.5, $sheet->getCell('F10')->getValue());
        $this->assertSame('n', $sheet->getCell('F10')->getDataType());
        $this->assertSame('—', $sheet->getCell('A11')->getValue());
        $this->assertSame([1, 8], $sheet->getPageSetup()->getRowsToRepeatAtTop());
        $this->assertSame('D9', $sheet->getFreezePane());
        $this->assertStringContainsString('Leger belum dibuat', $book->getSheet(3)->getCell('A9')->getValue());
        $book->disconnectWorksheets();
    }

    public function test_pdf_download_is_folio_landscape_with_separate_class_pages_and_does_not_edit_scores(): void
    {
        [$admin, $term] = $this->context();
        $before = DiniyyahLedgerSnapshot::with('rows.cells')->get()->toArray();
        $response = $this->actingAs($admin, 'admin')->get(route('admin.class-rankings.export', ['format' => 'pdf', 'academic_term_id' => $term->id]))->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertMatchesRegularExpression('/\/Count 3\b/', $pdf);
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[0\.000 0\.000 936\.000 612\.000\]/', $pdf);
        $this->assertSame($before, DiniyyahLedgerSnapshot::with('rows.cells')->get()->toArray());
    }

    public function test_excel_preserves_formula_like_student_names_as_plain_text(): void
    {
        [$admin, $term, $classes] = $this->context();
        $snapshot = DiniyyahLedgerSnapshot::where('classroom_term_id', $classes[0]->id)->firstOrFail();
        $snapshot->rows()->where('rank_in_class', 1)->update(['student_name' => '=1+2']);
        $content = app(ClassRankingExporter::class)->export(app(ClassRankingReport::class)->build($term, $classes[0]->id));
        $path = tempnam(sys_get_temp_dir(), 'rankings-safe-');
        file_put_contents($path, $content);
        try {
            $book = IOFactory::load($path);
        } finally {
            @unlink($path);
        }
        $this->assertSame('=1+2', $book->getSheet(0)->getCell('B9')->getValue());
        $this->assertSame('s', $book->getSheet(0)->getCell('B9')->getDataType());
        $book->disconnectWorksheets();
    }
}
