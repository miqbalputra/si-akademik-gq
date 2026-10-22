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
use App\Models\DiniyyahTeacherAssignment;
use App\Models\PanelNotification;
use App\Models\ReportCard;
use App\Models\ReportCardLine;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\DiniyyahKkmAccess;
use App\Services\DiniyyahKkmCalculator;
use App\Services\DiniyyahKkmResolver;
use App\Services\DiniyyahKkmWorkflow;
use App\Services\DiniyyahLedgerGenerator;
use App\Services\DiniyyahLedgerWorkflow;
use App\Services\ReportCardGenerator;
use App\Services\ReportCardWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DiniyyahSchoolKkmTest extends TestCase
{
    use RefreshDatabase;

    private function context(string $code = 'suluk', string $name = 'Suluk', bool $initialize = true): array
    {
        foreach (['admin', 'kabag_diniyyah', 'guru'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $school = School::create(['name' => 'Sekolah KKM Tetap']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil', 'is_active' => true, 'kkm_workflow_enabled' => true]);
        $subject = DiniyyahSubject::create(['code' => $code, 'name' => $name, 'default_assessment_method' => $code === 'suluk' ? 'direct_final' : 'practical']);
        $subjects = $teachers = $users = [];
        foreach (['female' => 'Mustawa 1', 'male' => 'M1'] as $gender => $level) {
            $user = User::factory()->create(['name' => 'Guru '.$gender]);
            $user->assignRole('guru');
            $teacher = Teacher::create(['user_id' => $user->id, 'name' => $user->name, 'gender' => $gender, 'status' => 'active']);
            $classroom = Classroom::create(['name' => 'M1 '.$gender, 'level_name' => $level, 'gender_group' => $gender, 'is_active' => true]);
            $ct = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => $classroom->name, 'status' => 'active']);
            $create = fn () => DiniyyahClassSubject::create(['classroom_term_id' => $ct->id, 'subject_id' => $subject->id, 'kkm' => 85, 'is_active' => true, 'assessment_method' => 'direct_final', 'appears_on_ledger' => true, 'appears_on_report' => true]);
            $cs = $initialize ? $create() : DiniyyahClassSubject::withoutEvents($create);
            DiniyyahTeacherAssignment::create(['teacher_id' => $teacher->id, 'diniyyah_class_subject_id' => $cs->id]);
            $subjects[$gender] = $cs;
            $teachers[$gender] = $teacher;
            $users[$gender] = $user;
        }
        $scope = app(DiniyyahKkmResolver::class)->scopeFor($subjects['female'], true);

        return compact('admin', 'term', 'subject', 'subjects', 'teachers', 'users', 'scope');
    }

    private function rollout(): void
    {
        (require database_path('migrations/2026_10_22_120000_apply_school_kkm_for_suluk_and_praktik_ibadah.php'))->up();
    }

    private function enrollment(array $c): ClassEnrollment
    {
        $student = Student::create(['name' => 'Santri KKM Sekolah', 'gender' => 'female', 'nis' => 'KKM-SEKOLAH', 'status' => 'active']);

        return ClassEnrollment::create(['academic_term_id' => $c['term']->id, 'classroom_term_id' => $c['subjects']['female']->classroom_term_id, 'student_id' => $student->id, 'status' => 'active']);
    }

    public static function fixedSubjects(): array
    {
        return [['suluk', 'Suluk'], ['praktik_ibadah', 'Praktik Ibadah'], ['praktek_ibadah', 'Praktek Ibadah'], ['legacy_praktek', 'Praktek Ibadah']];
    }

    #[DataProvider('fixedSubjects')]
    public function test_school_policy_is_70_for_both_parallel_classes_without_teacher_approval(string $code, string $name): void
    {
        $c = $this->context($code, $name);
        $revision = $c['scope']->activeRevision;
        $this->assertSame('school_fixed', $revision->status);
        $this->assertSame(70, $revision->final_kkm);
        $this->assertNull($revision->average);
        $this->assertNull($revision->submitted_by);
        $this->assertNull($revision->reviewed_by);
        $this->assertNull($c['scope']->assigned_teacher_id);
        foreach ($c['subjects'] as $cs) {
            $this->assertSame('70.00', $cs->fresh()->kkm);
            $this->assertSame($revision->id, $cs->fresh()->kkm_revision_id);
        }
        foreach ($c['users'] as $user) {
            $this->assertTrue(app(DiniyyahKkmAccess::class)->viewsRevision($user, $revision));
            $this->assertFalse(app(DiniyyahKkmAccess::class)->writes($user, $c['scope']));
        }
        $this->actingAs($c['users']['male'], 'web')->get(route('guru.kkm.index', ['status' => 'school_fixed']))->assertOk()->assertSee($name)->assertSee('Ditetapkan Sekolah');
    }

    public function test_writes_assignment_submission_review_and_revision_are_blocked_on_direct_urls(): void
    {
        $c = $this->context();
        $c['scope']->update(['assigned_teacher_id' => $c['teachers']['female']->id]);
        $revision = $c['scope']->activeRevision;
        $input = array_fill_keys(array_keys(DiniyyahKkmCalculator::CRITERIA), ['score' => 95, 'reason' => 'Tidak boleh mengganti ketetapan sekolah']);
        $this->actingAs($c['users']['female'], 'web')->post(route('guru.kkm.start', $c['scope']))->assertSessionHasErrors('kkm');
        foreach (['save', 'submit'] as $action) {
            $this->put(route('guru.kkm.save', $revision), ['action' => $action, 'criteria' => $input, 'lock_version' => 0])->assertSessionHasErrors('kkm');
        }
        $this->actingAs($c['admin'], 'admin')->post(route('admin.kkm.assign'), ['class_subject_id' => $c['subjects']['female']->id, 'teacher_id' => $c['teachers']['female']->id])->assertSessionHasErrors('kkm');
        foreach (['approve', 'revise'] as $decision) {
            $this->post(route('admin.kkm.review', $revision), ['decision' => $decision, 'notes' => 'Ubah ketetapan', 'lock_version' => 0])->assertSessionHasErrors('kkm');
        }
        $this->assertSame(70, $revision->fresh()->final_kkm);
        $this->assertDatabaseCount('diniyyah_kkm_revisions', 1);
    }

    public function test_school_policy_is_read_only_and_is_excluded_from_bulk_assignment(): void
    {
        $c = $this->context();
        $response = $this->actingAs($c['users']['female'], 'web')->get(route('guru.kkm.show', $c['scope']))->assertOk()
            ->assertSee('KKM tetap:')->assertSee('Sekolah/Kabag Diniyyah')->assertDontSee('Ajukan untuk ACC')->assertDontSee('Mulai menyusun KKM')->assertDontSee('Buat versi revisi');
        $this->qa('school-kkm.html', $response->getContent());
        $this->actingAs($c['admin'], 'admin')->get(route('admin.kkm.show', $c['scope']))->assertOk()->assertDontSee('Ubah guru penyusun')->assertDontSee('Setujui KKM');
        $preview = $this->get(route('admin.kkm.bulk.preview'))->assertOk()->assertSee('KKM tetap 70');
        $this->assertStringNotContainsString('name="selected[]"', $preview->getContent());
        $this->post(route('admin.kkm.bulk.apply'), [
            'academic_term_id' => $c['term']->id, 'preview_token' => $preview->viewData('previewToken'),
            'selected' => [$c['subjects']['female']->id], 'teachers' => [$c['subjects']['female']->id => $c['teachers']['female']->id], 'genders' => [$c['subjects']['female']->id => 'female'],
        ])->assertSessionHasErrors('kkm');
        $this->assertNull($c['scope']->fresh()->assigned_teacher_id);
    }

    public function test_rollout_preserves_grade_values_and_old_versions_but_refreshes_open_kkm_references(): void
    {
        $c = $this->context(initialize: false);
        $old = $c['scope']->revisions()->create(['version' => 1, 'status' => 'approved', 'final_kkm' => 85]);
        $working = $c['scope']->revisions()->create(['version' => 2, 'status' => 'submitted', 'criteria' => ['complexity' => ['score' => 90, 'reason' => 'Alasan lama tetap tersimpan']], 'final_kkm' => 90, 'submitted_by' => $c['users']['female']->id, 'submitted_at' => now(), 'lock_version' => 3]);
        $c['scope']->update(['active_revision_id' => $old->id, 'working_revision_id' => $working->id, 'assigned_teacher_id' => $c['teachers']['female']->id]);
        $set = DiniyyahAssessmentSet::withoutEvents(fn () => DiniyyahAssessmentSet::create(['diniyyah_class_subject_id' => $c['subjects']['female']->id, 'title' => 'Suluk', 'assessment_method' => 'direct_final', 'status' => 'validated', 'kkm' => 85]));
        $set->forceFill(['kkm_revision_id' => $old->id])->saveQuietly();
        $result = DiniyyahAssessmentResult::create(['diniyyah_assessment_set_id' => $set->id, 'class_enrollment_id' => $this->enrollment($c)->id, 'daily_raw_score' => 73, 'exam_raw_score' => 72, 'final_score' => 72.5, 'is_complete' => true, 'is_passed' => false, 'kkm' => 85, 'kkm_revision_id' => $old->id]);
        $snapshot = DiniyyahLedgerSnapshot::create(['academic_term_id' => $c['term']->id, 'classroom_term_id' => $set->classSubject->classroom_term_id, 'title' => 'Leger terbuka', 'status' => 'validated', 'snapshot_data' => ['columns' => [['kkm' => 85, 'kkm_revision_id' => $old->id]], 'summary' => ['blocking_issues' => 0]]]);
        $this->rollout();
        $revision = $c['scope']->fresh()->activeRevision;
        $this->assertSame(3, $revision->version);
        $this->assertSame('school_fixed', $revision->status);
        $this->assertSame('superseded', $old->fresh()->status);
        $this->assertSame('cancelled_by_policy', $working->fresh()->status);
        $this->assertSame('Alasan lama tetap tersimpan', $working->fresh()->criteria['complexity']['reason']);
        $this->assertSame(4, $working->fresh()->lock_version);
        $this->assertNull($c['scope']->fresh()->working_revision_id);
        $this->assertSame('70.00', $set->fresh()->kkm);
        $this->assertSame('submitted', $set->fresh()->status);
        $this->assertSame('72.50', $result->fresh()->final_score);
        $this->assertSame('73.00', $result->fresh()->daily_raw_score);
        $this->assertSame('72.00', $result->fresh()->exam_raw_score);
        $this->assertTrue($result->fresh()->is_passed);
        $this->assertSame($revision->id, $result->fresh()->kkm_revision_id);
        $this->assertTrue($snapshot->fresh()->snapshot_data['kkm_stale']);
        $this->assertSame(85, $snapshot->fresh()->snapshot_data['columns'][0]['kkm']);
        $notifications = PanelNotification::where('notification_type', 'kkm_approved')->count();
        $this->assertSame(2, $notifications);
        $this->rollout();
        $this->assertDatabaseCount('diniyyah_kkm_revisions', 3);
        $this->assertSame($notifications, PanelNotification::where('notification_type', 'kkm_approved')->count());
        $this->assertSame(1, PanelNotification::where('notification_type', 'kkm_approved')->first()->batch_count);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'kkm', 'event' => 'school_fixed']);
    }

    public function test_locked_ledger_and_published_report_keep_historical_thresholds_and_versions(): void
    {
        $c = $this->context(initialize: false);
        $old = $c['scope']->revisions()->create(['version' => 1, 'status' => 'approved', 'final_kkm' => 85]);
        $c['scope']->update(['active_revision_id' => $old->id]);
        $snapshot = DiniyyahLedgerSnapshot::create(['academic_term_id' => $c['term']->id, 'classroom_term_id' => $c['subjects']['female']->classroom_term_id, 'title' => 'Leger historis', 'status' => 'locked', 'snapshot_data' => ['columns' => [['kkm' => 85, 'kkm_revision_id' => $old->id]]]]);
        $enrollment = $this->enrollment($c);
        $report = ReportCard::withoutEvents(fn () => ReportCard::create(['academic_term_id' => $c['term']->id, 'classroom_term_id' => $enrollment->classroom_term_id, 'class_enrollment_id' => $enrollment->id, 'student_id' => $enrollment->student_id, 'report_type' => 'diniyyah', 'status' => 'published']));
        $line = ReportCardLine::withoutEvents(fn () => $report->lines()->create(['line_type' => 'subject', 'source_type' => 'diniyyah_assessment_set', 'subject_name' => 'Suluk', 'kkm' => 85, 'kkm_revision_id' => $old->id, 'score_numeric' => 80]));
        $before = [$snapshot->fresh()->getAttributes(), $report->fresh()->getAttributes(), $line->fresh()->getAttributes(), $old->fresh()->getAttributes()];
        $this->rollout();
        $this->assertSame($before, [$snapshot->fresh()->getAttributes(), $report->fresh()->getAttributes(), $line->fresh()->getAttributes(), $old->fresh()->getAttributes()]);
        $this->assertSame(85, app(DiniyyahKkmResolver::class)->resolve($c['subjects']['female'])['kkm']);
        $this->assertSame('85.00', $c['subjects']['male']->fresh()->kkm);
        $this->assertDatabaseCount('diniyyah_kkm_revisions', 1);
    }

    public function test_frozen_scope_without_kkm_history_keeps_its_legacy_value(): void
    {
        $c = $this->context(initialize: false);
        $c['scope']->delete();
        DiniyyahLedgerSnapshot::create(['academic_term_id' => $c['term']->id, 'classroom_term_id' => $c['subjects']['female']->classroom_term_id, 'title' => 'Leger lama', 'status' => 'locked']);
        $this->rollout();
        $this->assertSame('85.00', app(DiniyyahKkmResolver::class)->resolve($c['subjects']['female'])['kkm']);
        $this->assertDatabaseCount('diniyyah_kkm_scopes', 0);
    }

    public function test_legacy_semester_is_not_enabled_or_rewritten(): void
    {
        $c = $this->context(initialize: false);
        $c['term']->update(['kkm_workflow_enabled' => false]);
        $this->rollout();
        $this->assertFalse($c['term']->fresh()->kkm_workflow_enabled);
        $this->assertSame('85.00', $c['subjects']['female']->fresh()->kkm);
        $this->assertDatabaseCount('diniyyah_kkm_revisions', 0);
    }

    public function test_new_parallel_classes_and_assessments_cannot_override_school_kkm(): void
    {
        $c = $this->context();
        $revision = $c['scope']->activeRevision;
        $classroom = Classroom::create(['name' => 'Paralel baru', 'level_name' => 'mustawa 1']);
        $ct = ClassroomTerm::create(['academic_term_id' => $c['term']->id, 'classroom_id' => $classroom->id, 'name' => 'Paralel baru']);
        $cs = DiniyyahClassSubject::create(['classroom_term_id' => $ct->id, 'subject_id' => $c['subject']->id, 'kkm' => 99, 'assessment_method' => 'direct_final']);
        $set = DiniyyahAssessmentSet::create(['diniyyah_class_subject_id' => $cs->id, 'title' => 'Suluk paralel', 'assessment_method' => 'direct_final', 'kkm' => 99]);
        $this->assertSame('70.00', $cs->fresh()->kkm);
        $this->assertSame('70.00', $set->fresh()->kkm);
        $set->update(['kkm' => 95]);
        $this->assertSame('70.00', $set->fresh()->kkm);
        $this->assertSame($revision->id, $set->fresh()->kkm_revision_id);
        $this->assertSame($revision->id, app(DiniyyahKkmResolver::class)->ensureApproved($set)['kkm_revision_id']);
    }

    public function test_fixed_kkm_is_snapshotted_in_ledger_and_report_without_waiting_for_acc(): void
    {
        $c = $this->context();
        $set = DiniyyahAssessmentSet::create(['diniyyah_class_subject_id' => $c['subjects']['female']->id, 'title' => 'Suluk', 'assessment_method' => 'direct_final', 'status' => 'validated', 'appears_on_ledger' => true, 'appears_on_report' => true]);
        $enrollment = $this->enrollment($c);
        $revision = $c['scope']->activeRevision;
        DiniyyahAssessmentResult::create(['diniyyah_assessment_set_id' => $set->id, 'class_enrollment_id' => $enrollment->id, 'final_score' => 70, 'kkm' => 70, 'kkm_revision_id' => $revision->id, 'is_complete' => true, 'is_passed' => true]);
        $snapshot = app(DiniyyahLedgerGenerator::class)->generate($set->classSubject->classroomTerm);
        $this->assertSame(0, $snapshot->snapshot_data['summary']['blocking_issues']);
        $this->assertSame($revision->id, $snapshot->snapshot_data['columns'][0]['kkm_revision_id']);
        app(DiniyyahLedgerWorkflow::class)->lock($snapshot, $c['admin']);
        app(ReportCardGenerator::class)->generateFromLedgerSnapshot($snapshot->fresh(), $c['admin']->id);
        $report = ReportCard::where('class_enrollment_id', $enrollment->id)->sole();
        $this->assertSame('70.00', $report->lines()->first()->kkm);
        $this->assertSame($revision->id, $report->lines()->first()->kkm_revision_id);
        $this->assertTrue($report->lines()->first()->is_passed);
        app(ReportCardWorkflow::class)->lock($report, $c['admin']);
        $this->assertSame('locked', $report->fresh()->status);
    }

    public function test_exports_identify_school_policy_without_fake_criteria_or_approval(): void
    {
        $c = $this->context();
        $revision = $c['scope']->activeRevision;
        $this->actingAs($c['users']['female'], 'web');
        $xlsx = $this->get(route('guru.kkm.export', [$revision, 'xlsx']))->assertOk()->getContent();
        $this->qa('school-kkm.xlsx', $xlsx);
        $path = tempnam(sys_get_temp_dir(), 'school-kkm-');
        try {
            file_put_contents($path, $xlsx);
            $workbook = IOFactory::load($path);
            $summary = $workbook->getActiveSheet();
            $this->assertSame('Ditetapkan Sekolah', $summary->getCell('E6')->getValue());
            $this->assertNull($summary->getCell('F6')->getValue());
            $this->assertNull($summary->getCell('I6')->getValue());
            $this->assertSame(70, $summary->getCell('J6')->getValue());
            $this->assertSame('Sekolah/Kabag Diniyyah', $workbook->getSheetByName('Pertimbangan')->getCell('D6')->getValue());
            $this->assertSame('Ketetapan sekolah', $workbook->getSheetByName('Pertimbangan')->getCell('F6')->getValue());
            $workbook->disconnectWorksheets();
        } finally {
            @unlink($path);
        }
        $pdf = $this->get(route('guru.kkm.export', [$revision, 'pdf']))->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->qa('school-kkm.pdf', $pdf);
        $this->get(route('guru.kkm.recap', ['format' => 'xlsx', 'status' => 'school_fixed']))->assertOk();
    }

    public function test_other_subjects_keep_the_teacher_approval_workflow(): void
    {
        $c = $this->context('praktik_fiqih', 'Praktik Fiqih');
        $this->assertNull($c['subject']->schoolKkm());
        $this->assertNull($c['scope']->active_revision_id);
        $this->assertNull($c['subjects']['female']->kkm);
    }

    public function test_school_version_cannot_be_reassigned_or_edited_after_subject_metadata_changes(): void
    {
        $c = $this->context();
        $c['subject']->update(['code' => 'renamed_subject', 'name' => 'Nama mapel diperbarui']);
        $c['scope']->update(['assigned_teacher_id' => $c['teachers']['female']->id]);
        $this->actingAs($c['users']['female'], 'web')->post(route('guru.kkm.start', $c['scope']))->assertSessionHasErrors('kkm');
        $this->actingAs($c['admin'], 'admin')->post(route('admin.kkm.assign'), ['class_subject_id' => $c['subjects']['female']->id, 'teacher_id' => $c['teachers']['male']->id])->assertSessionHasErrors('kkm');
        $this->get(route('admin.kkm.show', $c['scope']))->assertOk()->assertDontSee('Ubah guru penyusun');
        $this->assertSame(70, $c['scope']->fresh()->activeRevision->final_kkm);
    }

    public function test_enabling_a_legacy_open_term_also_initializes_its_school_kkm(): void
    {
        $c = $this->context(initialize: false);
        $c['term']->update(['kkm_workflow_enabled' => false]);
        $subject = DiniyyahSubject::create(['code' => 'fiqih_regular', 'name' => 'Fiqih']);
        $cs = DiniyyahClassSubject::create(['classroom_term_id' => $c['subjects']['female']->classroom_term_id, 'subject_id' => $subject->id, 'assessment_method' => 'weighted', 'is_active' => true]);
        DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $cs->id, 'teacher_id' => $c['teachers']['female']->id]);
        app(DiniyyahKkmWorkflow::class)->assign($cs, $c['teachers']['female']->id, $c['admin']);
        $this->assertSame('school_fixed', $c['scope']->fresh()->activeRevision->status);
        $this->assertSame('70.00', $c['subjects']['female']->fresh()->kkm);
        $this->assertSame('70.00', $c['subjects']['male']->fresh()->kkm);
    }

    private function qa(string $name, string $content): void
    {
        if (getenv('KKM_QA') === '1') {
            $directory = storage_path('app/kkm-qa');
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            file_put_contents($directory.'/'.$name, $content);
        }
    }
}
