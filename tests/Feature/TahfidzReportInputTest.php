<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\HomeroomAssignment;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\TahfidzUasResult;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AttendanceRecapService;
use App\Services\DiniyyahCombinedLedger;
use App\Services\DiniyyahLedgerWorkflow;
use App\Services\ReportCardPrintService;
use App\Services\ReportCardWorkflow;
use App\Services\TahfidzReportSources;
use App\Services\TahfidzReportWorkflow;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TahfidzReportInputTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        foreach (['guru', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $user = User::factory()->create();
        $user->assignRole('guru');
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

    public function test_teacher_gender_is_enforced_for_lists_and_direct_submission_in_both_directions(): void
    {
        [$male, $boy, $term, $class] = $this->context();
        $girl = Student::create(['name' => 'Santri Akhwat', 'nis' => '002', 'gender' => 'female', 'status' => 'active']);
        $girlEnrollment = ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'student_id' => $girl->id, 'status' => 'active']);
        $female = User::factory()->create();
        $female->assignRole('guru');
        Teacher::create(['user_id' => $female->id, 'name' => 'Ustadzah', 'gender' => 'female', 'status' => 'active']);
        $data = $this->values() + ['version' => 0, 'action' => 'final'];
        $this->actingAs($male)->get(route('guru.tahfidz-report.index', ['classroom_term_id' => $class->id]))->assertOk()->assertSee('Santri Ikhwan')->assertDontSee('Santri Akhwat');
        $this->actingAs($male)->post(route('guru.tahfidz-report.save', $girlEnrollment), $data)->assertForbidden();
        $male->assignRole('admin');
        $this->actingAs($male)->post(route('guru.tahfidz-report.save', $girlEnrollment), $data)->assertForbidden();
        $this->actingAs($female)->get(route('guru.tahfidz-report.index', ['classroom_term_id' => $class->id]))->assertOk()->assertSee('Santri Akhwat')->assertDontSee('Santri Ikhwan');
        $this->actingAs($female)->post(route('guru.tahfidz-report.save', $boy), $data)->assertForbidden();
        $this->actingAs($female)->post(route('guru.tahfidz-report.save', $girlEnrollment), $data)->assertSessionHasNoErrors();
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
        $this->actingAs($user)->post(route('guru.tahfidz-report.save', $enrollment), ['action' => 'final', 'version' => 0])->assertSessionHasErrors('tahfidz');
        $this->actingAs($user)->post(route('guru.tahfidz-report.save', $enrollment), array_replace($this->values(), ['uas_score' => 101, 'action' => 'final', 'version' => 0]))->assertSessionHasErrors('uas_score');
        $this->assertDatabaseCount('tahfidz_report_entries', 0);
    }

    public function test_only_class_homeroom_can_review_and_monitor_shows_missing_children(): void
    {
        [$wali, $enrollment, $term, $class] = $this->context();
        $other = User::factory()->create();
        $other->assignRole('guru');
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
        $this->actingAs($user)->post(route('guru.tahfidz-report.save', $enrollment), $this->values() + ['version' => 2, 'action' => 'final'])->assertSessionHasErrors('tahfidz');
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
        $this->actingAs($user)->get(route('guru.tahfidz-report.index'))->assertOk()
            ->assertViewHas('selectedEnrollment', null)->assertDontSee('name="uas_score"', false);
        $this->get(route('guru.tahfidz-report.index', ['classroom_term_id' => $class->id]))->assertOk()
            ->assertSee('Santri Ikhwan — NIS 001')->assertDontSee('name="uas_score"', false);
        $url = route('guru.tahfidz-report.index', ['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'enrollment_id' => $enrollment->id]);
        $response = $this->get($url)->assertOk()->assertSee('name="uas_score"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'name="uas_score"'));
        $otherRoom = Classroom::create(['name' => 'Kelas Lain']);
        $otherClass = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $otherRoom->id, 'name' => 'Kelas Lain']);
        $otherChild = Student::create(['name' => 'Santri Kelas Lain', 'nis' => '009', 'gender' => 'male', 'status' => 'active']);
        $other = ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $otherClass->id, 'student_id' => $otherChild->id, 'status' => 'active']);
        $this->get(route('guru.tahfidz-report.index', ['classroom_term_id' => $class->id, 'enrollment_id' => $other->id]))->assertForbidden();
        $this->get(route('guru.tahfidz-report.index', ['enrollment_id' => $enrollment->id]))->assertForbidden();
        $this->post(route('guru.tahfidz-report.save', $enrollment), $this->values() + ['version' => 0, 'action' => 'final'])
            ->assertRedirect(route('guru.tahfidz-report.index', ['academic_term_id' => $term->id, 'classroom_term_id' => $class->id]));
        $this->get(route('guru.tahfidz-report.index', ['academic_term_id' => $term->id, 'classroom_term_id' => $class->id]))
            ->assertOk()->assertViewHas('selectedEnrollment', null)->assertSee('Juz 29 dan 30')->assertDontSee('name="uas_score"', false);
    }

    public function test_own_entries_list_preserves_teachers_contributions_and_labels_later_changes(): void
    {
        [$user, $enrollment, $term, $class] = $this->context();
        $workflow = app(TahfidzReportWorkflow::class);
        $own = $workflow->save($user, $enrollment, $this->values(), true, 0);
        $before = $own->fresh()->toArray();
        $other = User::factory()->create();
        $other->assignRole('guru');
        Teacher::create(['user_id' => $other->id, 'name' => 'Guru Lain', 'gender' => 'male', 'status' => 'active']);
        $workflow->save($other, $enrollment, array_replace($this->values(), ['uas_score' => 55]), true, 1);
        $anotherStudent = Student::create(['name' => 'Isian Guru Lain', 'nis' => '010', 'gender' => 'male', 'status' => 'active']);
        $another = ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $class->id, 'student_id' => $anotherStudent->id, 'status' => 'active']);
        $workflow->save($other, $another, $this->values(), true, 0);
        $this->actingAs($user)->get(route('guru.tahfidz-report.index'))->assertOk()
            ->assertViewHas('history', fn ($history) => $history->count() === 1 && $history->first()->id === $own->id)
            ->assertSee('Ada pembaruan dari guru lain')->assertDontSee('Isian Guru Lain');
        $this->get(route('guru.tahfidz-report.index', ['classroom_term_id' => $class->id, 'enrollment_id' => $enrollment->id]))
            ->assertOk()->assertSee('value="55.00"', false);
        $this->assertSame($before, $own->fresh()->toArray());
        $this->assertDatabaseCount('tahfidz_report_entries', 3);
    }
}
