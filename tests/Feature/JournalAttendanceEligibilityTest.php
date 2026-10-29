<?php

namespace Tests\Feature;

use App\Filament\Resources\DiniyyahClassJournals\Pages\CreateDiniyyahClassJournal;
use App\Filament\Resources\DiniyyahClassJournals\Pages\EditDiniyyahClassJournal;
use App\Filament\Resources\Teachers\Pages\EditTeacher;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\ClassSession;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AttendanceTeacherReportClient;
use App\Services\GuruPerformaService;
use App\Services\JournalAttendanceEligibilityService;
use App\Services\RekapJurnalGuruService;
use App\Support\SessionTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JournalAttendanceEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private ?string $sourceStatus = 'hadir';

    private int $sourceHttpStatus = 200;

    private ?string $payloadMode = null;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-30 14:00', 'Asia/Jakarta'));
        foreach (['guru', 'admin', 'kabag_diniyyah', 'kepala_sekolah'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        config([
            'services.attendance_journal.enabled' => true,
            'services.attendance_journal.base_url' => 'https://geo.example.test',
            'services.attendance_journal.api_key' => 'test-key',
        ]);
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => Http::response($this->payload($request), $this->sourceHttpStatus));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public static function statuses(): array
    {
        return [
            'hadir' => ['hadir', true, 'Hadir'],
            'terlambat' => ['hadir_terlambat', true, 'Hadir terlambat'],
            'izin terlambat' => ['hadir_izin_terlambat', true, 'Hadir - izin terlambat'],
            'belum presensi' => [null, false, 'Belum presensi'],
            'sakit' => ['sakit', false, 'Sakit'],
            'izin' => ['izin', false, 'Izin'],
            'alfa' => ['alfa', false, 'Alfa'],
            'tidak dikenal' => ['status_baru', false, 'Belum dapat diverifikasi'],
        ];
    }

    #[DataProvider('statuses')]
    public function test_status_on_the_journal_date_decides_eligibility(?string $status, bool $allowed, string $label): void
    {
        $ctx = $this->context();
        $this->sourceStatus = $status;
        $decision = app(JournalAttendanceEligibilityService::class)->check($ctx['teacher'], $ctx['date']);

        $this->assertSame($allowed, $decision['allowed']);
        $this->assertSame($label, $decision['status_label']);
        $this->assertFalse($decision['exempt']);
        if (in_array($label, ['Belum presensi', 'Sakit', 'Izin', 'Alfa'], true)) {
            $this->assertSame('Mohon maaf Anda tidak hadir pada jam ini dengan status '.$label.'. Silahkan cek aplikasi GeoPresensi', $decision['message']);
        }
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/teacher-report?')
            && $request['id_guru'] === $ctx['teacher']->niy
            && $request['start_date'] === $ctx['date'] && $request['end_date'] === $ctx['date']);
    }

    public static function modes(): array
    {
        return ['reguler' => [false, false], 'pengganti' => [true, false], 'tafsir' => [false, true], 'pengganti tafsir' => [true, true]];
    }

    #[DataProvider('modes')]
    public function test_each_teacher_write_path_rejects_absence_and_preserves_input_then_accepts_presence(bool $substitute, bool $tafsir): void
    {
        $ctx = $this->context($substitute, $tafsir);
        $this->sourceStatus = 'sakit';
        $this->actingAs($ctx['user'])->post(route($ctx['storeRoute']), $ctx['input'])
            ->assertRedirect()->assertSessionHas('error', fn ($message) => str_contains($message, 'status Sakit'))
            ->assertSessionHasInput('material', 'Materi baru');
        $this->assertDatabaseCount('diniyyah_class_journals', 0);
        $this->assertDatabaseCount('diniyyah_class_journal_absences', 0);
        $this->get(route($ctx['indexRoute'], ['date' => $ctx['date'], 'classroom_term_id' => $ctx['classroomTerm']->id]))
            ->assertOk()->assertSee('status Sakit')->assertSee('Materi baru')->assertSee('Cek ulang presensi');

        $this->sourceStatus = 'hadir_terlambat';
        $this->post(route($ctx['storeRoute']), $ctx['input'])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseCount('diniyyah_class_journals', $tafsir ? 2 : 1);
        foreach (DiniyyahClassJournal::all() as $journal) {
            $this->assertSame($ctx['teacher']->id, $journal->effectiveTeacher()->id);
        }
        Http::assertSent(fn (Request $request) => ($request['id_guru'] ?? null) === $ctx['teacher']->niy);
    }

    #[DataProvider('modes')]
    public function test_exempt_teacher_can_write_without_niy_or_geopresensi_and_jp_is_counted(bool $substitute, bool $tafsir): void
    {
        $ctx = $this->context($substitute, $tafsir);
        $ctx['teacher']->update(['journal_attendance_exempt' => true, 'niy' => null]);
        config(['services.attendance_journal.enabled' => false]);
        $this->actingAs($ctx['user'])->post(route($ctx['storeRoute']), $ctx['input'])
            ->assertRedirect()->assertSessionHas('success');

        $recap = app(RekapJurnalGuruService::class)->build($ctx['term']->id, $ctx['date'], $ctx['date']);
        $this->assertSame(1, $recap['stats']['total_jp']);
        $performa = app(GuruPerformaService::class)->calculate($ctx['teacher']->fresh(), 10, 2026);
        $this->assertSame(1, $performa['stats']['jp_berhasil_terlaksana']);
        $this->assertTrue($performa['reconciliation']['exempt']);
        $this->assertEmpty($performa['reconciliation']['rows']);
        Http::assertNothingSent();
    }

    public function test_edit_denial_keeps_all_journal_and_student_attendance_data_unchanged(): void
    {
        $ctx = $this->context();
        $journal = $this->journal($ctx);
        $journal->absences()->create(['class_enrollment_id' => $ctx['enrollment']->id, 'status' => 'sick']);
        $before = $this->snapshot();
        $this->sourceStatus = 'alfa';
        $this->actingAs($ctx['user'])->put(route('guru.diniyyah-journals.update', $journal), [
            'material' => 'Materi koreksi', 'absences' => [$ctx['enrollment']->id => 'skipped'],
        ])->assertRedirect(route('guru.diniyyah-journals.edit', $journal))->assertSessionHasInput('material', 'Materi koreksi');
        $this->assertEquals($before, $this->snapshot());
        $this->assertSame(1, app(RekapJurnalGuruService::class)->build($ctx['term']->id, $ctx['date'], $ctx['date'])['stats']['total_jp']);
        $this->get(route('guru.diniyyah-journals.edit', $journal))
            ->assertOk()->assertSee('Materi koreksi')->assertSee('status Alfa')->assertSee('Cek ulang presensi');
    }

    public function test_presence_is_refetched_after_opening_the_form_and_cache_cannot_authorize_a_write(): void
    {
        $ctx = $this->context();
        $this->actingAs($ctx['user'])->get(route($ctx['indexRoute'], [
            'date' => $ctx['date'], 'classroom_term_id' => $ctx['classroomTerm']->id,
        ]))->assertOk()->assertSee('Status GeoPresensi: Hadir');
        // Seed the ordinary report cache with Hadir, then change the source.
        app(AttendanceTeacherReportClient::class)->reportForTeacher($ctx['teacher'], Carbon::parse($ctx['date']), Carbon::parse($ctx['date']));
        $this->sourceStatus = 'izin';
        $this->post(route($ctx['storeRoute']), $ctx['input'])->assertSessionHas('error', fn ($message) => str_contains($message, 'status Izin'));
        $this->assertDatabaseCount('diniyyah_class_journals', 0);
    }

    public function test_read_only_recheck_uses_latest_status_without_saving_a_journal(): void
    {
        $ctx = $this->context();
        $this->sourceStatus = null;
        $url = route($ctx['indexRoute'], ['date' => $ctx['date'], 'attendance_check' => 1]);
        $this->actingAs($ctx['user'])->getJson($url)->assertOk()->assertJsonPath('allowed', false)->assertJsonPath('status_label', 'Belum presensi');
        $this->sourceStatus = 'hadir';
        $this->getJson($url)->assertOk()->assertJsonPath('allowed', true);
        $this->assertDatabaseCount('diniyyah_class_journals', 0);
    }

    public function test_teacher_cannot_claim_an_exception_in_the_request_and_exempt_edits_do_not_need_geopresensi(): void
    {
        $ctx = $this->context();
        $this->sourceStatus = 'alfa';
        $this->actingAs($ctx['user'])->post(route($ctx['storeRoute']), $ctx['input'] + ['journal_attendance_exempt' => true])
            ->assertSessionHas('error');
        $this->assertFalse($ctx['teacher']->fresh()->journal_attendance_exempt);
        $journal = $this->journal($ctx);
        $ctx['teacher']->update(['journal_attendance_exempt' => true, 'niy' => null]);
        config(['services.attendance_journal.enabled' => false]);
        $this->put(route('guru.diniyyah-journals.update', $journal), ['material' => 'Edit guru khusus'])
            ->assertSessionHas('success');
        $this->assertSame('Edit guru khusus', $journal->fresh()->material);
    }

    public function test_revoking_an_exception_cannot_be_bypassed_with_a_previously_loaded_teacher(): void
    {
        $ctx = $this->context();
        $ctx['teacher']->update(['journal_attendance_exempt' => true]);
        $staleTeacher = $ctx['teacher']->fresh();
        $this->assertTrue(app(JournalAttendanceEligibilityService::class)->check($staleTeacher, $ctx['date'])['allowed']);
        Http::assertNothingSent();
        Teacher::findOrFail($staleTeacher->id)->update(['journal_attendance_exempt' => false]);
        $this->sourceStatus = 'alfa';
        $this->assertFalse(app(JournalAttendanceEligibilityService::class)->check($staleTeacher, $ctx['date'])['allowed']);
    }

    public function test_unavailable_service_and_invalid_identity_deny_without_fabricating_absence(): void
    {
        $ctx = $this->context();
        $this->sourceHttpStatus = 503;
        $decision = app(JournalAttendanceEligibilityService::class)->check($ctx['teacher'], $ctx['date']);
        $this->assertFalse($decision['allowed']);
        $this->assertSame('Belum dapat diverifikasi', $decision['status_label']);
        $this->assertStringNotContainsString('status Alfa', $decision['message']);
        $this->actingAs($ctx['user'])->post(route($ctx['storeRoute']), $ctx['input'])->assertSessionHas('error');
        $this->assertDatabaseCount('diniyyah_class_journals', 0);

        $ctx['teacher']->update(['niy' => null]);
        $this->assertFalse(app(JournalAttendanceEligibilityService::class)->check($ctx['teacher'], $ctx['date'])['allowed']);
        $ctx['teacher']->update(['niy' => 'DUPLIKAT']);
        Teacher::create(['name' => 'NIY sama', 'niy' => ' DUPLIKAT ']);
        $this->assertStringContainsString('lebih dari satu guru', app(JournalAttendanceEligibilityService::class)->check($ctx['teacher'], $ctx['date'])['message']);
    }

    public static function invalidPayloads(): array
    {
        return ['identity' => ['identity'], 'malformed' => ['malformed'], 'duplicate' => ['duplicate'], 'missing status' => ['missing_status']];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_reports_cannot_authorize_a_write(string $mode): void
    {
        $ctx = $this->context();
        $this->payloadMode = $mode;
        $decision = app(JournalAttendanceEligibilityService::class)->check($ctx['teacher'], $ctx['date']);
        $this->assertFalse($decision['allowed']);
        $this->assertSame('Belum dapat diverifikasi', $decision['status_label']);
    }

    public function test_today_presence_does_not_authorize_a_different_journal_date(): void
    {
        $ctx = $this->context();
        $this->payloadMode = 'wrong_date';
        $this->actingAs($ctx['user'])->post(route($ctx['storeRoute']), $ctx['input'])
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'Belum presensi'));
        $this->assertDatabaseCount('diniyyah_class_journals', 0);
    }

    public function test_admin_create_and_edit_use_the_prospective_teacher_and_date_and_preserve_denied_data(): void
    {
        $ctx = $this->context(true);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $data = $ctx['input'] + ['jp_count' => 1, 'substitute_teacher_id' => $ctx['teacher']->id];
        $this->sourceStatus = 'sakit';
        $page = Livewire::actingAs($admin, 'admin')->test(CreateDiniyyahClassJournal::class)->fillForm($data)
            ->call('create')->assertHasFormErrors(['date']);
        $this->assertDatabaseCount('diniyyah_class_journals', 0);
        $this->sourceStatus = 'hadir';
        $page->call('checkJournalAttendance')->call('create')->assertHasNoFormErrors();
        $journal = DiniyyahClassJournal::sole();
        $this->assertSame($ctx['teacher']->id, $journal->effectiveTeacher()->id);

        $before = $this->snapshot();
        $page = Livewire::actingAs($admin, 'admin')->test(EditDiniyyahClassJournal::class, ['record' => $journal->id]);
        $this->sourceStatus = 'alfa';
        $page->fillForm(['substitute_teacher_id' => null, 'date' => '2026-10-27', 'material' => 'Koreksi admin'])
            ->call('save')->assertHasFormErrors(['date']);
        $this->assertEquals($before, $this->snapshot());
        Http::assertSent(fn (Request $request) => ($request['id_guru'] ?? null) === $ctx['originalTeacher']->niy && $request['start_date'] === '2026-10-27');
    }

    public function test_kabag_cannot_bypass_the_attendance_gate(): void
    {
        $ctx = $this->context();
        $kabag = User::factory()->create();
        $kabag->assignRole('kabag_diniyyah');
        $this->sourceStatus = 'alfa';
        Livewire::actingAs($kabag, 'admin')->test(CreateDiniyyahClassJournal::class)
            ->fillForm($ctx['input'] + ['jp_count' => 1])->call('create')->assertHasFormErrors(['date']);
        $this->assertDatabaseCount('diniyyah_class_journals', 0);
    }

    public function test_only_admin_can_manage_the_exception_and_it_takes_effect_without_an_api(): void
    {
        $ctx = $this->context();
        $this->assertFalse($ctx['teacher']->fresh()->journal_attendance_exempt);
        foreach (['guru', 'kabag_diniyyah', 'kepala_sekolah'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user);
            $this->assertFalse(TeacherResource::canEdit($ctx['teacher']));
            Livewire::actingAs($user, 'admin')->test(EditTeacher::class, ['record' => $ctx['teacher']->id])->assertForbidden();
        }
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Livewire::actingAs($admin, 'admin')->test(EditTeacher::class, ['record' => $ctx['teacher']->id])
            ->fillForm(['journal_attendance_exempt' => true, 'niy' => null])->call('save')->assertHasNoFormErrors();
        config(['services.attendance_journal.enabled' => false]);
        $this->assertTrue(app(JournalAttendanceEligibilityService::class)->check($ctx['teacher']->fresh(), $ctx['date'])['allowed']);
        Http::assertNothingSent();
    }

    public function test_additive_migration_preserves_active_archived_and_student_attendance_records(): void
    {
        $ctx = $this->context();
        $journal = $this->journal($ctx);
        $journal->absences()->create(['class_enrollment_id' => $ctx['enrollment']->id, 'status' => 'sick']);
        $journal->delete();
        $this->journal($ctx);
        $migration = require database_path('migrations/2026_10_29_000002_add_journal_attendance_exempt_to_teachers.php');
        $before = $this->snapshot();
        $teachersBefore = DB::table('teachers')->orderBy('id')->get()->map(fn ($row) => collect($row)->except('journal_attendance_exempt')->all())->all();
        $migration->down();
        $migration->up();
        $this->assertEquals($before, $this->snapshot());
        $this->assertEquals($teachersBefore, DB::table('teachers')->orderBy('id')->get()->map(fn ($row) => collect($row)->except('journal_attendance_exempt')->all())->all());
        $this->assertFalse($ctx['teacher']->fresh()->journal_attendance_exempt);
        $this->assertSame(1, app(RekapJurnalGuruService::class)->build($ctx['term']->id, $ctx['date'], $ctx['date'])['stats']['total_jp']);
    }

    private function payload(Request $request): array
    {
        if (! str_contains($request->url(), '/teacher-report')) {
            return ['success' => true, 'data' => []];
        }
        $payload = ['success' => true, 'data' => [
            'teacher' => ['id_guru' => $request['id_guru']],
            'summary' => [],
            'rows' => $this->sourceStatus === null ? [] : [[
                'tanggal' => $request['start_date'], 'status' => $this->sourceStatus,
                'jam_masuk' => '11:00:00', 'jam_pulang' => '',
            ]],
        ]];
        if ($this->payloadMode === 'identity') {
            $payload['data']['teacher']['id_guru'] = 'GURU-LAIN';
        } elseif ($this->payloadMode === 'malformed') {
            $payload['data']['rows'] = ['rusak'];
        } elseif ($this->payloadMode === 'duplicate') {
            $payload['data']['rows'][] = $payload['data']['rows'][0];
        } elseif ($this->payloadMode === 'missing_status') {
            unset($payload['data']['rows'][0]['status']);
        } elseif ($this->payloadMode === 'wrong_date') {
            $payload['data']['rows'][0]['tanggal'] = '2026-10-30';
        }

        return $payload;
    }

    private function snapshot(): array
    {
        return [
            DB::table('diniyyah_class_journals')->orderBy('id')->get()->all(),
            DB::table('diniyyah_class_journal_absences')->orderBy('id')->get()->all(),
        ];
    }

    private function journal(array $ctx): DiniyyahClassJournal
    {
        return DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
            'date' => $ctx['date'], 'session_hour' => $ctx['session'], 'material' => 'Materi asli', 'jp_count' => 1,
        ]);
    }

    private function context(bool $substitute = false, bool $tafsir = false): array
    {
        $user = User::factory()->create();
        $user->assignRole('guru');
        $originalTeacher = Teacher::create(['user_id' => $user->id, 'email' => $user->email, 'name' => 'Guru Asli', 'gender' => 'female', 'status' => 'active', 'niy' => 'ASLI']);
        $teacher = $originalTeacher;
        if ($substitute) {
            $user = User::factory()->create();
            $user->assignRole('guru');
            $teacher = Teacher::create(['user_id' => $user->id, 'email' => $user->email, 'name' => 'Guru Pengganti', 'gender' => 'female', 'status' => 'active', 'niy' => 'PENGGANTI']);
        }
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil', 'is_active' => true]);
        $subject = DiniyyahSubject::firstOrCreate(['code' => $tafsir ? 'tafsir' : 'fiqih'], ['name' => $tafsir ? 'Tafsir' : 'Fiqih']);
        $date = $tafsir ? '2026-10-29' : '2026-10-26';
        $session = $tafsir ? 'tafsir' : '2';
        $assignments = [];
        foreach ($tafsir ? [5, 6] : [5] as $level) {
            $classroom = Classroom::create(['name' => "Mustawa {$level} Akhwat", 'gender_group' => 'female']);
            SessionTimetable::seedForClassroom($classroom);
            $classroomTerm = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => $classroom->name]);
            $classSubject = DiniyyahClassSubject::create(['classroom_term_id' => $classroomTerm->id, 'subject_id' => $subject->id, 'assessment_method' => 'weighted', 'kkm' => 70]);
            $assignment = DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $classSubject->id, 'teacher_id' => $originalTeacher->id, 'assignment_role' => 'primary']);
            DiniyyahTeachingSchedule::create(['diniyyah_teacher_assignment_id' => $assignment->id, 'class_session_id' => ClassSession::where('session_name', $session)->sole()->id, 'day_of_week' => $tafsir ? 4 : 1]);
            $assignments[] = $assignment;
        }
        $assignment = $assignments[0];
        $classroomTerm = $assignment->classSubject->classroomTerm;
        $student = Student::create(['name' => 'Santriwati', 'nis' => 'ATTENDANCE-001', 'gender' => 'female', 'status' => 'active']);
        $enrollment = ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $classroomTerm->id, 'student_id' => $student->id, 'status' => 'active']);
        $prefix = 'guru.diniyyah-'.($substitute ? 'substitute-' : '').($tafsir ? 'tafsir-' : '').'journals.';
        $storeRoute = $prefix.'store';
        $indexRoute = $prefix.'index';
        $input = ['date' => $date, 'material' => 'Materi baru'];
        $input += $tafsir ? ['assignments' => array_map(fn ($item) => $item->id, $assignments)] : [
            'diniyyah_teacher_assignment_id' => $assignment->id, 'classroom_term_id' => $classroomTerm->id,
            'session_hour' => $session, 'absences' => [$enrollment->id => 'skipped'],
        ];

        return compact('user', 'teacher', 'originalTeacher', 'term', 'assignment', 'assignments', 'classroomTerm', 'enrollment', 'date', 'session', 'storeRoute', 'indexRoute', 'input');
    }
}
