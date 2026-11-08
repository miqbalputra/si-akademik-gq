<?php

namespace Tests\Feature;

use App\Jobs\ExportDiniyyahLedgerExcel;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentResult;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\DiniyyahScore;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\HomeroomAssignment;
use App\Models\ReportExportRequest;
use App\Models\School;
use App\Models\Student;
use App\Models\TahfidzHalaqah;
use App\Models\TahfidzHalaqahMember;
use App\Models\TahfidzSemesterRecap;
use App\Models\TahfidzUasResult;
use App\Models\Teacher;
use App\Models\User;
use App\Services\DiniyyahAssessmentComponentBuilder;
use App\Services\DiniyyahLedgerGenerator;
use App\Services\DiniyyahLedgerWorkflow;
use App\Services\DiniyyahScoreCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DiniyyahWorkbookWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        foreach (['guru', 'admin', 'kabag_diniyyah', 'kepala_sekolah'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $teachers = [];
        foreach (['wali', 'mapel', 'other'] as $key) {
            $user = User::factory()->create();
            $user->assignRole('guru');
            $teachers[$key] = Teacher::create(['user_id' => $user->id, 'name' => 'Guru '.$key, 'status' => 'active']);
        }
        $school = School::create(['name' => 'Sekolah uji workbook']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Genap', 'semester' => 'genap']);
        $classroom = Classroom::create(['name' => 'M5 Akhwat Uji', 'level_name' => 'M5']);
        $ct = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => $classroom->name]);
        $homeroom = HomeroomAssignment::create(['classroom_term_id' => $ct->id, 'teacher_id' => $teachers['wali']->id]);
        $student = Student::create(['name' => 'Santri Uji', 'nis' => 'GAB-001', 'gender' => 'female', 'status' => 'active']);
        $enrollment = ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $ct->id, 'student_id' => $student->id, 'status' => 'active']);
        $sets = [];
        foreach (['fiqih', 'suluk', 'praktik_ibadah'] as $code) {
            $subject = DiniyyahSubject::create(['code' => $code, 'name' => ucfirst($code)]);
            $cs = DiniyyahClassSubject::create(['classroom_term_id' => $ct->id, 'subject_id' => $subject->id, 'assessment_method' => 'direct_final', 'is_active' => true, 'appears_on_ledger' => true]);
            DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $cs->id, 'teacher_id' => $teachers['mapel']->id]);
            $sets[$code] = DiniyyahAssessmentSet::create(['diniyyah_class_subject_id' => $cs->id, 'title' => ucfirst($code), 'assessment_method' => 'direct_final', 'status' => 'validated', 'appears_on_ledger' => true]);
            app(DiniyyahAssessmentComponentBuilder::class)->createDefaults($sets[$code]);
            DiniyyahAssessmentResult::create(['diniyyah_assessment_set_id' => $sets[$code]->id, 'class_enrollment_id' => $enrollment->id, 'final_score' => 80, 'is_complete' => true]);
        }
        $halaqah = TahfidzHalaqah::create(['academic_term_id' => $term->id, 'teacher_id' => $teachers['other']->id, 'name' => 'Halaqah Uji']);
        $member = TahfidzHalaqahMember::create(['tahfidz_halaqah_id' => $halaqah->id, 'student_id' => $student->id, 'class_enrollment_id' => $enrollment->id]);
        $recap = TahfidzSemesterRecap::create(['academic_term_id' => $term->id, 'student_id' => $student->id, 'tahfidz_halaqah_id' => $halaqah->id, 'tahfidz_halaqah_member_id' => $member->id, 'sabaq_semester_score' => 75, 'sabqi_score' => 85, 'manzil_average_score' => 90, 'status' => 'locked']);
        $uas = TahfidzUasResult::create(['academic_term_id' => $term->id, 'student_id' => $student->id, 'tahfidz_halaqah_id' => $halaqah->id, 'juz_tested' => 'Juz 28–30', 'final_score' => 70, 'is_complete' => true, 'status' => 'validated']);

        return compact('teachers', 'ct', 'term', 'student', 'enrollment', 'sets', 'homeroom', 'recap', 'uas');
    }

    public function test_suluk_is_only_editable_by_the_class_homeroom_even_if_a_subject_teacher_is_assigned(): void
    {
        $c = $this->context();
        $set = $c['sets']['suluk'];
        $set->update(['status' => 'active']);
        $this->actingAs($c['teachers']['mapel']->user)->get(route('guru.diniyyah-scores.edit', $set))->assertForbidden();
        $c['teachers']['mapel']->user->assignRole('admin');
        $this->get(route('guru.diniyyah-scores.edit', $set))->assertForbidden();
        $this->actingAs($c['teachers']['wali']->user)->get(route('guru.diniyyah-scores.edit', $set))->assertOk()->assertSee('Suluk diisi wali kelas');
        $this->put(route('guru.diniyyah-scores.update', $set), ['scores' => [$c['enrollment']->id => [$set->components->sole()->id => 88]]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('88.00', $set->results()->sole()->final_score);
        $this->get(route('guru.diniyyah-scores.index'))->assertOk()->assertSee('Suluk')->assertDontSee('Fiqih');
        $this->actingAs($c['teachers']['mapel']->user)->get(route('guru.diniyyah-scores.index'))->assertOk()->assertSee('Fiqih')->assertDontSee('Suluk diisi');
    }

    public function test_only_the_homeroom_can_generate_read_and_export_the_combined_ledger(): void
    {
        $c = $this->context();
        $this->actingAs($c['teachers']['wali']->user)->post(route('diniyyah.ledger.generate', $c['ct']))->assertRedirect();
        $snapshot = DiniyyahLedgerSnapshot::sole();
        $this->get(route('diniyyah.ledger.show', $snapshot))->assertOk()->assertSee('UAS Tahfidz')->assertSee('Juz 28–30')->assertSee('>560</td>', false);
        $row = $snapshot->rows()->with('cells')->sole();
        $this->assertSame('240.00', $row->total_diniyyah_score);
        $this->assertSame('560.00', $row->cells->firstWhere('column_key', 'combined_total')->value_numeric);
        $this->get(route('diniyyah.ledger.export-excel', $snapshot))->assertOk();
        $pdf = $this->get(route('diniyyah.ledger.export-pdf', $snapshot))->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        if (getenv('WORKBOOK_LEDGER_QA') === '1') {
            Storage::disk('local')->put('ledger-qa/combined.pdf', $pdf->getContent());
            Storage::disk('local')->put('ledger-qa/combined.xlsx', $this->get(route('diniyyah.ledger.export-excel', $snapshot))->streamedContent());
            Storage::disk('local')->put('ledger-qa/combined.html', $this->get(route('diniyyah.ledger.show', $snapshot))->getContent());
        }
        foreach (['mapel', 'other'] as $key) {
            $user = $c['teachers'][$key]->user;
            $user->assignRole(['admin', 'kabag_diniyyah', 'kepala_sekolah']);
            $this->actingAs($user)->get(route('diniyyah.ledger.show', $snapshot))->assertForbidden();
            $this->post(route('diniyyah.ledger.generate', $c['ct']))->assertForbidden();
            $this->get(route('diniyyah.ledger.export-excel', $snapshot))->assertForbidden();
            $this->get(route('diniyyah.ledger.export-pdf', $snapshot))->assertForbidden();
            $this->post(route('diniyyah.ledger.lock', $snapshot))->assertForbidden();
        }
    }

    public function test_optional_daily_columns_are_averaged_and_journal_attendance_cannot_be_forged(): void
    {
        $c = $this->context();
        $set = $c['sets']['fiqih'];
        $set->components()->delete();
        $set->update(['assessment_method' => 'weighted', 'daily_weight' => 40, 'exam_weight' => 60, 'status' => 'active']);
        app(DiniyyahAssessmentComponentBuilder::class)->createDefaults($set);
        $components = $set->components()->get()->keyBy('code');
        DiniyyahScore::create(['diniyyah_assessment_set_id' => $set->id, 'diniyyah_score_component_id' => $components['keaktifan_presensi']->id, 'class_enrollment_id' => $c['enrollment']->id, 'score' => 60]);
        $this->actingAs($c['teachers']['mapel']->user)->put(route('guru.diniyyah-scores.update', $set), ['scores' => [$c['enrollment']->id => [
            $components['keaktifan_presensi']->id => 100,
            $components['ulangan_harian_1']->id => 80,
            $components['ulangan_harian_2']->id => null,
            $components['nilai_tugas']->id => null,
            $components['nilai_ujian_mentah']->id => 90,
        ]]])->assertRedirect()->assertSessionHasNoErrors();
        $result = $set->results()->sole();
        $this->assertSame('70.00', $result->daily_raw_score);
        $this->assertSame('82.00', $result->final_score);
        $this->assertTrue($result->is_complete);
        $this->assertSame('60.00', $components['keaktifan_presensi']->scores()->sole()->score);
        $this->get(route('guru.diniyyah-scores.edit', $set))->assertOk()->assertSee('(opsional)');
    }

    public function test_expired_homeroom_assignment_revokes_detail_and_queued_download_access(): void
    {
        $c = $this->context();
        $snapshot = app(DiniyyahLedgerGenerator::class)->generate($c['ct'], null, true);
        $request = ReportExportRequest::create(['requested_by' => $c['teachers']['wali']->user_id, 'export_type' => 'diniyyah_ledger_excel', 'filters' => ['snapshot_id' => $snapshot->id], 'status' => 'completed', 'file_path' => 'ledger-qa/test.xlsx']);
        $c['homeroom']->update(['ends_at' => today()->subDay()]);
        $this->actingAs($c['teachers']['wali']->user)->get(route('diniyyah.ledger.show', $snapshot))->assertForbidden();
        $this->get(route('diniyyah.ledger.download', $request))->assertForbidden();
        $this->get(route('guru.diniyyah-scores.edit', $c['sets']['suluk']))->assertForbidden();
    }

    public function test_weighted_final_score_uses_the_unrounded_average_like_the_workbook(): void
    {
        $c = $this->context();
        $set = $c['sets']['fiqih'];
        $set->components()->delete();
        $set->update(['assessment_method' => 'weighted', 'daily_weight' => 40, 'exam_weight' => 60, 'status' => 'active']);
        app(DiniyyahAssessmentComponentBuilder::class)->createDefaults($set);
        foreach ($set->components()->get() as $component) {
            if ($component->code === 'nilai_tugas') {
                continue;
            }
            DiniyyahScore::create(['diniyyah_assessment_set_id' => $set->id, 'diniyyah_score_component_id' => $component->id, 'class_enrollment_id' => $c['enrollment']->id, 'score' => $component->code === 'ulangan_harian_2' ? 60.02 : 60.01]);
        }
        $result = app(DiniyyahScoreCalculator::class)->calculate($set, $c['enrollment']);
        $this->assertSame('60.01', $result->final_score);
    }

    public function test_missing_unvalidated_or_ambiguous_tahfidz_does_not_become_zero_or_a_final_total(): void
    {
        $c = $this->context();
        $c['uas']->update(['status' => 'draft']);
        $snapshot = app(DiniyyahLedgerGenerator::class)->generate($c['ct'], null, true);
        $this->assertNull($snapshot->rows()->sole()->cells()->where('column_key', 'combined_total')->sole()->value_numeric);
        $this->assertGreaterThan(0, $snapshot->snapshot_data['summary']['blocking_issues']);
        $this->expectException(\DomainException::class);
        app(DiniyyahLedgerWorkflow::class)->lock($snapshot, $c['teachers']['wali']->user);
    }

    public function test_multiple_practical_materials_are_separate_columns_and_draft_material_can_be_excluded(): void
    {
        $c = $this->context();
        $practice = $c['sets']['praktik_ibadah'];
        $second = DiniyyahAssessmentSet::create(['diniyyah_class_subject_id' => $practice->diniyyah_class_subject_id, 'title' => 'Praktik Ibadah 2', 'tested_material' => 'Wudhu', 'assessment_method' => 'direct_final', 'status' => 'validated', 'appears_on_ledger' => true]);
        DiniyyahAssessmentResult::create(['diniyyah_assessment_set_id' => $second->id, 'class_enrollment_id' => $c['enrollment']->id, 'final_score' => 90, 'is_complete' => true]);
        $snapshot = app(DiniyyahLedgerGenerator::class)->generate($c['ct'], null, true);
        $this->assertContains('Praktik Ibadah 2', collect($snapshot->snapshot_data['columns'])->pluck('label'));
        $this->assertSame('650.00', $snapshot->rows()->sole()->cells()->where('column_key', 'combined_total')->sole()->value_numeric);
        $second->update(['appears_on_ledger' => false]);
        $snapshot = app(DiniyyahLedgerGenerator::class)->generate($c['ct']);
        $this->assertSame('560.00', $snapshot->rows()->sole()->cells()->where('column_key', 'combined_total')->sole()->value_numeric);
    }

    public function test_tahfidz_for_another_term_is_not_used_and_duplicate_recaps_require_resolution(): void
    {
        $c = $this->context();
        $term = AcademicTerm::create(['academic_year_id' => $c['term']->academic_year_id, 'name' => 'Ganjil', 'semester' => 'ganjil']);
        $c['uas']->update(['academic_term_id' => $term->id]);
        $snapshot = app(DiniyyahLedgerGenerator::class)->generate($c['ct'], null, true);
        $this->assertNull($snapshot->rows()->sole()->cells()->where('column_key', 'tahfidz_uas')->sole()->value_numeric);
        $c['uas']->update(['academic_term_id' => $c['term']->id]);
        $otherHalaqah = TahfidzHalaqah::create(['academic_term_id' => $c['term']->id, 'teacher_id' => $c['teachers']['other']->id, 'name' => 'Halaqah kedua']);
        $otherMember = TahfidzHalaqahMember::create(['tahfidz_halaqah_id' => $otherHalaqah->id, 'student_id' => $c['student']->id, 'class_enrollment_id' => $c['enrollment']->id]);
        TahfidzSemesterRecap::create(['tahfidz_halaqah_id' => $otherHalaqah->id, 'tahfidz_halaqah_member_id' => $otherMember->id, 'student_id' => $c['student']->id, 'academic_term_id' => $c['term']->id, 'sabaq_semester_score' => 100, 'sabqi_score' => 100, 'manzil_average_score' => 100, 'status' => 'locked']);
        $snapshot = app(DiniyyahLedgerGenerator::class)->generate($c['ct']);
        $this->assertNull($snapshot->rows()->sole()->cells()->where('column_key', 'combined_total')->sole()->value_numeric);
        $this->assertContains('tahfidz_incomplete', array_column($snapshot->snapshot_data['issues'], 'code'));
    }

    public function test_queued_exports_recheck_the_homeroom_assignment_before_writing_a_file(): void
    {
        $c = $this->context();
        $snapshot = app(DiniyyahLedgerGenerator::class)->generate($c['ct'], null, true);
        $export = ReportExportRequest::create(['requested_by' => $c['teachers']['wali']->user_id, 'export_type' => 'diniyyah_ledger_excel', 'filters' => ['snapshot_id' => $snapshot->id], 'status' => 'queued']);
        $c['homeroom']->update(['ends_at' => today()->subDay()]);
        try {
            (new ExportDiniyyahLedgerExcel($export->id))->handle();
            $this->fail('Ekspor guru yang tidak lagi menjadi wali kelas harus ditolak.');
        } catch (\DomainException) {
            $this->assertSame('failed', $export->fresh()->status);
            $this->assertNull($export->fresh()->file_path);
        }
    }

    public function test_tahfidz_changes_require_refresh_and_locked_snapshot_keeps_its_source_values(): void
    {
        $c = $this->context();
        $generator = app(DiniyyahLedgerGenerator::class);
        $snapshot = $generator->generate($c['ct'], null, true);
        $c['uas']->update(['final_score' => 80]);
        try {
            app(DiniyyahLedgerWorkflow::class)->lock($snapshot, $c['teachers']['wali']->user);
            $this->fail('Leger usang tidak boleh dikunci.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('Tahfidz berubah', $exception->getMessage());
        }
        $snapshot = $generator->generate($c['ct']); // Refresh preserves the combined layout.
        app(DiniyyahLedgerWorkflow::class)->lock($snapshot, $c['teachers']['wali']->user);
        $c['uas']->update(['final_score' => 95]);
        $this->assertSame('80.00', $snapshot->rows()->sole()->cells()->where('column_key', 'tahfidz_uas')->sole()->value_numeric);
        $this->expectException(\DomainException::class);
        $generator->generate($c['ct'], null, true);
    }

    public function test_diniyyah_changes_after_generation_also_block_finalization_until_refreshed(): void
    {
        $c = $this->context();
        $snapshot = app(DiniyyahLedgerGenerator::class)->generate($c['ct'], null, true);
        $c['sets']['fiqih']->results()->update(['final_score' => 85]);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Nilai Diniyyah berubah');
        app(DiniyyahLedgerWorkflow::class)->lock($snapshot, $c['teachers']['wali']->user);
    }
}
