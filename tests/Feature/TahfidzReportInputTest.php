<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentResult;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\DiniyyahSubject;
use App\Models\HomeroomAssignment;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\TahfidzReportEntry;
use App\Models\TahfidzUasResult;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AttendanceRecapService;
use App\Services\DiniyyahAssessmentComponentBuilder;
use App\Services\DiniyyahCombinedLedger;
use App\Services\DiniyyahLedgerWorkflow;
use App\Services\ReportCardPrintService;
use App\Services\ReportCardWorkflow;
use App\Services\TahfidzReportSources;
use App\Services\TahfidzReportWorkflow;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TahfidzReportInputTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        foreach (['guru', 'admin', 'kabag_tahfidz'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $user = User::factory()->create();
        $user->assignRole('guru', 'kabag_tahfidz');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => 'Ustadz Pengisi', 'gender' => 'male', 'status' => 'active']);
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026-2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil', 'is_active' => true, 'report_issue_date' => '2026-12-20']);
        $room = Classroom::create(['name' => 'Mustawa 1']);
        $class = ClassroomTerm::create(['classroom_id' => $room->id, 'academic_term_id' => $term->id, 'name' => 'Kelas Ikhwan']);
        HomeroomAssignment::create(['classroom_term_id' => $class->id, 'teacher_id' => $teacher->id]);
        $student = Student::create(['name' => 'Santri Ikhwan', 'nis' => '001', 'gender' => 'male', 'status' => 'active']);
        $enrollment = ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'student_id' => $student->id, 'status' => 'active']);

        return [$user, $enrollment, $term, $class];
    }

    private function values(): array
    {
        return ['juz_tested' => 'Juz 29 dan 30', 'uas_score' => 0, 'sabaq_score' => 80, 'sabqi_score' => 81.5, 'manzil_score' => 82];
    }

    public function test_only_kabag_can_input_and_the_portal_lists_both_genders_without_a_teacher_profile(): void
    {
        [$wali, $boy, $term, $class] = $this->context();
        $wali->removeRole('kabag_tahfidz');
        $girl = Student::create(['name' => 'Santri Akhwat', 'nis' => '002', 'gender' => 'female', 'status' => 'active']);
        $girlEnrollment = ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'student_id' => $girl->id, 'status' => 'active']);
        $data = $this->values() + ['version' => 0, 'action' => 'final'];
        $index = route('kabag-tahfidz.report.index', ['classroom_term_id' => $class->id]);
        $this->actingAs($wali)->get($index)->assertForbidden();
        $this->post(route('kabag-tahfidz.report.save', $girlEnrollment), $data)->assertForbidden();
        $this->post(route('guru.tahfidz-report.save', $boy), $data)->assertForbidden();
        $this->get(route('kabag-tahfidz.report.template', $term))->assertForbidden();
        $this->post(route('kabag-tahfidz.report.import', $term))->assertForbidden();

        $kabag = User::factory()->create();
        $kabag->assignRole('kabag_tahfidz');
        $this->actingAs($kabag)->get(route('kabag-tahfidz.dashboard'))->assertOk()->assertSee('Nilai Tahfidz Rapor');
        $this->get($index)->assertOk()->assertSee('Santri Ikhwan')->assertSee('Santri Akhwat');
        $this->post(route('kabag-tahfidz.report.save', $girlEnrollment), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tahfidz_report_entries', ['student_id' => $girl->id, 'input_by' => $kabag->id, 'input_role' => 'kabag_tahfidz']);
        $this->assertDatabaseCount('tahfidz_report_entries', 1);
    }

    public function test_partial_draft_zero_final_review_and_optimistic_concurrency_keep_all_versions(): void
    {
        [$user, $enrollment] = $this->context();
        $workflow = app(TahfidzReportWorkflow::class);
        $first = $workflow->save($user, $enrollment, ['juz_tested' => 'Juz 30'], false, 0);
        $original = $first->fresh()->toArray();
        try {
            $workflow->save($user, $enrollment, $this->values(), true, 0);
            $this->fail('A stale form must be rejected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Muat ulang', $e->getMessage());
        }
        $final = $workflow->save($user, $enrollment, $this->values(), true, 1);
        $this->assertSame('0.00', $final->uas_score);
        $this->assertSame('final', $final->status);
        $reviewed = $workflow->review($user, $enrollment, 2);
        $this->assertSame('reviewed', $reviewed->status);
        $this->assertSame($user->id, $reviewed->reviewed_by);
        $this->assertSame($original, $first->fresh()->toArray());
        $next = $workflow->save($user, $enrollment, $this->values(), true, 3);
        $this->assertNull($next->reviewed_by);
        $this->assertDatabaseCount('tahfidz_report_entries', 4);
    }

    public function test_final_requires_all_values_and_score_bounds_are_validated(): void
    {
        [$user, $enrollment] = $this->context();
        $this->actingAs($user)->post(route('kabag-tahfidz.report.save', $enrollment), ['action' => 'final', 'version' => 0])->assertSessionHasErrors('tahfidz');
        $this->actingAs($user)->post(route('kabag-tahfidz.report.save', $enrollment), array_replace($this->values(), ['uas_score' => 101, 'action' => 'final', 'version' => 0]))->assertSessionHasErrors('uas_score');
        $this->assertDatabaseCount('tahfidz_report_entries', 0);
    }

    public function test_only_class_homeroom_can_review_and_monitor_shows_missing_children(): void
    {
        [$wali, $enrollment, $term, $class] = $this->context();
        $other = User::factory()->create();
        $other->assignRole('guru', 'kabag_tahfidz');
        Teacher::create(['user_id' => $other->id, 'name' => 'Guru lainnya', 'gender' => 'male', 'status' => 'active']);
        app(TahfidzReportWorkflow::class)->save($other, $enrollment, $this->values(), true, 0);
        $this->actingAs($other)->get(route('guru.tahfidz-report.monitor', $class))->assertForbidden();
        $this->actingAs($other)->post(route('guru.tahfidz-report.review', $enrollment), ['version' => 1])->assertForbidden();
        $missing = Student::create(['name' => 'Anak Belum Diisi', 'nis' => '003', 'gender' => 'male', 'status' => 'active']);
        ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'student_id' => $missing->id, 'status' => 'active']);
        $this->actingAs($wali)->get(route('guru.tahfidz-report.monitor', $class))->assertOk()->assertSee('Anak Belum Diisi')->assertSee('Belum lengkap')->assertSee('Guru lainnya');
        $this->actingAs($wali)->post(route('guru.tahfidz-report.review', $enrollment), ['version' => 1])->assertSessionHasNoErrors();
        HomeroomAssignment::query()->update(['ends_at' => today()->subDay()]);
        $this->actingAs($wali)->get(route('guru.tahfidz-report.monitor', $class))->assertForbidden();
    }

    public function test_reviewed_manual_values_feed_ledger_without_modifying_legacy_tahfidz(): void
    {
        [$user, $enrollment, $term, $class] = $this->context();
        $legacy = TahfidzUasResult::create(['academic_term_id' => $term->id, 'student_id' => $enrollment->student_id, 'juz_tested' => 'Juz lama', 'final_score' => 77, 'is_complete' => true, 'status' => 'validated']);
        $before = $legacy->fresh()->toArray();
        $workflow = app(TahfidzReportWorkflow::class);
        $workflow->save($user, $enrollment, $this->values(), true, 0);
        $sources = app(TahfidzReportSources::class);
        $this->assertFalse($sources->resolve($sources->load($class, collect([$enrollment->student_id])), $enrollment->student_id)['ready']);
        $workflow->review($user, $enrollment, 1);
        $snapshot = DiniyyahLedgerSnapshot::create(['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'title' => 'Test', 'status' => 'draft', 'snapshot_data' => ['columns' => [], 'issues' => [], 'summary' => ['score_columns' => 2]]]);
        $row = $snapshot->rows()->create(['class_enrollment_id' => $enrollment->id, 'row_number' => 1, 'student_name' => 'Santri', 'total_diniyyah_score' => 170]);
        app(DiniyyahCombinedLedger::class)->append($snapshot);
        $this->assertSame('0.00', $row->cells()->where('column_key', 'tahfidz_uas')->first()->value_numeric);
        $this->assertSame('413.50', $row->cells()->where('column_key', 'combined_total')->first()->value_numeric);
        $this->assertSame($before, $legacy->fresh()->toArray());
        $snapshot->update(['status' => 'locked']);
        $this->actingAs($user)->post(route('kabag-tahfidz.report.save', $enrollment), $this->values() + ['version' => 2, 'action' => 'final'])->assertSessionHasErrors('tahfidz');
        $this->assertDatabaseCount('tahfidz_report_entries', 2);
    }

    public function test_attendance_is_from_original_student_attendance_and_stale_ledger_cannot_be_locked(): void
    {
        [$user, $enrollment, $term, $class] = $this->context();
        $attendance = StudentAttendance::create(['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'class_enrollment_id' => $enrollment->id, 'student_id' => $enrollment->student_id, 'attendance_date' => '2026-11-16', 'status' => 'sick']);
        $recap = app(AttendanceRecapService::class);
        $this->assertSame(['sick_count' => 1, 'permission_count' => 0, 'absent_count' => 0], $recap->recapForEnrollment($term->id, $enrollment->id));
        $snapshot = DiniyyahLedgerSnapshot::create(['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'title' => 'Test', 'status' => 'draft']);
        $snapshot->rows()->create(['class_enrollment_id' => $enrollment->id, 'row_number' => 1, 'student_name' => 'Santri']);
        $snapshot->update(['snapshot_data' => ['attendance_fingerprint' => $recap->ledgerFingerprint($snapshot)]]);
        $attendance->update(['status' => 'permission']);
        try {
            app(DiniyyahLedgerWorkflow::class)->lock($snapshot, $user);
            $this->fail('Changed attendance must block stale ledger lock.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Presensi peserta didik berubah', $e->getMessage());
        }
        $this->assertSame('draft', $snapshot->fresh()->status);
    }

    public function test_admin_date_changes_only_new_drafts_and_freezes_on_lock_while_archives_are_untouched(): void
    {
        [$user, $enrollment, $term, $class] = $this->context();
        $card = ReportCard::create(['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'class_enrollment_id' => $enrollment->id, 'student_id' => $enrollment->student_id, 'report_type' => 'diniyyah', 'status' => 'draft']);
        $old = $card->snapshots()->create(['generated_at' => now(), 'layout_version' => 'diniyyah-f4-v1', 'snapshot_data' => ['print' => ['options' => ['issue_date' => '2025-12-16']]]]);
        $service = app(ReportCardPrintService::class);
        $this->assertSame('2025-12-16', $service->data($card)['options']['issue_date']);
        $card->snapshots()->create(['generated_at' => now(), 'layout_version' => 'diniyyah-f4-v1', 'snapshot_data' => ['print' => ['date_policy' => 'admin-period-v1', 'options' => ['issue_date' => null]]]]);
        $this->assertSame('2026-12-20', $service->data($card)['options']['issue_date']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $term->update(['report_issue_date' => '2026-12-21']);
        $this->assertSame('2026-12-21', $service->data($card)['options']['issue_date']);
        app(ReportCardWorkflow::class)->lock($card, $admin);
        $term->update(['report_issue_date' => '2026-12-22']);
        $this->assertSame('2026-12-21', $service->data($card)['options']['issue_date']);
        $this->assertSame('2025-12-16', $old->fresh()->snapshot_data['print']['options']['issue_date']);
        $this->actingAs($user);
        $this->expectException(AuthorizationException::class);
        $term->update(['report_issue_date' => '2026-12-23']);
    }

    public function test_rollback_refuses_to_delete_populated_report_input_data(): void
    {
        [$user, $enrollment, $term] = $this->context();
        $entry = app(TahfidzReportWorkflow::class)->save($user, $enrollment, $this->values(), true, 0);
        $migration = require database_path('migrations/2026_11_17_120000_add_report_input_settings.php');
        try {
            $migration->down();
            $this->fail('Rollback must refuse to drop populated tables.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Rollback dibatalkan', $exception->getMessage());
        }
        $this->assertSame('final', $entry->fresh()->status);
        $this->assertSame('2026-12-20', $term->fresh()->report_issue_date->format('Y-m-d'));
    }

    public function test_class_then_student_selection_only_opens_one_form_and_rejects_mismatched_children(): void
    {
        [$user, $enrollment, $term, $class] = $this->context();
        $this->actingAs($user)->get(route('kabag-tahfidz.report.index'))->assertOk()
            ->assertViewHas('selectedEnrollment', null)->assertDontSee('name="uas_score"', false);
        $this->get(route('kabag-tahfidz.report.index', ['classroom_term_id' => $class->id]))->assertOk()
            ->assertSee('Santri Ikhwan')->assertDontSee('name="uas_score"', false);
        $url = route('kabag-tahfidz.report.index', ['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'enrollment_id' => $enrollment->id]);
        $response = $this->get($url)->assertOk()->assertSee('name="uas_score"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'name="uas_score"'));
        $otherRoom = Classroom::create(['name' => 'Kelas Lain']);
        $otherClass = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $otherRoom->id, 'name' => 'Kelas Lain']);
        $otherChild = Student::create(['name' => 'Santri Kelas Lain', 'nis' => '009', 'gender' => 'male', 'status' => 'active']);
        $other = ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $otherClass->id, 'student_id' => $otherChild->id, 'status' => 'active']);
        $this->get(route('kabag-tahfidz.report.index', ['classroom_term_id' => $class->id, 'enrollment_id' => $other->id]))->assertForbidden();
        $this->get(route('kabag-tahfidz.report.index', ['enrollment_id' => $enrollment->id]))->assertForbidden();
        $this->post(route('kabag-tahfidz.report.save', $enrollment), $this->values() + ['version' => 0, 'action' => 'final'])
            ->assertRedirect(route('kabag-tahfidz.report.index', ['academic_term_id' => $term->id, 'classroom_term_id' => $class->id]));
        $this->get(route('kabag-tahfidz.report.index', ['academic_term_id' => $term->id, 'classroom_term_id' => $class->id]))
            ->assertOk()->assertViewHas('selectedEnrollment', null)->assertSee('Juz 29 dan 30')->assertDontSee('name="uas_score"', false);
    }

    public function test_current_roster_displays_latest_value_and_preserves_previous_revisions(): void
    {
        [$user, $enrollment, $term, $class] = $this->context();
        $workflow = app(TahfidzReportWorkflow::class);
        $first = $workflow->save($user, $enrollment, $this->values(), true, 0);
        $before = $first->fresh()->toArray();
        $other = User::factory()->create();
        $other->assignRole('kabag_tahfidz');
        $workflow->save($other, $enrollment, array_replace($this->values(), ['uas_score' => 55]), true, 1);

        $this->actingAs($user)->get(route('kabag-tahfidz.report.index', ['classroom_term_id' => $class->id, 'enrollment_id' => $enrollment->id]))
            ->assertOk()->assertSee('value="55.00"', false);
        $this->assertSame($before, $first->fresh()->toArray());
        $this->assertDatabaseCount('tahfidz_report_entries', 2);
    }

    public function test_downloads_class_and_full_excel_and_reviewed_upload_reaches_rapor(): void
    {
        [$wali, $enrollment, $term, $class] = $this->context();
        $wali->removeRole('kabag_tahfidz');
        $kabag = User::factory()->create();
        $kabag->assignRole('kabag_tahfidz');
        $other = $this->enrollInSecondClass($term);
        $archived = Student::create(['name' => 'Santri Arsip', 'nis' => '003', 'gender' => 'female', 'status' => 'inactive']);
        ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'student_id' => $archived->id, 'status' => 'active']);

        $this->actingAs($kabag);
        $classWorkbook = $this->downloadTemplate($term, $class);
        $classSheet = $classWorkbook->getActiveSheet();
        $this->assertSame(['NIS', 'Nama Santri', 'Kelas', 'Juz yang diujikan', 'Nilai Ujian Tahfidz', 'Sabaq', 'Sabqi', 'Manzil'],
            $classSheet->rangeToArray('A4:H4')[0]);
        $this->assertSame(['001', 'Santri Ikhwan', 'Kelas Ikhwan'], $classSheet->rangeToArray('A5:C5')[0]);
        $this->assertSame(5, $classSheet->getHighestDataRow());
        $classWorkbook->disconnectWorksheets();

        $full = $this->downloadTemplate($term);
        $sheet = $full->getActiveSheet();
        $this->assertSame(6, $sheet->getHighestDataRow());
        $this->assertSame(['002', 'Santri Kelas Kedua', 'Kelas Lanjutan'], $sheet->rangeToArray('A6:C6')[0]);
        $sheet->fromArray(['Juz 29 dan 30', 0, 80, '81,5', 82], null, 'D5', true);
        $sheet->setCellValue('F6', 70);
        $this->post(route('kabag-tahfidz.report.import', $term), ['file' => $this->excelUpload($full)])
            ->assertRedirect(route('kabag-tahfidz.report.index', ['academic_term_id' => $term->id]))
            ->assertSessionHasNoErrors()->assertSessionHas('status', '1 nilai final menunggu pemeriksaan wali kelas dan 1 draf disimpan.');
        $this->assertDatabaseHas('tahfidz_report_entries', ['student_id' => $enrollment->student_id, 'input_by' => $kabag->id, 'input_role' => 'kabag_tahfidz', 'status' => 'final']);
        $this->assertDatabaseHas('tahfidz_report_entries', ['student_id' => $other->student_id, 'status' => 'draft', 'sabaq_score' => 70]);

        $this->actingAs($wali)->get(route('guru.tahfidz-report.monitor', $class))->assertOk()
            ->assertViewHas('kabagComplete', 1)->assertSee('Menunggu pemeriksaan')->assertDontSee('Santri Kelas Kedua');
        $this->post(route('guru.tahfidz-report.review', $enrollment), ['version' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tahfidz_report_entries', ['student_id' => $enrollment->student_id, 'status' => 'reviewed', 'input_role' => 'kabag_tahfidz']);

        $subject = DiniyyahSubject::create(['code' => 'fiqih', 'name' => 'Fiqih']);
        $classSubject = DiniyyahClassSubject::create(['classroom_term_id' => $class->id, 'subject_id' => $subject->id,
            'assessment_method' => 'direct_final', 'is_active' => true, 'appears_on_ledger' => true]);
        $set = DiniyyahAssessmentSet::create(['diniyyah_class_subject_id' => $classSubject->id, 'title' => 'Fiqih',
            'assessment_method' => 'direct_final', 'status' => 'validated', 'appears_on_ledger' => true]);
        app(DiniyyahAssessmentComponentBuilder::class)->createDefaults($set);
        DiniyyahAssessmentResult::create(['diniyyah_assessment_set_id' => $set->id, 'class_enrollment_id' => $enrollment->id,
            'final_score' => 85, 'is_complete' => true]);

        $this->post(route('diniyyah.ledger.generate', $class))->assertSessionHasNoErrors();
        $snapshot = DiniyyahLedgerSnapshot::sole();
        $this->post(route('diniyyah.ledger.lock', $snapshot))->assertSessionHasNoErrors();
        $card = ReportCard::where('class_enrollment_id', $enrollment->id)->firstOrFail();
        $this->assertSame([
            'tahfidz_juz' => 'Juz 29 dan 30', 'tahfidz_uas' => '0.00', 'tahfidz_sabaq' => '80.00',
            'tahfidz_sabqi' => '81.50', 'tahfidz_manzil' => '82.00',
        ], $card->snapshots()->latest('id')->first()->snapshot_data['print']['tahfidz']);
    }

    public function test_partial_updates_preserve_scores_and_stale_full_upload_rolls_back_every_row(): void
    {
        [$kabag, $enrollment, $term, $class] = $this->context();
        $other = $this->enrollInSecondClass($term);
        $this->actingAs($kabag);
        $stale = $this->downloadTemplate($term);
        $stale->getActiveSheet()->setCellValue('F5', 95)->setCellValue('F6', 96);
        app(TahfidzReportWorkflow::class)->save($kabag, $other, ['sabaq_score' => 64], false, 0);

        $import = route('kabag-tahfidz.report.import', $term);
        $this->post($import, ['file' => $this->excelUpload($stale)])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('tahfidz_report_entries', 1);

        app(TahfidzReportWorkflow::class)->save($kabag, $enrollment, $this->values(), true, 0);
        $correction = $this->downloadTemplate($term, $class);
        $correction->getActiveSheet()->setCellValue('G5', 100);
        $this->post($import, ['file' => $this->excelUpload($correction)])->assertSessionHasNoErrors();
        $entry = TahfidzReportEntry::where('student_id', $enrollment->student_id)->where('version', 2)->firstOrFail();
        $this->assertSame('100.00', $entry->sabqi_score);
        $this->assertSame('0.00', $entry->uas_score);
        $this->assertSame('final', $entry->status);

        $locked = $this->downloadTemplate($term, $class);
        $locked->getActiveSheet()->setCellValue('G5', 82);
        DiniyyahLedgerSnapshot::create(['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'title' => 'Leger', 'status' => 'locked']);
        $this->post($import, ['file' => $this->excelUpload($locked)])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('tahfidz_report_entries', 3);
    }

    public function test_import_rejects_wrong_semester_and_invalid_scores_before_saving(): void
    {
        [$kabag, $enrollment, $term, $class] = $this->context();
        $otherTerm = AcademicTerm::create(['academic_year_id' => $term->academic_year_id, 'name' => 'Genap', 'semester' => 'genap']);
        $this->actingAs($kabag);
        $workbook = $this->downloadTemplate($term, $class);
        $sheet = $workbook->getActiveSheet();
        $sheet->setCellValue('E5', 101);
        $this->post(route('kabag-tahfidz.report.import', $term), ['file' => $this->excelUpload($workbook)])->assertSessionHasErrors('file');

        $sheet->setCellValue('E5', 90);
        $this->post(route('kabag-tahfidz.report.import', $otherTerm), ['file' => $this->excelUpload($workbook)])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('tahfidz_report_entries', 0);
    }

    private function enrollInSecondClass(AcademicTerm $term): ClassEnrollment
    {
        $room = Classroom::create(['name' => 'Kelas Lanjutan']);
        $class = ClassroomTerm::create(['classroom_id' => $room->id, 'academic_term_id' => $term->id, 'name' => $room->name]);
        $student = Student::create(['name' => 'Santri Kelas Kedua', 'nis' => '002', 'gender' => 'female', 'status' => 'active']);

        return ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'student_id' => $student->id, 'status' => 'active']);
    }

    private function downloadTemplate(AcademicTerm $term, ?ClassroomTerm $class = null): Spreadsheet
    {
        $response = $this->get(route('kabag-tahfidz.report.template', ['term' => $term, 'classroom_term_id' => $class?->id]))
            ->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'tahfidz-');
        try {
            file_put_contents($path, $response->streamedContent());

            return IOFactory::createReader('Xlsx')->load($path);
        } finally {
            unlink($path);
        }
    }

    private function excelUpload(Spreadsheet $workbook): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'tahfidz-');
        try {
            (new Xlsx($workbook))->save($path);

            return UploadedFile::fake()->createWithContent('nilai-tahfidz.xlsx', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }
}
