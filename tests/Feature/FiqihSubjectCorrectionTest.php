<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\ClassSession;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahKkmScope;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\DiniyyahKkmAccess;
use App\Services\DiniyyahKkmAssignmentRoster;
use App\Services\FiqihCorrectionBackup;
use App\Services\FiqihSubjectCorrection;
use App\Services\RekapJurnalGuruService;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FiqihSubjectCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.disks.fiqih-test' => ['driver' => 'local', 'root' => storage_path('framework/testing/disks/fiqih-test'), 'throw' => true]]);
        Storage::fake('fiqih-test');
        config(['backup.disk' => 'fiqih-test']);
    }

    public function test_preview_has_no_database_or_backup_writes(): void
    {
        $c = $this->context();
        $before = $this->rows();
        $preview = app(FiqihSubjectCorrection::class)->preview();
        $this->assertEmpty($preview['blockers']);
        $this->assertSame([$c['sourceClass']->id], $preview['class_subject_ids']);
        $this->assertSame([$c['scope']->id], $preview['kkm_scope_ids']);
        $this->assertSame($before, $this->rows());
        $this->assertEmpty(Storage::disk('fiqih-test')->allFiles());
        $this->artisan('diniyyah:correct-fiqih')->assertSuccessful();
        $this->assertSame($before, $this->rows());
    }

    public function test_correction_preserves_journals_absences_grades_assignments_and_kkm_history(): void
    {
        $c = $this->context();
        $before = $this->rows();
        $recapBefore = app(RekapJurnalGuruService::class)->build($c['term']->id, null, null);
        $this->assertCount(2, app(DiniyyahKkmAssignmentRoster::class)->forTerm($c['term'])['rows']);
        $service = app(FiqihSubjectCorrection::class);
        $result = $service->apply($service->preview()['fingerprint']);
        $this->assertTrue($result['changed']);
        $this->assertSame(1, $result['class_subjects_updated']);
        $this->assertSame(1, $result['kkm_scopes_updated']);
        $this->assertFalse($c['source']->fresh()->is_active);
        $this->assertNull($c['source']->fresh()->deleted_at);
        $this->assertSame($c['target']->id, $c['sourceClass']->fresh()->subject_id);
        $this->assertSame($c['target']->id, $c['scope']->fresh()->subject_id);
        foreach ($before as $table => $rows) {
            if (! in_array($table, ['diniyyah_subjects', 'diniyyah_class_subjects', 'diniyyah_kkm_scopes'], true)) {
                $this->assertSame($rows, $this->rows()[$table], $table.' must remain byte-for-byte equivalent');
            }
        }
        $roster = app(DiniyyahKkmAssignmentRoster::class)->forTerm($c['term'])['rows'];
        $this->assertCount(1, $roster);
        $this->assertCount(2, $roster[0]['teachers']);
        $this->assertSame($c['target']->id, $roster[0]['subject_id']);
        $this->assertSame($c['teacher']->id, $roster[0]['assigned_teacher_id']);
        $this->assertCount(2, app(DiniyyahKkmAccess::class)->eligibleTeachers($c['scope']->fresh())->get());
        $this->assertSame($recapBefore['stats'], app(RekapJurnalGuruService::class)->build($c['term']->id, null, null)['stats']);
        $snapshot = json_decode(Storage::disk('fiqih-test')->get($result['backup']['path']), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($before['diniyyah_class_subjects'], $snapshot['before']['diniyyah_class_subjects']);
        $this->assertFalse($service->apply($service->preview()['fingerprint'])['changed']);
        $this->assertCount(1, Storage::disk('fiqih-test')->allFiles());
    }

    public function test_coexisting_class_subjects_including_archives_block_every_change(): void
    {
        $c = $this->context();
        $duplicate = DiniyyahClassSubject::create([
            'classroom_term_id' => $c['sourceClass']->classroom_term_id, 'subject_id' => $c['target']->id,
            'assessment_method' => 'direct_final', 'kkm' => 80,
        ]);
        $duplicate->delete();
        $this->assertBlockedWithoutWrites();
    }

    public function test_coexisting_kkm_scopes_do_not_overwrite_either_teacher_or_revision(): void
    {
        $c = $this->context();
        $other = DiniyyahKkmScope::create([
            'academic_term_id' => $c['term']->id, 'subject_id' => $c['target']->id,
            'mustawa' => 1, 'assigned_teacher_id' => $c['otherTeacher']->id,
        ]);
        $revision = $other->revisions()->create(['version' => 1, 'status' => 'approved', 'final_kkm' => 80]);
        $other->update(['active_revision_id' => $revision->id]);
        $this->assertBlockedWithoutWrites();
    }

    public function test_different_kkm_values_in_parallel_classes_are_not_silently_selected(): void
    {
        $c = $this->context();
        DB::table('diniyyah_class_subjects')->where('id', $c['targetClass']->id)->update(['kkm' => 80]);
        $this->assertBlockedWithoutWrites();
    }

    public function test_production_requires_maintenance_and_rejects_local_backup_storage(): void
    {
        $this->context();
        $before = $this->rows();
        $this->app['env'] = 'production';
        try {
            try {
                app(FiqihSubjectCorrection::class)->apply(str_repeat('a', 64));
                $this->fail('Production harus maintenance');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('maintenance', $exception->getMessage());
            }
            try {
                app(FiqihCorrectionBackup::class)->save(['before' => []]);
                $this->fail('Backup production tidak boleh di kontainer');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('S3 privat', $exception->getMessage());
            }
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertSame($before, $this->rows());
        $this->assertEmpty(Storage::disk('fiqih-test')->allFiles());
    }

    public function test_downloaded_coolify_backup_keeps_a_verified_undo_snapshot_without_using_s3(): void
    {
        $c = $this->context();
        $before = $this->rows();
        config(['backup.disk' => 'unconfigured-s3']);
        // The safety archive must persist even if optional event logging is disabled.
        activity()->disableLogging();
        $maintenance = Mockery::mock(MaintenanceMode::class);
        $maintenance->shouldReceive('active')->andReturnTrue();
        $this->app->instance(MaintenanceMode::class, $maintenance);
        $this->app['env'] = 'production';
        try {
            $service = app(FiqihSubjectCorrection::class);
            $result = $service->apply($service->preview()['fingerprint'], $this->coolifyBackup());
        } finally {
            $this->app['env'] = 'testing';
            activity()->enableLogging();
        }
        $this->assertTrue($result['changed']);
        $this->assertTrue($result['backup']['snapshot_verified']);
        $this->assertSame('operator_confirmed_download', $result['backup']['external_backup']['verification']);
        $this->assertSame($this->coolifyBackup()['file'], $result['backup']['external_backup']['file']);
        $archive = DB::table('activity_log')->where('id', $result['backup']['activity_log_id'])->first();
        $this->assertSame('fiqih_correction_snapshot', $archive->event);
        $properties = json_decode($archive->properties, true, flags: JSON_THROW_ON_ERROR);
        foreach (['diniyyah_subjects', 'diniyyah_class_subjects', 'diniyyah_kkm_scopes'] as $table) {
            $this->assertSame($before[$table], $properties['snapshot']['before'][$table]);
        }
        $this->assertSame($before['diniyyah_class_journals'], $this->rows()['diniyyah_class_journals']);
        $this->assertSame($before['diniyyah_class_journal_absences'], $this->rows()['diniyyah_class_journal_absences']);
        $this->assertSame($before['diniyyah_scores'], $this->rows()['diniyyah_scores']);
        $this->assertSame($c['target']->id, $c['sourceClass']->fresh()->subject_id);
        $this->assertEmpty(Storage::disk('fiqih-test')->allFiles());
    }

    public static function invalidCoolifyBackups(): array
    {
        return [
            'missing filename' => [['file' => '', 'sha256' => str_repeat('a', 64)]],
            'missing digest' => [['file' => 'school.dump.gz', 'sha256' => '']],
            'invalid digest' => [['file' => 'school.dump.gz', 'sha256' => 'not-a-digest']],
            'path instead of filename' => [['file' => '../school.dump.gz', 'sha256' => str_repeat('a', 64)]],
            'URL instead of filename' => [['file' => 'https://backup.invalid/file?secret=value', 'sha256' => str_repeat('a', 64)]],
        ];
    }

    #[DataProvider('invalidCoolifyBackups')]
    public function test_incomplete_coolify_backup_details_do_not_disable_backup_protection(array $backup): void
    {
        $this->context();
        $before = $this->rows();
        $service = app(FiqihSubjectCorrection::class);
        try {
            $service->apply($service->preview()['fingerprint'], $backup);
            $this->fail('Rujukan backup tidak valid wajib ditolak.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('nama file backup Coolify', $exception->getMessage());
        }
        $this->assertSame($before, $this->rows());
        $this->assertSame(0, DB::table('activity_log')->where('event', 'fiqih_correction_snapshot')->count());
    }

    public function test_coolify_snapshot_corruption_prevents_all_subject_changes(): void
    {
        $this->context();
        DB::unprepared("CREATE TRIGGER corrupt_fiqih_archive AFTER INSERT ON activity_log WHEN NEW.event = 'fiqih_correction_snapshot' BEGIN UPDATE activity_log SET properties = '{}' WHERE id = NEW.id; END");
        $before = $this->rows();
        $service = app(FiqihSubjectCorrection::class);
        try {
            $service->apply($service->preview()['fingerprint'], $this->coolifyBackup());
            $this->fail('Arsip yang tidak cocok setelah dibaca ulang harus ditolak.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Snapshot database gagal diverifikasi', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER corrupt_fiqih_archive');
        }
        $this->assertSame($before, $this->rows());
        $this->assertSame(0, DB::table('activity_log')->where('event', 'fiqih_correction_snapshot')->count());
    }

    public function test_coolify_command_supports_explicit_downloaded_backup_and_its_digest(): void
    {
        $c = $this->context();
        $service = app(FiqihSubjectCorrection::class);
        $this->artisan('diniyyah:correct-fiqih', [
            '--apply' => true, '--fingerprint' => $service->preview()['fingerprint'],
            '--coolify-backup' => $this->coolifyBackup()['file'],
            '--coolify-backup-sha256' => $this->coolifyBackup()['sha256'],
        ])->assertSuccessful();
        $this->assertSame($c['target']->id, $c['sourceClass']->fresh()->subject_id);
        $this->assertSame(1, DB::table('activity_log')->where('event', 'fiqih_correction_snapshot')->count());
    }

    public function test_locked_historical_documents_block_all_semesters_without_rewriting_snapshots(): void
    {
        $c = $this->context();
        DiniyyahLedgerSnapshot::create([
            'academic_term_id' => $c['term']->id, 'classroom_term_id' => $c['sourceClass']->classroom_term_id,
            'title' => 'Riwayat terkunci', 'status' => 'locked', 'snapshot_data' => ['subject' => 'Fiqih Ibadah', 'kkm' => 75],
        ]);
        $this->assertBlockedWithoutWrites();
    }

    public function test_old_semesters_and_archived_class_subjects_are_corrected_without_changing_the_archive(): void
    {
        $c = $this->context();
        $old = $this->oldSemester($c, $c['source']);
        $old['classSubject']->delete();
        $archivedAt = DB::table('diniyyah_class_subjects')->where('id', $old['classSubject']->id)->value('deleted_at');
        $service = app(FiqihSubjectCorrection::class);
        $result = $service->apply($service->preview()['fingerprint']);
        $this->assertSame(2, $result['class_subjects_updated']);
        $this->assertSame($c['target']->id, DB::table('diniyyah_class_subjects')->where('id', $old['classSubject']->id)->value('subject_id'));
        $this->assertSame($archivedAt, DB::table('diniyyah_class_subjects')->where('id', $old['classSubject']->id)->value('deleted_at'));
    }

    public function test_a_locked_older_source_semester_blocks_the_whole_correction(): void
    {
        $c = $this->context();
        $old = $this->oldSemester($c, $c['source']);
        DiniyyahLedgerSnapshot::create([
            'academic_term_id' => $old['term']->id, 'classroom_term_id' => $old['classSubject']->classroom_term_id,
            'title' => 'Leger semester lama', 'status' => 'published', 'snapshot_data' => ['subject' => 'Fiqih Ibadah'],
        ]);
        $this->assertBlockedWithoutWrites();
    }

    public function test_an_unaffected_older_fiqih_document_is_not_rewritten_or_unlocked(): void
    {
        $c = $this->context();
        $old = $this->oldSemester($c, $c['target']);
        $ledger = DiniyyahLedgerSnapshot::create([
            'academic_term_id' => $old['term']->id, 'classroom_term_id' => $old['classSubject']->classroom_term_id,
            'title' => 'Fiqih lama sudah benar', 'status' => 'published', 'snapshot_data' => ['subject' => 'Fiqih'],
        ]);
        $before = $ledger->fresh()->getRawOriginal();
        $service = app(FiqihSubjectCorrection::class);
        $this->assertEmpty($service->preview()['blockers']);
        $this->assertTrue($service->apply($service->preview()['fingerprint'])['changed']);
        $this->assertSame($before, $ledger->fresh()->getRawOriginal());
    }

    public function test_stale_preview_or_missing_fingerprint_cannot_apply_correction(): void
    {
        $c = $this->context();
        $service = app(FiqihSubjectCorrection::class);
        $fingerprint = $service->preview()['fingerprint'];
        DB::table('diniyyah_class_journals')->where('id', $c['journal']->id)->update(['material' => 'Koreksi dari guru setelah pratinjau']);
        $before = $this->rows();
        try {
            $service->apply($fingerprint);
            $this->fail('Pratinjau basi harus ditolak');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('berbeda dari pratinjau', $exception->getMessage());
        }
        $this->artisan('diniyyah:correct-fiqih', ['--apply' => true])->assertFailed();
        $this->assertSame($before, $this->rows());
        $this->assertEmpty(Storage::disk('fiqih-test')->allFiles());
    }

    public function test_backup_failure_stops_before_any_references_are_changed(): void
    {
        $this->context();
        $backup = Mockery::mock(FiqihCorrectionBackup::class);
        $backup->shouldReceive('save')->once()->andThrow(new RuntimeException('Backup gagal'));
        $service = new FiqihSubjectCorrection($backup);
        $before = $this->rows();
        try {
            $service->apply($service->preview()['fingerprint']);
            $this->fail('Backup gagal wajib membatalkan koreksi');
        } catch (RuntimeException $exception) {
            $this->assertSame('Backup gagal', $exception->getMessage());
        }
        $this->assertSame($before, $this->rows());
    }

    public static function backupModes(): array
    {
        return ['S3 snapshot' => [false], 'downloaded Coolify dump' => [true]];
    }

    #[DataProvider('backupModes')]
    public function test_unexpected_database_side_effect_rolls_back_the_entire_correction(bool $coolify): void
    {
        $c = $this->context();
        DB::unprepared('CREATE TRIGGER fiqih_side_effect AFTER UPDATE OF subject_id ON diniyyah_class_subjects BEGIN UPDATE diniyyah_class_journals SET material = \'BERUBAH\'; END');
        $before = $this->rows();
        $service = app(FiqihSubjectCorrection::class);
        try {
            $service->apply($service->preview()['fingerprint'], $coolify ? $this->coolifyBackup() : null);
            $this->fail('Perubahan jurnal harus membatalkan seluruh koreksi');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('terlindungi berubah', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER fiqih_side_effect');
        }
        $this->assertSame($before, $this->rows());
        $this->assertSame($c['source']->id, $c['sourceClass']->fresh()->subject_id);
        $this->assertTrue($c['source']->fresh()->is_active);
        $this->assertSame(0, DB::table('activity_log')->where('event', 'fiqih_correction_snapshot')->count());
    }

    public function test_missing_or_ambiguous_master_never_guesses_a_subject_id(): void
    {
        $this->artisan('diniyyah:correct-fiqih')->assertFailed();
        $c = $this->context();
        DiniyyahSubject::create(['code' => 'fiqih-duplicate', 'name' => 'Fiqih']);
        $before = $this->rows();
        $this->artisan('diniyyah:correct-fiqih')->assertFailed();
        $this->assertSame($before, $this->rows());
    }

    private function assertBlockedWithoutWrites(): void
    {
        $service = app(FiqihSubjectCorrection::class);
        $before = $this->rows();
        $preview = $service->preview();
        $this->assertNotEmpty($preview['blockers']);
        try {
            $service->apply($preview['fingerprint']);
            $this->fail('Konflik harus menghentikan koreksi');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Ada konflik', $exception->getMessage());
        }
        $this->assertSame($before, $this->rows());
        $this->assertEmpty(Storage::disk('fiqih-test')->allFiles());
    }

    private function rows(): array
    {
        $result = [];
        foreach (['diniyyah_subjects', 'diniyyah_class_subjects', 'diniyyah_kkm_scopes', 'diniyyah_kkm_revisions',
            'diniyyah_teacher_assignments', 'diniyyah_teaching_schedules', 'diniyyah_class_journals',
            'diniyyah_class_journal_absences', 'student_attendances', 'diniyyah_assessment_sets',
            'diniyyah_scores', 'diniyyah_assessment_results', 'diniyyah_ledger_snapshots', 'report_cards'] as $table) {
            $result[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $result;
    }

    private function context(): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $school = School::create(['name' => 'Sekolah Fiqih']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil', 'is_active' => true, 'kkm_workflow_enabled' => false]);
        $source = DiniyyahSubject::create(['code' => 'fiqih_ibadah', 'name' => 'Fiqih Ibadah', 'is_active' => true]);
        $target = DiniyyahSubject::create(['code' => 'fiqih', 'name' => 'Fiqih', 'is_active' => true]);
        $subjects = $teachers = [];
        foreach ([$source, $target] as $index => $subject) {
            $gender = $index === 0 ? 'female' : 'male';
            $user = User::factory()->create();
            $user->assignRole('guru');
            $teacher = Teacher::create(['name' => 'Guru '.$gender, 'user_id' => $user->id, 'gender' => $gender, 'status' => 'active']);
            $classroom = Classroom::create(['name' => 'Mustawa 1 '.$gender, 'level_name' => 'Mustawa 1', 'gender_group' => $gender]);
            $ct = ClassroomTerm::create(['classroom_id' => $classroom->id, 'academic_term_id' => $term->id, 'name' => $classroom->name]);
            $cs = DiniyyahClassSubject::create(['classroom_term_id' => $ct->id, 'subject_id' => $subject->id, 'assessment_method' => 'direct_final', 'kkm' => 75, 'is_active' => true]);
            $assignment = DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $cs->id, 'teacher_id' => $teacher->id, 'assignment_role' => 'primary']);
            $session = ClassSession::firstOrCreate(['session_name' => '1']);
            DiniyyahTeachingSchedule::create(['diniyyah_teacher_assignment_id' => $assignment->id, 'class_session_id' => $session->id, 'day_of_week' => 1]);
            $subjects[] = $cs;
            $teachers[] = $teacher;
        }
        [$sourceClass, $targetClass] = $subjects;
        [$teacher, $otherTeacher] = $teachers;
        $student = Student::create(['nis' => 'FIQIH-001', 'name' => 'Santriwati Fiqih', 'gender' => 'female', 'status' => 'active']);
        $enrollment = ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $sourceClass->classroom_term_id, 'student_id' => $student->id, 'status' => 'active']);
        $journal = DiniyyahClassJournal::create(['diniyyah_teacher_assignment_id' => $sourceClass->teacherAssignments()->sole()->id, 'date' => '2026-10-26', 'session_hour' => '1', 'material' => 'Materi tidak boleh hilang', 'jp_count' => 1]);
        $journal->absences()->create(['class_enrollment_id' => $enrollment->id, 'status' => 'sick']);
        DB::table('student_attendances')->insert(['academic_term_id' => $term->id, 'classroom_term_id' => $sourceClass->classroom_term_id, 'class_enrollment_id' => $enrollment->id, 'student_id' => $student->id, 'attendance_date' => '2026-10-26', 'status' => 'sick']);
        $set = DiniyyahAssessmentSet::create(['diniyyah_class_subject_id' => $sourceClass->id, 'title' => 'Penilaian lama', 'assessment_method' => 'direct_final', 'kkm' => 75, 'status' => 'validated']);
        $component = DB::table('diniyyah_score_components')->insertGetId(['diniyyah_assessment_set_id' => $set->id, 'code' => 'exam', 'name' => 'Ujian', 'component_group' => 'exam']);
        DB::table('diniyyah_scores')->insert(['diniyyah_assessment_set_id' => $set->id, 'diniyyah_score_component_id' => $component, 'class_enrollment_id' => $enrollment->id, 'score' => 88, 'status' => 'validated']);
        DB::table('diniyyah_assessment_results')->insert(['diniyyah_assessment_set_id' => $set->id, 'class_enrollment_id' => $enrollment->id, 'final_score' => 88, 'kkm' => 75, 'is_complete' => true, 'is_passed' => true]);
        $scope = DiniyyahKkmScope::create(['academic_term_id' => $term->id, 'subject_id' => $source->id, 'mustawa' => 1, 'assigned_teacher_id' => $teacher->id]);
        $revision = $scope->revisions()->create(['version' => 1, 'status' => 'approved', 'final_kkm' => 75]);
        $scope->update(['active_revision_id' => $revision->id]);
        $term->update(['kkm_workflow_enabled' => true]);

        return compact('source', 'target', 'term', 'sourceClass', 'targetClass', 'teacher', 'otherTeacher', 'scope', 'journal');
    }

    private function oldSemester(array $c, DiniyyahSubject $subject): array
    {
        $year = AcademicYear::create(['school_id' => $c['term']->academicYear->school_id, 'name' => '2025/2026']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Genap', 'semester' => 'genap', 'is_active' => false, 'kkm_workflow_enabled' => false]);
        $ct = ClassroomTerm::create(['classroom_id' => $c['sourceClass']->classroomTerm->classroom_id, 'academic_term_id' => $term->id, 'name' => 'Mustawa 1 tahun lalu']);
        $classSubject = DiniyyahClassSubject::create(['classroom_term_id' => $ct->id, 'subject_id' => $subject->id, 'assessment_method' => 'direct_final', 'kkm' => 75, 'is_active' => true]);

        return compact('term', 'classSubject');
    }

    private function coolifyBackup(): array
    {
        return ['file' => 'school-dump-2026-10-30.sql.gz', 'sha256' => str_repeat('a', 64)];
    }
}
