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
use App\Models\DiniyyahKkmRevision;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\PanelNotification;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\DiniyyahKkmAccess;
use App\Services\DiniyyahKkmCalculator;
use App\Services\DiniyyahKkmExportService;
use App\Services\DiniyyahKkmWorkflow;
use App\Services\DiniyyahLedgerGenerator;
use App\Services\DiniyyahLedgerWorkflow;
use App\Services\ReportCardGenerator;
use App\Services\ReportCardWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DiniyyahKkmTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        foreach (['admin', 'kabag_diniyyah', 'guru', 'wali_santri'] as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $admin = User::factory()->create(['name' => 'Admin KKM']);
        $admin->assignRole('admin');
        $school = School::create(['name' => 'Sekolah KKM']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil', 'starts_at' => '2026-07-01', 'ends_at' => '2026-12-31', 'is_active' => true, 'kkm_workflow_enabled' => true]);
        $subject = DiniyyahSubject::create(['code' => 'akidah_kkm', 'name' => 'Akidah Akhlak', 'default_assessment_method' => 'direct_final']);
        $teachers = $subjects = $users = [];
        foreach (['female' => 'Mustawa 1', 'male' => 'M1'] as $gender => $level) {
            $user = User::factory()->create(['name' => $gender === 'female' ? 'Guru Akhwat' : 'Guru Ikhwan']);
            $user->assignRole('guru');
            $teacher = Teacher::create(['user_id' => $user->id, 'name' => $user->name, 'gender' => $gender, 'status' => 'active']);
            $classroom = Classroom::create(['name' => 'M1 '.$gender, 'level_name' => $level, 'gender_group' => $gender, 'is_active' => true]);
            $ct = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => $classroom->name, 'status' => 'active']);
            $cs = DiniyyahClassSubject::create(['classroom_term_id' => $ct->id, 'subject_id' => $subject->id, 'assessment_method' => 'direct_final', 'kkm' => 70, 'is_active' => true]);
            DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $cs->id, 'teacher_id' => $teacher->id]);
            $teachers[$gender] = $teacher;
            $subjects[$gender] = $cs;
            $users[$gender] = $user;
        }
        $scope = app(DiniyyahKkmWorkflow::class)->assign($subjects['female'], $teachers['female']->id, $admin);

        return compact('admin', 'term', 'subject', 'teachers', 'subjects', 'users', 'scope');
    }

    private function input(int $score = 75): array
    {
        return array_fill_keys(array_keys(DiniyyahKkmCalculator::CRITERIA), ['score' => $score, 'reason' => 'Pertimbangan guru berdasarkan kondisi pembelajaran.']);
    }

    private function submitted(array $c, int $score = 75): DiniyyahKkmRevision
    {
        $workflow = app(DiniyyahKkmWorkflow::class);
        $revision = $workflow->startRevision($c['scope']->fresh(), $c['users']['female']);

        return $workflow->save($revision, $this->input($score), $c['users']['female'], $revision->lock_version, true);
    }

    private function approved(array $c, int $score = 75): DiniyyahKkmRevision
    {
        $revision = $this->submitted($c, $score);
        app(DiniyyahKkmWorkflow::class)->review($revision, $c['admin'], $revision->lock_version, true);

        return $revision->fresh();
    }

    public static function bands(): array
    {
        return [[60, 'Tinggi', 'Kurang Mendukung', 'Rendah'], [65, 'Tinggi', 'Kurang Mendukung', 'Rendah'], [66, 'Sedang', 'Cukup Mendukung', 'Sedang'], [79, 'Sedang', 'Cukup Mendukung', 'Sedang'], [80, 'Rendah', 'Sangat Mendukung', 'Tinggi'], [100, 'Rendah', 'Sangat Mendukung', 'Tinggi']];
    }

    #[DataProvider('bands')]
    public function test_category_boundaries(int $score, string $complexity, string $support, string $intake): void
    {
        $result = app(DiniyyahKkmCalculator::class)->calculate($this->input($score), true);
        $this->assertSame($complexity, $result['criteria']['complexity']['category']);
        $this->assertSame($support, $result['criteria']['support']['category']);
        $this->assertSame($intake, $result['criteria']['intake']['category']);
        $this->assertSame($score, $result['final_kkm']);
    }

    public function test_rounding_and_incomplete_draft(): void
    {
        $calculator = app(DiniyyahKkmCalculator::class);
        $input = $this->input(65);
        $input['intake']['score'] = 75;
        $this->assertSame(68, $calculator->calculate($input, true)['final_kkm']);
        $this->assertNull($calculator->calculate([])['final_kkm']);
    }

    public static function invalidInputs(): array
    {
        return [[59], [101], [65.5], ['abc'], [null]];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_inputs_cannot_be_submitted(mixed $score): void
    {
        $input = $this->input();
        $input['complexity']['score'] = $score;
        $this->expectException(ValidationException::class);
        app(DiniyyahKkmCalculator::class)->calculate($input, true);
    }

    public function test_reason_is_required_and_client_categories_are_ignored(): void
    {
        $input = $this->input();
        $input['complexity']['category'] = 'FAKE';
        $this->assertSame('Sedang', app(DiniyyahKkmCalculator::class)->calculate($input, true)['criteria']['complexity']['category']);
        $input['support']['reason'] = '   ';
        $this->expectException(ValidationException::class);
        app(DiniyyahKkmCalculator::class)->calculate($input, true);
    }

    public function test_akhwat_author_and_ikhwan_reader_across_approval_and_exports(): void
    {
        $c = $this->context();
        $revision = $this->submitted($c);
        $this->actingAs($c['users']['male'], 'web')->get(route('guru.kkm.show', $c['scope']))->assertForbidden();
        $this->get(route('guru.kkm.export', [$revision, 'xlsx']))->assertForbidden();
        $this->put(route('guru.kkm.save', $revision), ['action' => 'save', 'lock_version' => $revision->lock_version, 'criteria' => $this->input()])->assertForbidden();
        app(DiniyyahKkmWorkflow::class)->review($revision, $c['admin'], $revision->lock_version, true);
        $this->get(route('guru.kkm.show', $c['scope']))->assertOk()->assertSee('75')->assertDontSee('Ajukan untuk ACC');
        $this->get(route('guru.kkm.export', [$revision, 'xlsx']))->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $pdf = $this->get(route('guru.kkm.export', [$revision, 'pdf']))->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->qa('approved.pdf', $pdf->getContent());
        $this->qa('approved.xlsx', $this->get(route('guru.kkm.export', [$revision, 'xlsx']))->getContent());
        $this->qa('reader.html', $this->get(route('guru.kkm.show', $c['scope']))->getContent());
        foreach ($c['subjects'] as $cs) {
            $this->assertSame('75.00', $cs->fresh()->kkm);
            $this->assertSame($revision->id, $cs->fresh()->kkm_revision_id);
        }
        $this->assertDatabaseHas('panel_notifications', ['user_id' => $c['users']['male']->id, 'notification_type' => 'kkm_approved']);
    }

    public function test_author_and_management_pages_render_and_guards_stay_separate(): void
    {
        $c = $this->context();
        $this->actingAs($c['users']['female'], 'web')->get(route('guru.kkm.index'))->assertOk()->assertSee('Akidah Akhlak');
        $this->get(route('guru.kkm.show', $c['scope']))->assertOk()->assertSee('Mulai menyusun KKM');
        $this->post(route('guru.kkm.start', $c['scope']))->assertRedirect();
        $this->get(route('guru.kkm.show', $c['scope']))->assertOk()->assertSee('Ajukan untuk ACC');
        $this->qa('author.html', $this->get(route('guru.kkm.show', $c['scope']))->getContent());
        $this->get(route('admin.kkm.index'))->assertRedirect();
        $this->actingAs($c['admin'], 'admin')->get(route('admin.kkm.index'))->assertOk()->assertSee('Tetapkan guru penyusun');
        $this->get(route('admin.notifications.feed'))->assertOk();
        $this->get(route('admin.notifications.index'))->assertOk();
    }

    public function test_revision_keeps_old_active_until_acc_and_hides_pending_from_readers(): void
    {
        $c = $this->context();
        $old = $this->approved($c, 70);
        $new = $this->submitted($c, 80);
        $this->assertSame($old->id, $c['scope']->fresh()->active_revision_id);
        $this->actingAs($c['users']['male'])->get(route('guru.kkm.show', $c['scope']))->assertOk()->assertDontSee('Menunggu ACC');
        $this->get(route('guru.kkm.export', [$new, 'pdf']))->assertForbidden();
        app(DiniyyahKkmWorkflow::class)->review($new, $c['admin'], $new->lock_version, true);
        $this->assertSame($new->id, $c['scope']->fresh()->active_revision_id);
        $this->assertSame('superseded', $old->fresh()->status);
        $this->assertSame(2, $new->version);
    }

    public function test_reassignment_invalidates_pending_submission_and_old_author(): void
    {
        $c = $this->context();
        $revision = $this->submitted($c);
        app(DiniyyahKkmWorkflow::class)->assign($c['subjects']['male'], $c['teachers']['male']->id, $c['admin']);
        $this->assertSame('draft', $revision->fresh()->status);
        $this->assertFalse(app(DiniyyahKkmAccess::class)->writes($c['users']['female'], $c['scope']->fresh()));
        $this->expectException(ValidationException::class);
        app(DiniyyahKkmWorkflow::class)->review($revision, $c['admin'], $revision->lock_version, true);
    }

    public function test_self_approval_is_denied_even_with_admin_role(): void
    {
        $c = $this->context();
        $revision = $this->submitted($c);
        $c['users']['female']->assignRole('admin');
        $this->expectException(ValidationException::class);
        app(DiniyyahKkmWorkflow::class)->review($revision, $c['users']['female'], $revision->lock_version, true);
    }

    public function test_request_revision_requires_reason_and_can_be_resubmitted(): void
    {
        $c = $this->context();
        $revision = $this->submitted($c);
        $this->actingAs($c['admin'], 'admin')->post(route('admin.kkm.review', $revision), ['decision' => 'revise', 'lock_version' => $revision->lock_version])->assertSessionHasErrors('kkm');
        app(DiniyyahKkmWorkflow::class)->review($revision, $c['admin'], $revision->lock_version, false, 'Lengkapi pertimbangan fasilitas.');
        $this->assertSame('needs_revision', $revision->fresh()->status);
        $revision = $revision->fresh();
        $new = app(DiniyyahKkmWorkflow::class)->save($revision, $this->input(80), $c['users']['female'], $revision->lock_version, true);
        $this->assertSame('submitted', $new->status);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'kkm', 'event' => 'revision_requested']);
    }

    public function test_double_approval_is_rejected_without_duplicate_notifications(): void
    {
        $c = $this->context();
        $revision = $this->submitted($c);
        $version = $revision->lock_version;
        app(DiniyyahKkmWorkflow::class)->review($revision, $c['admin'], $version, true);
        $count = PanelNotification::where('notification_type', 'kkm_approved')->count();
        try {
            app(DiniyyahKkmWorkflow::class)->review($revision, $c['admin'], $version, true);
            $this->fail('ACC ganda harus ditolak.');
        } catch (ValidationException) {
        }
        $this->assertSame($count, PanelNotification::where('notification_type', 'kkm_approved')->count());
    }

    public function test_locked_ledger_blocks_acc_and_preserves_history(): void
    {
        $c = $this->context();
        $revision = $this->submitted($c);
        DiniyyahLedgerSnapshot::create(['academic_term_id' => $c['term']->id, 'classroom_term_id' => $c['subjects']['male']->classroom_term_id, 'title' => 'Leger historis', 'status' => 'locked']);
        $this->expectException(ValidationException::class);
        app(DiniyyahKkmWorkflow::class)->review($revision, $c['admin'], $revision->lock_version, true);
    }

    public function test_old_kkm_cannot_bypass_approval_or_locking_ledger(): void
    {
        $c = $this->context();
        $set = DiniyyahAssessmentSet::create(['diniyyah_class_subject_id' => $c['subjects']['male']->id, 'title' => 'Akidah', 'assessment_method' => 'direct_final', 'kkm' => 99, 'status' => 'validated']);
        $this->assertNull($set->kkm);
        $snapshot = app(DiniyyahLedgerGenerator::class)->generate($set->classSubject->classroomTerm);
        $this->assertContains('kkm_not_approved', array_column($snapshot->snapshot_data['issues'], 'code'));
        $this->expectException(ValidationException::class);
        app(DiniyyahLedgerWorkflow::class)->lock($snapshot, $c['admin']);
    }

    public function test_acc_updates_pass_status_without_modifying_final_score_and_marks_ledger_stale(): void
    {
        $c = $this->context();
        $set = DiniyyahAssessmentSet::create(['diniyyah_class_subject_id' => $c['subjects']['male']->id, 'title' => 'Akidah', 'assessment_method' => 'direct_final', 'status' => 'validated']);
        $student = Student::create(['name' => 'Santri KKM', 'gender' => 'male', 'nis' => 'KKM1']);
        $enrollment = ClassEnrollment::create(['academic_term_id' => $c['term']->id, 'classroom_term_id' => $set->classSubject->classroom_term_id, 'student_id' => $student->id, 'status' => 'active']);
        $result = DiniyyahAssessmentResult::create(['diniyyah_assessment_set_id' => $set->id, 'class_enrollment_id' => $enrollment->id, 'final_score' => 72, 'is_complete' => true]);
        $snapshot = app(DiniyyahLedgerGenerator::class)->generate($set->classSubject->classroomTerm);
        $revision = $this->approved($c, 70);
        $this->assertTrue($result->fresh()->is_passed);
        $this->assertSame('72.00', $result->fresh()->final_score);
        $this->assertSame($revision->id, $result->fresh()->kkm_revision_id);
        $this->assertSame('submitted', $set->fresh()->status);
        $this->assertTrue($snapshot->fresh()->snapshot_data['kkm_stale']);
        $this->approved($c, 80);
        $this->assertFalse($result->fresh()->is_passed);
        $this->assertSame('72.00', $result->fresh()->final_score);
    }

    public function test_new_parallel_class_inherits_approved_kkm_and_import_cannot_override(): void
    {
        $c = $this->context();
        $revision = $this->approved($c);
        $classroom = Classroom::create(['name' => 'M1 paralel', 'level_name' => 'mustawa 1', 'gender_group' => 'male']);
        $ct = ClassroomTerm::create(['academic_term_id' => $c['term']->id, 'classroom_id' => $classroom->id, 'name' => 'Paralel']);
        $cs = DiniyyahClassSubject::create(['classroom_term_id' => $ct->id, 'subject_id' => $c['subject']->id, 'assessment_method' => 'weighted', 'kkm' => 99]);
        $this->assertSame('75.00', $cs->kkm);
        $this->assertSame($revision->id, $cs->kkm_revision_id);
        $cs->update(['kkm' => 99]);
        $this->assertSame('75.00', $cs->fresh()->kkm);
    }

    public function test_other_subject_level_and_semester_do_not_inherit(): void
    {
        $c = $this->context();
        $this->approved($c);
        foreach ([['mustawa' => 2], ['subject' => true], ['term' => true]] as $variant) {
            $term = isset($variant['term']) ? AcademicTerm::create(['academic_year_id' => $c['term']->academic_year_id, 'name' => 'Genap', 'semester' => 'genap', 'kkm_workflow_enabled' => true]) : $c['term'];
            $subject = isset($variant['subject']) ? DiniyyahSubject::create(['code' => 'fiqih-kkm', 'name' => 'Fiqih', 'default_assessment_method' => 'weighted']) : $c['subject'];
            $classroom = Classroom::create(['name' => 'Kelas lain', 'level_name' => 'Mustawa '.($variant['mustawa'] ?? 1), 'gender_group' => 'male']);
            $ct = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => 'Kelas lain']);
            $cs = DiniyyahClassSubject::create(['classroom_term_id' => $ct->id, 'subject_id' => $subject->id, 'assessment_method' => 'weighted', 'kkm' => 99]);
            $this->assertNull($cs->kkm);
        }
    }

    public function test_xlsx_preserves_teacher_text_and_contains_categories_and_formula_description(): void
    {
        $c = $this->context();
        $revision = app(DiniyyahKkmWorkflow::class)->startRevision($c['scope'], $c['users']['female']);
        $input = $this->input(65);
        $input['complexity']['reason'] = '=HYPERLINK("https://example.invalid","x")';
        $revision = app(DiniyyahKkmWorkflow::class)->save($revision, $input, $c['users']['female'], 0);
        $bytes = app(DiniyyahKkmExportService::class)->xlsx(collect([$revision]));
        $path = tempnam(sys_get_temp_dir(), 'kkm-test-');
        try {
            file_put_contents($path, $bytes);
            $workbook = IOFactory::load($path);
            $this->assertSame('s', $workbook->getSheetByName('Pertimbangan')->getCell('I6')->getDataType());
            $this->assertSame($input['complexity']['reason'], $workbook->getSheetByName('Pertimbangan')->getCell('I6')->getValue());
            $this->assertSame('Tinggi', $workbook->getSheetByName('Pertimbangan')->getCell('H6')->getValue());
            $this->assertStringContainsString('BELUM DISETUJUI', $workbook->getActiveSheet()->getCell('E6')->getValue());
            $this->assertSame(65, $workbook->getActiveSheet()->getCell('J6')->getValue());
            $workbook->disconnectWorksheets();
        } finally {
            @unlink($path);
        }
    }

    public function test_approved_version_is_snapshotted_in_ledger_and_report_card(): void
    {
        $c = $this->context();
        $revision = $this->approved($c, 75);
        $set = DiniyyahAssessmentSet::create(['diniyyah_class_subject_id' => $c['subjects']['male']->id, 'title' => 'Akidah Akhlak', 'assessment_method' => 'direct_final', 'status' => 'validated', 'appears_on_ledger' => true, 'appears_on_report' => true]);
        $student = Student::create(['name' => 'Santri Rapor KKM', 'gender' => 'male', 'nis' => 'KKM-RAPOR', 'status' => 'active']);
        $enrollment = ClassEnrollment::create(['academic_term_id' => $c['term']->id, 'classroom_term_id' => $set->classSubject->classroom_term_id, 'student_id' => $student->id, 'status' => 'active']);
        DiniyyahAssessmentResult::create(['diniyyah_assessment_set_id' => $set->id, 'class_enrollment_id' => $enrollment->id, 'final_score' => 72, 'is_complete' => true, 'kkm' => 75, 'kkm_revision_id' => $revision->id]);
        $snapshot = app(DiniyyahLedgerGenerator::class)->generate($set->classSubject->classroomTerm);
        $this->assertSame(0, $snapshot->snapshot_data['summary']['blocking_issues']);
        $this->assertSame($revision->id, $snapshot->snapshot_data['columns'][0]['kkm_revision_id']);
        app(DiniyyahLedgerWorkflow::class)->lock($snapshot, $c['admin']);
        $snapshot->refresh();
        app(ReportCardGenerator::class)->generateFromLedgerSnapshot($snapshot, $c['admin']->id);
        $report = ReportCard::where('class_enrollment_id', $enrollment->id)->firstOrFail();
        $line = $report->lines()->firstOrFail();
        $this->assertSame('75.00', $line->kkm);
        $this->assertSame($revision->id, $line->kkm_revision_id);
        $this->assertSame('Jayyid', $line->score_letter);
        $this->assertFalse($line->is_passed);
        app(ReportCardWorkflow::class)->lock($report, $c['admin']);
        $this->assertSame('locked', $report->fresh()->status);
        $this->expectException(ValidationException::class);
        app(DiniyyahKkmWorkflow::class)->startRevision($c['scope']->fresh(), $c['users']['female']);
    }

    public function test_stale_browser_cannot_overwrite_a_newer_draft(): void
    {
        $c = $this->context();
        $workflow = app(DiniyyahKkmWorkflow::class);
        $revision = $workflow->startRevision($c['scope'], $c['users']['female']);
        $workflow->save($revision, $this->input(75), $c['users']['female'], 0);
        $this->expectException(ValidationException::class);
        $workflow->save($revision, $this->input(80), $c['users']['female'], 0);
    }

    public function test_admin_and_kabag_notifications_link_to_their_authenticated_portal(): void
    {
        $c = $this->context();
        $this->submitted($c);
        $response = $this->actingAs($c['admin'], 'admin')->getJson(route('admin.notifications.feed'))->assertOk();
        $links = collect($response->json('notifications'))->where('type', 'kkm_submitted')->pluck('link_url');
        $this->assertContains(route('admin.kkm.show', $c['scope']), $links);
        $this->post(route('admin.notifications.read-all'))->assertRedirect();
        $this->assertDatabaseHas('panel_notifications', ['user_id' => $c['admin']->id, 'notification_type' => 'kkm_submitted', 'status' => 'read']);
    }

    public function test_pdf_and_xlsx_handle_long_reasons_without_losing_text(): void
    {
        $c = $this->context();
        $revision = app(DiniyyahKkmWorkflow::class)->startRevision($c['scope'], $c['users']['female']);
        $input = $this->input();
        foreach ($input as &$criterion) {
            $criterion['reason'] = str_repeat('Pertimbangan pembelajaran dan fasilitas kelas. ', 70).'PENUTUP_ALASAN_KKM';
        }
        unset($criterion);
        $revision = app(DiniyyahKkmWorkflow::class)->save($revision, $input, $c['users']['female'], 0);
        $response = $this->actingAs($c['users']['female'])->get(route('guru.kkm.export', [$revision, 'pdf']))->assertOk();
        $this->qa('long-reasons.pdf', $response->getContent());
        $bytes = app(DiniyyahKkmExportService::class)->xlsx(collect([$revision]));
        $this->qa('long-reasons.xlsx', $bytes);
        $path = tempnam(sys_get_temp_dir(), 'kkm-long-');
        try {
            file_put_contents($path, $bytes);
            $workbook = IOFactory::load($path);
            $sheet = $workbook->getSheetByName('Pertimbangan');
            $text = '';
            for ($row = 6; $row <= $sheet->getHighestRow(); $row++) {
                $text .= $sheet->getCell('I'.$row)->getValue();
                $this->assertLessThanOrEqual(150, $sheet->getRowDimension($row)->getRowHeight());
            }
            $this->assertSame(3, substr_count($text, 'PENUTUP_ALASAN_KKM'));
            $workbook->disconnectWorksheets();
        } finally {
            @unlink($path);
        }
    }

    public function test_incomplete_snapshot_cannot_be_locked_even_after_kkm_approval(): void
    {
        $c = $this->context();
        $this->approved($c);
        DiniyyahAssessmentSet::create(['diniyyah_class_subject_id' => $c['subjects']['male']->id, 'title' => 'Akidah', 'assessment_method' => 'direct_final', 'status' => 'validated', 'appears_on_ledger' => true]);
        $snapshot = DiniyyahLedgerSnapshot::create(['academic_term_id' => $c['term']->id, 'classroom_term_id' => $c['subjects']['male']->classroom_term_id, 'title' => 'Leger kosong', 'status' => 'draft', 'snapshot_data' => ['summary' => ['blocking_issues' => 0]]]);
        $this->expectException(\DomainException::class);
        app(DiniyyahLedgerWorkflow::class)->lock($snapshot, $c['admin']);
    }

    private function qa(string $name, string $content): void
    {
        if (getenv('KKM_QA') !== '1') {
            return;
        }
        $directory = storage_path('app/kkm-qa');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($directory.'/'.$name, $content);
    }
}
