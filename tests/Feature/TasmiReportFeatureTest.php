<?php

namespace Tests\Feature;

use App\Filament\Resources\TasmiRecords\Pages\EditTasmiRecord;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\HomeroomAssignment;
use App\Models\PanelNotification;
use App\Models\School;
use App\Models\Student;
use App\Models\TasmiExaminerAssignment;
use App\Models\TasmiRecord;
use App\Models\Teacher;
use App\Models\User;
use App\Services\NotificationDispatcher;
use App\Services\TasmiReportService;
use App\Services\TasmiReviewService;
use App\Services\TasmiWaliReminderPreferenceService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TasmiReportFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'kabag_tahfidz', 'guru'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function context(): array
    {
        $school = School::create(['name' => 'GQ']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $previousTerm = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Genap', 'semester' => 'genap', 'starts_at' => '2026-01-01', 'ends_at' => '2026-06-30', 'is_active' => false]);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil', 'starts_at' => '2026-07-01', 'ends_at' => '2026-12-31', 'is_active' => true]);
        $classroom = Classroom::create(['name' => 'Mustawa 1 Ikhwan', 'gender_group' => 'male', 'level_name' => 'M1', 'sort_order' => 1, 'is_active' => true]);
        $classroomTerm = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => $classroom->name, 'status' => 'active']);
        $previousClassroomTerm = ClassroomTerm::create(['academic_term_id' => $previousTerm->id, 'classroom_id' => $classroom->id, 'name' => $classroom->name, 'status' => 'inactive']);

        $pjUser = User::factory()->create(['name' => 'PJ Tasmi']);
        $pjUser->assignRole('guru');
        $pj = Teacher::create(['user_id' => $pjUser->id, 'name' => 'PJ Tasmi', 'gender' => 'male', 'niy' => 'PJ-01', 'status' => 'active']);
        TasmiExaminerAssignment::create(['academic_term_id' => $term->id, 'teacher_id' => $pj->id, 'status' => 'active']);

        $otherPjUser = User::factory()->create(['name' => 'PJ Lain']);
        $otherPjUser->assignRole('guru');
        $otherPj = Teacher::create(['user_id' => $otherPjUser->id, 'name' => 'PJ Lain', 'gender' => 'male', 'niy' => 'PJ-02', 'status' => 'active']);

        $waliUser = User::factory()->create(['name' => 'Wali Kelas']);
        $waliUser->assignRole('guru');
        $wali = Teacher::create(['user_id' => $waliUser->id, 'name' => 'Wali Kelas', 'gender' => 'male', 'niy' => 'WK-01', 'status' => 'active']);
        HomeroomAssignment::create(['classroom_term_id' => $classroomTerm->id, 'teacher_id' => $wali->id, 'starts_at' => '2026-07-01']);
        HomeroomAssignment::create(['classroom_term_id' => $previousClassroomTerm->id, 'teacher_id' => $wali->id, 'starts_at' => '2026-01-01', 'ends_at' => '2026-06-30']);

        $kabag = User::factory()->create(['name' => 'Kabag Tahfidz']);
        $kabag->assignRole('kabag_tahfidz');

        return compact('term', 'previousTerm', 'classroomTerm', 'previousClassroomTerm', 'pjUser', 'pj', 'otherPjUser', 'otherPj', 'waliUser', 'wali', 'kabag');
    }

    private function record(array $ctx, Student $student, array $overrides = []): TasmiRecord
    {
        $term = $overrides['academic_term'] ?? $ctx['term'];
        $classroomTerm = $overrides['classroom_term'] ?? $ctx['classroomTerm'];
        $examiner = $overrides['examiner'] ?? $ctx['pj'];
        $inputBy = $overrides['input_by'] ?? $ctx['pjUser'];
        $enrollment = ClassEnrollment::firstOrCreate([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'student_id' => $student->id,
        ], ['status' => 'active']);

        return TasmiRecord::create([
            'academic_term_id' => $term->id,
            'classroom_term_id' => $classroomTerm->id,
            'class_enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'examiner_teacher_id' => $examiner->id,
            'exam_type' => $overrides['exam_type'] ?? '1_juz',
            'juz_start' => $overrides['juz_start'] ?? 30,
            'juz_end' => $overrides['juz_end'] ?? 30,
            'exam_date' => $overrides['exam_date'] ?? '2026-08-15',
            'predicate' => $overrides['predicate'] ?? 'mumtaz',
            'notes' => $overrides['notes'] ?? null,
            'input_by' => $inputBy->id,
            'input_at' => now(),
            'last_updated_by' => $inputBy->id,
        ]);
    }

    public function test_juz_filter_includes_ascending_and_descending_ranges_in_pages_and_exports(): void
    {
        $ctx = $this->context();
        $student = Student::create(['name' => 'Santri Rentang', 'gender' => 'male', 'nis' => '030', 'status' => 'active']);
        $descending = $this->record($ctx, $student, ['exam_type' => '5_juz', 'juz_start' => 30, 'juz_end' => 26]);
        $ascending = $this->record($ctx, $student, ['exam_type' => '5_juz', 'juz_start' => 26, 'juz_end' => 30, 'exam_date' => '2026-08-16']);
        $this->record($ctx, $student, ['juz_start' => 25, 'juz_end' => 25, 'exam_date' => '2026-08-17']);
        $other = Student::create(['name' => 'Santri PJ Lain', 'gender' => 'male', 'nis' => '031', 'status' => 'active']);
        $this->record($ctx, $other, ['examiner' => $ctx['otherPj'], 'input_by' => $ctx['otherPjUser'], 'exam_type' => '5_juz', 'juz_start' => 30, 'juz_end' => 26]);
        $service = app(TasmiReportService::class);

        foreach ([26, 28, 30] as $juz) {
            $this->actingAs($ctx['pjUser'])->get(route('guru.tasmi.records', ['juz' => $juz]))
                ->assertOk()->assertDontSee('Santri PJ Lain')->assertViewHas('report', fn ($report) => $report['summary']['total_records'] === 2);
            $query = $service->forExaminer($ctx['pj']);
            $export = $service->exportReport($service->applyFilters(clone $query, ['juz' => $juz]), ['juz' => $juz], $service->options($query));
            $this->assertSame(2, $export['summary']['total_records']);
            $this->assertCount(2, $export['rows']);
        }
        $this->assertSame([$descending->id, $ascending->id], $service->applyFilters($service->forExaminer($ctx['pj']), ['juz' => 28])->orderBy('id')->pluck('id')->all());
        $this->assertSame(1, $service->applyFilters($service->forExaminer($ctx['pj']), ['juz' => 25])->count());
        $this->assertSame(0, $service->applyFilters($service->forExaminer($ctx['pj']), ['juz' => 24])->count());
    }

    public function test_examiner_report_covers_own_history_and_exports_without_other_examiner_data(): void
    {
        $ctx = $this->context();
        $ownCurrent = Student::create(['name' => 'Santri PJ Kini', 'gender' => 'male', 'nis' => '101', 'status' => 'active']);
        $ownPast = Student::create(['name' => 'Santri PJ Lama', 'gender' => 'male', 'nis' => '102', 'status' => 'active']);
        $other = Student::create(['name' => 'Santri PJ Lain', 'gender' => 'male', 'nis' => '103', 'status' => 'active']);
        $this->record($ctx, $ownCurrent);
        $this->record($ctx, $ownPast, ['academic_term' => $ctx['previousTerm'], 'classroom_term' => $ctx['previousClassroomTerm'], 'exam_date' => '2026-06-15']);
        $this->record($ctx, $other, ['examiner' => $ctx['otherPj'], 'input_by' => $ctx['otherPjUser'], 'exam_date' => '2026-08-16']);

        $this->actingAs($ctx['pjUser'])->get(route('guru.tasmi.records'))
            ->assertOk()
            ->assertSee('Santri PJ Kini')
            ->assertSee('Santri PJ Lama')
            ->assertDontSee('Santri PJ Lain');

        $this->actingAs($ctx['pjUser'])->get(route('guru.tasmi.export', 'xlsx'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_wali_class_sees_only_records_within_homeroom_assignment_period_and_can_dismiss_reminder(): void
    {
        $ctx = $this->context();
        $visible = Student::create(['name' => 'Santri Wali', 'gender' => 'male', 'nis' => '201', 'status' => 'active']);
        $visibleRecord = $this->record($ctx, $visible);

        $otherClass = Classroom::create(['name' => 'Mustawa 2 Ikhwan', 'gender_group' => 'male', 'level_name' => 'M2', 'sort_order' => 2, 'is_active' => true]);
        $otherClassTerm = ClassroomTerm::create(['academic_term_id' => $ctx['term']->id, 'classroom_id' => $otherClass->id, 'name' => $otherClass->name, 'status' => 'active']);
        $hidden = Student::create(['name' => 'Santri Bukan Wali', 'gender' => 'male', 'nis' => '202', 'status' => 'active']);
        $this->record($ctx, $hidden, ['classroom_term' => $otherClassTerm, 'exam_date' => '2026-08-16']);

        $this->actingAs($ctx['waliUser'])->get(route('guru.tasmi-wali.index'))
            ->assertOk()
            ->assertSee('Santri Wali')
            ->assertDontSee('Santri Bukan Wali');

        $this->actingAs($ctx['waliUser'])->get(route('guru.dashboard'))
            ->assertOk()
            ->assertSee("Persetujuan Tasmi'", false);

        $this->actingAs($ctx['waliUser'])->postJson(route('guru.tasmi-wali.reminder.dismiss'))
            ->assertOk();
        $this->assertDatabaseHas('panel_user_preferences', ['user_id' => $ctx['waliUser']->id, 'panel_key' => 'guru-tasmi-wali-reminder']);

        $this->actingAs($ctx['waliUser'])->get(route('guru.dashboard'))
            ->assertOk()
            ->assertDontSee(' data-tasmi-wali-modal>', false)
            ->assertSee("hasil Tasmi' belum disetujui", false);

        $this->travel(1)->seconds();
        $visibleRecord->update(['predicate' => 'jayyid']);

        $this->actingAs($ctx['waliUser'])->get(route('guru.dashboard'))
            ->assertOk()
            ->assertSee("Persetujuan Tasmi'", false)
            ->assertSee('Santri Wali');

        $this->actingAs($ctx['waliUser'])->get(route('guru.tasmi-wali.show', $visibleRecord))
            ->assertOk()
            ->assertSee('Santri Wali');
        $this->assertDatabaseHas('panel_notifications', [
            'user_id' => $ctx['waliUser']->id,
            'link_url' => route('guru.tasmi-wali.show', $visibleRecord),
            'status' => 'read',
        ]);
    }

    public function test_kabag_can_read_and_export_all_reports_but_cannot_create_tasmi_record(): void
    {
        $ctx = $this->context();
        $first = Student::create(['name' => 'Santri Laporan Satu', 'gender' => 'male', 'nis' => '301', 'status' => 'active']);
        $second = Student::create(['name' => 'Santri Laporan Dua', 'gender' => 'male', 'nis' => '302', 'status' => 'active']);
        $firstRecord = $this->record($ctx, $first);
        $this->record($ctx, $second, ['examiner' => $ctx['otherPj'], 'input_by' => $ctx['otherPjUser'], 'exam_date' => '2026-08-16']);

        $this->actingAs($ctx['kabag'], 'admin')->get(route('admin.tasmi-report.index'))
            ->assertOk()
            ->assertSee('Santri Laporan Satu')
            ->assertSee('Santri Laporan Dua');
        $this->actingAs($ctx['kabag'], 'admin')->get(route('admin.tasmi-report.show', $firstRecord))->assertOk()->assertSee('Riwayat perubahan');
        $this->actingAs($ctx['kabag'], 'admin')->get(route('admin.tasmi-report.export', 'pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($ctx['kabag'], 'admin')->get('/admin/tasmi-records/create')->assertForbidden();
    }

    public static function tasmiRanges(): array
    {
        return ['satu juz' => ['1_juz', 30, 30], 'lima juz naik' => ['5_juz', 1, 5], 'lima juz turun' => ['5_juz', 30, 26]];
    }

    #[DataProvider('tasmiRanges')]
    public function test_full_input_review_correction_resubmission_approval_workflow(string $type, int $start, int $end): void
    {
        $ctx = $this->context();
        $student = Student::create(['name' => 'Santri Persetujuan', 'gender' => 'male', 'nis' => '401', 'status' => 'active']);
        ClassEnrollment::create(['academic_term_id' => $ctx['term']->id, 'classroom_term_id' => $ctx['classroomTerm']->id, 'student_id' => $student->id, 'status' => 'active']);
        $payload = ['classroom_term_id' => $ctx['classroomTerm']->id, 'student_id' => $student->id,
            'exam_type' => $type, 'juz_start' => $start, 'juz_end' => $end, 'exam_date' => '2026-08-15', 'predicate' => 'jayyid',
            'review_status' => 'approved', 'reviewed_by' => $ctx['pjUser']->id];
        $this->actingAs($ctx['pjUser'])->post(route('guru.tasmi.store'), $payload)->assertRedirect(route('guru.tasmi.records'));
        $record = TasmiRecord::sole();
        $this->assertSame('pending', $record->review_status);
        $this->assertNull($record->reviewed_by);

        $this->actingAs($ctx['waliUser'])->get(route('guru.tasmi-wali.show', $record))->assertOk()->assertSee('Setujui untuk piagam')->assertSee('data-tasmi-correction-dialog', false);
        // Reading and archiving notices must never count as approval.
        PanelNotification::where('user_id', $ctx['waliUser']->id)->update(['status' => 'read', 'archived_at' => now()]);
        $this->assertSame(1, app(TasmiWaliReminderPreferenceService::class)->reminderFor($ctx['waliUser'])['count']);

        $this->post(route('guru.tasmi-wali.review', $record), ['review_version' => 0, 'decision' => 'correction_requested', 'correction_reason' => 'Predikat seharusnya Mumtaz.'])->assertRedirect();
        $record->refresh();
        $this->assertSame('correction_requested', $record->review_status);
        $this->assertSame('jayyid', $record->predicate);
        $this->assertDatabaseHas('panel_notifications', ['user_id' => $ctx['pjUser']->id, 'notification_type' => 'tasmi_correction_requested']);
        $this->actingAs($ctx['pjUser'])->get(route('guru.dashboard'))->assertOk()->assertSee('Predikat seharusnya Mumtaz.')->assertSee('data-tasmi-pj-dialog', false);
        $this->get(route('guru.tasmi.edit', $record))->assertOk()->assertSee('Predikat seharusnya Mumtaz.');
        $this->put(route('guru.tasmi.update', $record), [...$payload, 'predicate' => 'mumtaz', 'review_version' => 1])->assertRedirect(route('guru.tasmi.edit', $record));
        $record->refresh();
        $this->assertSame('pending', $record->review_status);
        $this->assertNull($record->reviewed_by);
        $this->assertNull($record->correction_reason);
        $this->assertNull(app(TasmiWaliReminderPreferenceService::class)->correctionsFor($ctx['pjUser']));
        $this->actingAs($ctx['waliUser'])->post(route('guru.tasmi-wali.review', $record), ['review_version' => 2, 'decision' => 'approved'])->assertRedirect();
        $record->refresh();
        $this->assertSame('approved', $record->review_status);
        $this->assertSame($ctx['waliUser']->id, $record->reviewed_by);
        $this->assertNull(app(TasmiWaliReminderPreferenceService::class)->reminderFor($ctx['waliUser']));
        $this->get(route('guru.dashboard'))->assertOk()->assertDontSee('data-tasmi-wali-reminder', false);
        $this->assertDatabaseHas('score_change_logs', ['score_id' => $record->id, 'reason' => 'approved_tasmi', 'old_score' => null, 'new_score' => null]);
        $this->assertDatabaseHas('panel_notifications', ['audience_role' => 'admin', 'notification_type' => 'tasmi_approved']);
    }

    public function test_other_class_and_outside_assignment_period_cannot_be_read_reviewed_or_exported(): void
    {
        $ctx = $this->context();
        $student = Student::create(['name' => 'Santri Rahasia Kelas Lain', 'gender' => 'male', 'nis' => '402', 'status' => 'active']);
        $otherClass = Classroom::create(['name' => 'Kelas lain', 'gender_group' => 'male', 'is_active' => true]);
        $ct = ClassroomTerm::create(['academic_term_id' => $ctx['term']->id, 'classroom_id' => $otherClass->id, 'name' => 'Kelas lain', 'status' => 'active']);
        $record = $this->record($ctx, $student, ['classroom_term' => $ct]);
        $otherStudent = Student::create(['name' => 'Santri Di Luar Masa Wali', 'gender' => 'male', 'nis' => '410', 'status' => 'active']);
        $outside = $this->record($ctx, $otherStudent, ['exam_date' => '2026-06-15']);
        foreach ([$record, $outside] as $hidden) {
            $this->actingAs($ctx['waliUser'])->get(route('guru.tasmi-wali.show', $hidden))->assertNotFound();
            $this->post(route('guru.tasmi-wali.review', $hidden), ['review_version' => 0, 'decision' => 'approved'])->assertNotFound();
            $this->get(route('guru.tasmi-wali.index', ['student_id' => $student->id, 'juz' => 30]))->assertOk()->assertDontSee($student->name);
        }
        $response = $this->get(route('guru.tasmi-wali.export', ['format' => 'xlsx', 'student_id' => $student->id]));
        $response->assertOk();
        $this->assertStringNotContainsString($student->name, $this->xlsxText($response->getContent()));
        $this->actingAs($ctx['pjUser'])->post(route('guru.tasmi-wali.review', $record), ['review_version' => 0, 'decision' => 'approved'])->assertForbidden();
    }

    public function test_review_requires_reason_and_rejects_stale_or_duplicate_decisions(): void
    {
        $ctx = $this->context();
        $record = $this->record($ctx, Student::create(['name' => 'Santri Versi', 'gender' => 'male', 'nis' => '403', 'status' => 'active']));
        $this->actingAs($ctx['waliUser'])->post(route('guru.tasmi-wali.review', $record), ['review_version' => 0, 'decision' => 'correction_requested', 'correction_reason' => '   '])->assertSessionHasErrors('correction_reason');
        $record->update(['notes' => 'Diubah setelah wali membuka detail']);
        $this->post(route('guru.tasmi-wali.review', $record), ['review_version' => 0, 'decision' => 'approved'])->assertSessionHasErrors('review');
        $this->assertSame('pending', $record->fresh()->review_status);
        $this->post(route('guru.tasmi-wali.review', $record), ['review_version' => 1, 'decision' => 'approved'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('guru.tasmi-wali.review', $record), ['review_version' => 1, 'decision' => 'approved'])->assertSessionHasErrors('review');
        $this->assertSame(1, DB::table('score_change_logs')->where('reason', 'approved_tasmi')->count());
    }

    public function test_admin_download_contains_only_approved_records_and_edit_invalidates_approval(): void
    {
        $ctx = $this->context();
        $approved = $this->record($ctx, Student::create(['name' => 'Santri Siap Piagam', 'gender' => 'male', 'nis' => '404', 'status' => 'active']));
        $pending = $this->record($ctx, Student::create(['name' => 'Santri Belum Approve', 'gender' => 'male', 'nis' => '405', 'status' => 'active']));
        $legacy = $this->record($ctx, Student::create(['name' => 'Santri Data Lama', 'gender' => 'male', 'nis' => '406', 'status' => 'active']));
        DB::table('tasmi_records')->where('id', $legacy->id)->update(['review_status' => null]);
        $returned = $this->record($ctx, Student::create(['name' => 'Santri Perlu Koreksi', 'gender' => 'male', 'nis' => '407', 'status' => 'active']));
        $this->actingAs($ctx['waliUser'])->post(route('guru.tasmi-wali.review', $approved), ['review_version' => 0, 'decision' => 'approved'])->assertSessionHasNoErrors();
        $this->post(route('guru.tasmi-wali.review', $returned), ['review_version' => 0, 'decision' => 'correction_requested', 'correction_reason' => 'Tanggal salah'])->assertSessionHasNoErrors();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $response = $this->actingAs($admin, 'admin')->get(route('admin.tasmi-report.export', 'xlsx'))->assertOk();
        $text = $this->xlsxText($response->getContent());
        $this->assertStringContainsString('Santri Siap Piagam', $text);
        foreach (['Santri Belum Approve', 'Santri Data Lama', 'Santri Perlu Koreksi'] as $name) {
            $this->assertStringNotContainsString($name, $text);
        }
        $this->assertStringContainsString('Wali Kelas', $text);
        // Observer also protects direct model/admin edits, including a day-label-only edit.
        $approved->refresh()->update(['exam_day_label' => 'Sabtu']);
        $this->assertSame('pending', $approved->fresh()->review_status);
        $this->assertNull($approved->fresh()->reviewed_at);
        $response = $this->get(route('admin.tasmi-report.export', 'xlsx'))->assertOk();
        $this->assertStringNotContainsString('Santri Siap Piagam', $this->xlsxText($response->getContent()));
        $this->assertDatabaseCount('tasmi_records', 4);
    }

    public function test_review_and_audit_roll_back_when_notification_fails(): void
    {
        $ctx = $this->context();
        $record = $this->record($ctx, Student::create(['name' => 'Santri Transaksi', 'gender' => 'male', 'nis' => '408', 'status' => 'active']));
        $before = DB::table('tasmi_records')->where('id', $record->id)->first();
        $auditCount = DB::table('activity_log')->count();
        $scoreCount = DB::table('score_change_logs')->count();
        $this->mock(NotificationDispatcher::class, function ($mock): void {
            $mock->shouldReceive('dispatchToExaminer')->once()->andThrow(new \RuntimeException('Uji gagal notifikasi'));
        });
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($ctx['waliUser'])->post(route('guru.tasmi-wali.review', $record), ['review_version' => 0, 'decision' => 'approved']);
            $this->fail('Exception notifikasi seharusnya diteruskan.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Uji gagal notifikasi', $e->getMessage());
        }
        $this->assertEquals($before, DB::table('tasmi_records')->where('id', $record->id)->first());
        $this->assertSame($auditCount, DB::table('activity_log')->count());
        $this->assertSame($scoreCount, DB::table('score_change_logs')->count());
    }

    public function test_additive_migration_preserves_all_legacy_results_soft_deletes_and_audit_history(): void
    {
        $ctx = $this->context();
        $student = Student::create(['name' => 'Santri Migrasi Lama', 'gender' => 'male', 'nis' => '409', 'status' => 'active']);
        $record = $this->record($ctx, $student, ['exam_type' => '5_juz', 'juz_start' => 30, 'juz_end' => 26, 'notes' => 'Catatan lama harus utuh']);
        $deleted = $this->record($ctx, $student, ['exam_date' => '2026-08-16']);
        $deleted->delete();
        // Recreate the pre-feature schema in the isolated, in-memory test DB only.
        $migration = require database_path('migrations/2026_11_23_000001_add_wali_review_to_tasmi_records.php');
        $migration->down();
        $before = DB::table('tasmi_records')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $audits = DB::table('activity_log')->orderBy('id')->get()->all();
        $scores = DB::table('score_change_logs')->orderBy('id')->get()->all();
        $migration->up();
        $columns = array_keys($before[0]);
        $after = DB::table('tasmi_records')->select($columns)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $this->assertSame($before, $after);
        $this->assertEquals($audits, DB::table('activity_log')->orderBy('id')->get()->all());
        $this->assertEquals($scores, DB::table('score_change_logs')->orderBy('id')->get()->all());
        $this->assertDatabaseHas('tasmi_records', ['id' => $record->id, 'review_status' => null, 'review_version' => 0]);
        $this->assertSoftDeleted('tasmi_records', ['id' => $deleted->id]);
        $this->actingAs($ctx['waliUser'])->get(route('guru.tasmi-wali.show', $record))->assertOk()->assertSee('belum diverifikasi');
        $this->post(route('guru.tasmi-wali.review', $record), ['review_version' => 0, 'decision' => 'approved'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('approved', $record->fresh()->review_status);
    }

    public function test_correction_can_move_wrong_student_and_class_without_creating_a_second_result(): void
    {
        $ctx = $this->context();
        $record = $this->record($ctx, Student::create(['name' => 'Santri Salah Pilih', 'gender' => 'male', 'nis' => '411', 'status' => 'active']));
        $class = Classroom::create(['name' => 'Kelas Tujuan', 'gender_group' => 'male', 'is_active' => true]);
        $ct = ClassroomTerm::create(['academic_term_id' => $ctx['term']->id, 'classroom_id' => $class->id, 'name' => $class->name, 'status' => 'active']);
        HomeroomAssignment::create(['classroom_term_id' => $ct->id, 'teacher_id' => $ctx['otherPj']->id, 'starts_at' => '2026-07-01']);
        $student = Student::create(['name' => 'Santri Yang Benar', 'gender' => 'male', 'nis' => '412', 'status' => 'active']);
        $enrollment = ClassEnrollment::create(['academic_term_id' => $ctx['term']->id, 'classroom_term_id' => $ct->id, 'student_id' => $student->id, 'status' => 'active']);
        $this->actingAs($ctx['waliUser'])->post(route('guru.tasmi-wali.review', $record), ['review_version' => 0, 'decision' => 'correction_requested', 'correction_reason' => 'Salah memilih santri dan kelas'])->assertSessionHasNoErrors();
        $this->actingAs($ctx['pjUser'])->get(route('guru.tasmi.edit', $record))->assertOk()->assertSee('Santri Yang Benar');
        $this->put(route('guru.tasmi.update', $record), ['review_version' => 1, 'class_enrollment_id' => $enrollment->id, 'exam_type' => '1_juz', 'juz_start' => 30, 'juz_end' => 30, 'exam_date' => '2026-08-15', 'predicate' => 'mumtaz'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('tasmi_records', 1);
        $this->assertDatabaseHas('tasmi_records', ['id' => $record->id, 'student_id' => $student->id, 'classroom_term_id' => $ct->id, 'review_status' => 'pending']);
        $this->actingAs($ctx['waliUser'])->get(route('guru.tasmi-wali.show', $record))->assertNotFound();
        $this->actingAs($ctx['otherPjUser'])->get(route('guru.tasmi-wali.show', $record))->assertOk()->assertSee('Santri Yang Benar');
        $this->assertDatabaseHas('panel_notifications', ['user_id' => $ctx['otherPjUser']->id, 'notification_type' => 'tasmi_updated']);
    }

    public function test_identity_correction_rejects_other_gender_or_other_semester(): void
    {
        $ctx = $this->context();
        $record = $this->record($ctx, Student::create(['name' => 'Santri Tetap Aman', 'gender' => 'male', 'nis' => '413', 'status' => 'active']));
        $class = Classroom::create(['name' => 'Kelas Akhwat', 'gender_group' => 'female', 'is_active' => true]);
        $femaleCt = ClassroomTerm::create(['academic_term_id' => $ctx['term']->id, 'classroom_id' => $class->id, 'name' => $class->name, 'status' => 'active']);
        $female = Student::create(['name' => 'Santriwati', 'gender' => 'female', 'nis' => '414', 'status' => 'active']);
        $femaleEnrollment = ClassEnrollment::create(['academic_term_id' => $ctx['term']->id, 'classroom_term_id' => $femaleCt->id, 'student_id' => $female->id, 'status' => 'active']);
        $past = ClassEnrollment::create(['academic_term_id' => $ctx['previousTerm']->id, 'classroom_term_id' => $ctx['previousClassroomTerm']->id, 'student_id' => $record->student_id, 'status' => 'active']);
        $before = $record->fresh()->getAttributes();
        foreach ([$femaleEnrollment, $past] as $invalid) {
            $this->actingAs($ctx['pjUser'])->put(route('guru.tasmi.update', $record), ['class_enrollment_id' => $invalid->id, 'review_version' => 0, 'exam_type' => '1_juz', 'juz_start' => 30, 'juz_end' => 30, 'exam_date' => '2026-08-15', 'predicate' => 'mumtaz'])->assertForbidden();
            $this->assertSame($before, $record->fresh()->getAttributes());
        }
    }

    public function test_pj_can_fix_returned_historical_record_after_assignment_becomes_inactive(): void
    {
        $ctx = $this->context();
        $record = $this->record($ctx, Student::create(['name' => 'Santri Riwayat', 'gender' => 'male', 'nis' => '415', 'status' => 'active']));
        $this->actingAs($ctx['waliUser'])->post(route('guru.tasmi-wali.review', $record), ['review_version' => 0, 'decision' => 'correction_requested', 'correction_reason' => 'Perbaiki catatan'])->assertSessionHasNoErrors();
        TasmiExaminerAssignment::where('teacher_id', $ctx['pj']->id)->update(['status' => 'inactive']);
        $this->actingAs($ctx['pjUser']->fresh())->get(route('guru.tasmi.edit', $record))->assertOk();
        $this->get(route('guru.tasmi.hijri-date', ['date' => '2026-08-15']))->assertOk();
        $this->put(route('guru.tasmi.update', $record), ['review_version' => 1, 'notes' => 'Catatan sudah diperbaiki', 'exam_type' => '1_juz', 'juz_start' => 30, 'juz_end' => 30, 'exam_date' => '2026-08-15', 'predicate' => 'mumtaz'])->assertRedirect(route('guru.tasmi.edit', $record))->assertSessionHasNoErrors();
        $this->get(route('guru.tasmi.edit', $record))->assertOk()->assertSee('Menunggu persetujuan wali kelas');
        $this->assertSame('pending', $record->fresh()->review_status);
    }

    public function test_admin_edit_form_rejects_stale_results_and_resets_approval_on_fresh_edit(): void
    {
        $ctx = $this->context();
        $record = $this->record($ctx, Student::create(['name' => 'Santri Edit Admin', 'gender' => 'male', 'nis' => '416', 'status' => 'active']));
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Livewire::test(EditTasmiRecord::class, ['record' => $record->getRouteKey()])->assertFormSet(['review_version' => 0]);
        app(TasmiReviewService::class)->review($ctx['waliUser'], $record, 0, 'approved', null);
        $page->fillForm(['notes' => 'Form lama'])->call('save')->assertHasFormErrors(['review_version']);
        $this->assertSame('approved', $record->fresh()->review_status);
        $this->assertNull($record->fresh()->notes);
        $freshPage = Livewire::test(EditTasmiRecord::class, ['record' => $record->getRouteKey()])->fillForm(['notes' => 'Koreksi admin'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('pending', $record->fresh()->review_status);
        $this->assertSame('Koreksi admin', $record->fresh()->notes);
        $freshPage->fillForm(['notes' => 'Koreksi admin lanjutan'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Koreksi admin lanjutan', $record->fresh()->notes);
    }

    private function statisticsContext(): array
    {
        $ctx = $this->context();
        $male = Student::create(['name' => 'Santri Statistik Ikhwan', 'gender' => 'male', 'nis' => 'STAT-M', 'status' => 'active']);
        $this->record($ctx, $male);
        $legacy = $this->record($ctx, $male, ['exam_date' => '2026-08-16']);
        DB::table('tasmi_records')->where('id', $legacy->id)->update(['review_status' => null]);
        $approved = $this->record($ctx, $male, ['exam_date' => '2026-08-17', 'exam_type' => '5_juz', 'juz_start' => 30, 'juz_end' => 26]);
        $approved->forceFill(['review_status' => 'approved'])->save();
        $returned = $this->record($ctx, $male, ['exam_date' => '2026-08-18']);
        $returned->forceFill(['review_status' => 'correction_requested', 'correction_reason' => 'Perbaiki predikat'])->save();
        $class = Classroom::create(['name' => 'Kelas Statistik Akhwat', 'gender_group' => 'female', 'is_active' => true]);
        $femaleClass = ClassroomTerm::create(['academic_term_id' => $ctx['term']->id, 'classroom_id' => $class->id, 'name' => $class->name, 'status' => 'active']);
        $female = Student::create(['name' => 'Santri Statistik Akhwat', 'gender' => 'female', 'nis' => 'STAT-F', 'status' => 'active']);
        $this->record($ctx, $female, ['classroom_term' => $femaleClass, 'exam_type' => '5_juz', 'juz_start' => 1, 'juz_end' => 5]);
        $femaleApproved = $this->record($ctx, $female, ['classroom_term' => $femaleClass, 'exam_date' => '2026-08-16', 'examiner' => $ctx['otherPj'], 'input_by' => $ctx['otherPjUser']]);
        $femaleApproved->forceFill(['review_status' => 'approved'])->save();
        $unknown = Student::create(['name' => 'Santri Tanpa Kelas', 'gender' => 'male', 'nis' => 'STAT-U', 'status' => 'active']);
        $unknownRecord = $this->record($ctx, $unknown);
        $unknownRecord->update(['classroom_term_id' => null]);
        $mixedClass = Classroom::create(['name' => 'Kelas Campuran', 'gender_group' => 'mixed', 'is_active' => true]);
        $mixedTerm = ClassroomTerm::create(['academic_term_id' => $ctx['term']->id, 'classroom_id' => $mixedClass->id, 'name' => $mixedClass->name, 'status' => 'active']);
        $mixed = Student::create(['name' => 'Santri Kelas Campuran', 'gender' => 'male', 'nis' => 'STAT-X', 'status' => 'active']);
        $this->record($ctx, $mixed, ['classroom_term' => $mixedTerm]);
        $this->record($ctx, $male, ['exam_date' => '2026-08-19'])->delete();
        $inactive = Student::create(['name' => 'Santri Nonaktif Statistik', 'gender' => 'male', 'nis' => 'STAT-I', 'status' => 'active']);
        $this->record($ctx, $inactive);
        $inactive->update(['status' => 'inactive']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return [...$ctx, 'admin' => $admin];
    }

    public function test_management_statistics_count_each_result_by_class_gender_without_changing_old_data(): void
    {
        $ctx = $this->statisticsContext();
        $before = DB::table('tasmi_records')->orderBy('id')->get()->toJson();
        $auditsBefore = DB::table('activity_log')->count();
        $response = $this->actingAs($ctx['admin'], 'admin')->get(route('admin.tasmi-report.index'))->assertOk()
            ->assertSee('Statistik proses Tasmi\'', false)->assertSee('Kelas laki-laki (Ikhwan)')->assertSee('Kelas perempuan (Akhwat)')->assertSee('Campuran / belum ditentukan');
        $statistics = $response->viewData('workflowStatistics');
        $this->assertSame(['input' => 8, 'pending' => 5, 'approved' => 2, 'correction' => 1], $statistics['total']);
        $this->assertSame(['label' => 'Kelas laki-laki (Ikhwan)', 'input' => 4, 'pending' => 2, 'approved' => 1, 'correction' => 1], $statistics['groups']['male']);
        $this->assertSame(['label' => 'Kelas perempuan (Akhwat)', 'input' => 2, 'pending' => 1, 'approved' => 1, 'correction' => 0], $statistics['groups']['female']);
        $this->assertSame(2, $statistics['groups']['other']['input']);
        $this->assertSame($before, DB::table('tasmi_records')->orderBy('id')->get()->toJson());
        $this->assertSame($auditsBefore, DB::table('activity_log')->count());
    }

    public function test_statistics_follow_report_filters_but_keep_workflow_overview_when_status_is_filtered(): void
    {
        $ctx = $this->statisticsContext();
        $response = $this->actingAs($ctx['admin'], 'admin')->get(route('admin.tasmi-report.index', ['review_status' => 'approved']))->assertOk();
        $this->assertSame(2, $response->viewData('report')['summary']['total_records']);
        $this->assertSame(8, $response->viewData('workflowStatistics')['total']['input']);
        $response = $this->get(route('admin.tasmi-report.index', ['gender_group' => 'female', 'review_status' => 'approved']))->assertOk();
        $this->assertSame(1, $response->viewData('report')['summary']['total_records']);
        $this->assertSame(['input' => 2, 'pending' => 1, 'approved' => 1, 'correction' => 0], $response->viewData('workflowStatistics')['total']);
        $response = $this->get(route('admin.tasmi-report.index', ['exam_type' => '5_juz', 'juz' => 28]))->assertOk();
        $this->assertSame(['input' => 1, 'pending' => 0, 'approved' => 1, 'correction' => 0], $response->viewData('workflowStatistics')['total']);
        $response = $this->get(route('admin.tasmi-report.index', ['date_from' => '2026-08-17', 'date_until' => '2026-08-17', 'classroom_term_id' => $ctx['classroomTerm']->id]))->assertOk();
        $this->assertSame(1, $response->viewData('workflowStatistics')['total']['input']);
        $response = $this->get(route('admin.tasmi-report.index', ['examiner_teacher_id' => $ctx['otherPj']->id]))->assertOk();
        $this->assertSame(['input' => 1, 'pending' => 0, 'approved' => 1, 'correction' => 0], $response->viewData('workflowStatistics')['total']);
        $response = $this->get(route('admin.tasmi-report.index', ['gender_group' => 'other']))->assertOk();
        $this->assertSame(2, $response->viewData('report')['summary']['total_records']);
        $this->assertSame(2, $response->viewData('workflowStatistics')['total']['input']);
        $response = $this->get(route('admin.tasmi-report.index', ['academic_term_id' => $ctx['previousTerm']->id]))->assertOk();
        $this->assertSame(['input' => 0, 'pending' => 0, 'approved' => 0, 'correction' => 0], $response->viewData('workflowStatistics')['total']);
        $this->get(route('admin.tasmi-report.index', ['gender_group' => 'invalid']))->assertSessionHasErrors('gender_group');
    }

    public function test_admin_gender_download_and_statistics_access_do_not_bypass_approval_or_roles(): void
    {
        $ctx = $this->statisticsContext();
        $this->actingAs($ctx['admin'], 'admin')->get('/admin/tasmi-records')->assertOk()->assertSee('Statistik & unduh Tasmi\'');
        $response = $this->actingAs($ctx['admin'], 'admin')->get(route('admin.tasmi-report.export', ['format' => 'xlsx', 'gender_group' => 'female']))->assertOk();
        $text = $this->xlsxText($response->getContent());
        $this->assertStringContainsString('Santri Statistik Akhwat', $text);
        $this->assertStringNotContainsString('Santri Statistik Ikhwan', $text);
        $response = $this->get(route('admin.tasmi-report.index', ['gender_group' => 'female', 'review_status' => 'pending']))->assertOk();
        $this->assertSame(1, $response->viewData('report')['summary']['total_records']);
        $this->actingAs($ctx['kabag'], 'admin')->get(route('admin.tasmi-report.index'))->assertOk()->assertSee('data-tasmi-workflow-statistics', false);
        $this->actingAs($ctx['pjUser'], 'admin')->get(route('admin.tasmi-report.index'))->assertForbidden();
        $this->actingAs($ctx['pjUser'], 'web')->get(route('guru.tasmi.records'))->assertOk()->assertDontSee('data-tasmi-workflow-statistics', false);
    }

    private function xlsxText(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tasmi-review-');
        try {
            file_put_contents($path, $content);
            $workbook = IOFactory::load($path);
            $text = json_encode($workbook->getSheetByName('Detail Tasmi')->toArray(), JSON_UNESCAPED_UNICODE);
            $workbook->disconnectWorksheets();

            return $text;
        } finally {
            @unlink($path);
        }
    }
}
