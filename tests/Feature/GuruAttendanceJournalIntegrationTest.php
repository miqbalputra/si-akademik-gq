<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\ClassSession;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Services\GuruPerformaService;
use App\Support\SessionTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruAttendanceJournalIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00', 'Asia/Jakarta'));
        Cache::flush();
        config([
            'services.attendance_journal.enabled' => true,
            'services.attendance_journal.base_url' => 'https://geo.example.test',
            'services.attendance_journal.api_key' => str_repeat('k', 32),
            'services.attendance_journal.timeout' => 5,
            'services.attendance_journal.cache_seconds' => 60,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();

        parent::tearDown();
    }

    public function test_izin_exempts_regular_empty_slot_and_is_requested_by_niy(): void
    {
        $context = $this->makeRegularContext('GURU-001');
        Http::fake([
            'https://geo.example.test/*' => Http::response([
                'success' => true,
                'data' => [[
                    'id_guru' => 'GURU-001',
                    'tanggal' => '2026-08-04',
                    'status' => 'izin',
                    'updated_at' => '2026-08-04T07:30:00+07:00',
                ]],
            ]),
        ]);

        $performa = app(GuruPerformaService::class)->calculate($context['teacher'], 8, 2026);

        $this->assertSame(0, $performa['stats']['kosong']);
        $this->assertSame(1, $performa['stats']['dibebaskan']);
        $this->assertSame(1, $performa['stats']['dibebaskan_izin']);
        $this->assertSame([], $performa['empty_slots']);
        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->hasHeader('X-API-Key', str_repeat('k', 32))
                && ($query['teacher_ids'] ?? null) === 'GURU-001'
                && ($query['start_date'] ?? null) === '2026-08-01'
                && ($query['end_date'] ?? null) === '2026-08-10';
        });
    }

    public function test_sakit_exempts_tafsir_once_without_breaking_deduplication(): void
    {
        $context = $this->makeTafsirContext('GURU-TAFSIR');
        Http::fake([
            'https://geo.example.test/*' => Http::response([
                'success' => true,
                'data' => [[
                    'id_guru' => 'GURU-TAFSIR',
                    'tanggal' => '2026-08-06',
                    'status' => 'sakit',
                    'updated_at' => '2026-08-06T07:30:00+07:00',
                ]],
            ]),
        ]);

        $performa = app(GuruPerformaService::class)->calculate($context['teacher'], 8, 2026);

        $this->assertSame(0, $performa['stats']['kosong']);
        $this->assertSame(1, $performa['stats']['dibebaskan']);
        $this->assertSame(1, $performa['stats']['dibebaskan_sakit']);
        $this->assertSame([], $performa['empty_slots']);
    }

    public function test_non_exempt_status_and_api_failure_keep_journal_actionable(): void
    {
        $context = $this->makeRegularContext('GURU-002');
        Http::fake([
            'https://geo.example.test/*' => Http::response([
                'success' => true,
                'data' => [[
                    'id_guru' => 'GURU-002',
                    'tanggal' => '2026-08-04',
                    'status' => 'hadir_izin_terlambat',
                    'updated_at' => '2026-08-04T07:30:00+07:00',
                ]],
            ]),
        ]);

        $performa = app(GuruPerformaService::class)->calculate($context['teacher'], 8, 2026);
        $this->assertSame(1, $performa['stats']['kosong']);
        $this->assertSame(0, $performa['stats']['dibebaskan']);

        Cache::flush();
        Http::fake(['https://geo.example.test/*' => Http::response([], 503)]);
        $failed = app(GuruPerformaService::class)->calculate($context['teacher'], 8, 2026);
        $this->assertSame(1, $failed['stats']['kosong']);
        $this->assertSame(0, $failed['stats']['dibebaskan']);
    }

    public function test_teacher_without_niy_is_not_exempted(): void
    {
        $context = $this->makeRegularContext(null);
        Http::fake();

        $performa = app(GuruPerformaService::class)->calculate($context['teacher'], 8, 2026);

        $this->assertSame(1, $performa['stats']['kosong']);
        $this->assertSame(0, $performa['stats']['dibebaskan']);
        Http::assertNothingSent();
    }

    private function makeRegularContext(?string $niy): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => 'Guru Integrasi Presensi']);
        $user->assignRole('guru');
        $teacher = Teacher::create([
            'user_id' => $user->id,
            'name' => 'Guru Integrasi Presensi',
            'niy' => $niy,
        ]);

        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027', 'starts_at' => '2026-07-01']);
        $term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => 'Ganjil',
            'semester' => 'ganjil',
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-12-31',
            'is_active' => true,
        ]);
        $classroom = Classroom::create(['name' => 'Mustawa 2 Ikhwan', 'gender_group' => 'male', 'is_active' => true]);
        SessionTimetable::seedForClassroom($classroom);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => $classroom->name,
        ]);
        $subject = DiniyyahSubject::create([
            'code' => 'fiqih',
            'name' => 'Fiqih',
            'default_assessment_method' => 'weighted',
            'is_active' => true,
        ]);
        $classSubject = DiniyyahClassSubject::create([
            'classroom_term_id' => $classroomTerm->id,
            'subject_id' => $subject->id,
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
        ]);
        $assignment = DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $classSubject->id,
            'teacher_id' => $teacher->id,
            'assignment_role' => 'primary',
            'starts_at' => '2026-08-01',
        ]);
        $session = ClassSession::where('session_name', '1')->firstOrFail();
        DiniyyahTeachingSchedule::create([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'class_session_id' => $session->id,
            'day_of_week' => 2,
        ]);

        return compact('user', 'teacher', 'term', 'assignment');
    }

    private function makeTafsirContext(string $niy): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => 'Guru Tafsir Integrasi']);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => 'Guru Tafsir Integrasi', 'niy' => $niy]);
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027', 'starts_at' => '2026-07-01']);
        $term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => 'Ganjil',
            'semester' => 'ganjil',
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-12-31',
            'is_active' => true,
        ]);
        $subject = DiniyyahSubject::firstOrCreate(['code' => 'tafsir'], [
            'code' => 'tafsir',
            'name' => 'Tafsir Al Quran',
            'default_assessment_method' => 'weighted',
            'is_active' => true,
        ]);
        $session = ClassSession::where('session_name', SessionTimetable::SESSION_TAFSIR)->firstOrFail();

        foreach (['Mustawa 2 Ikhwan', 'Mustawa 3 Ikhwan'] as $className) {
            $classroom = Classroom::create(['name' => $className, 'gender_group' => 'male', 'is_active' => true]);
            SessionTimetable::seedForClassroom($classroom);
            $classroomTerm = ClassroomTerm::create([
                'academic_term_id' => $term->id,
                'classroom_id' => $classroom->id,
                'name' => $className,
            ]);
            $classSubject = DiniyyahClassSubject::create([
                'classroom_term_id' => $classroomTerm->id,
                'subject_id' => $subject->id,
                'assessment_method' => 'weighted',
                'kkm' => 70,
                'daily_weight' => 40,
                'exam_weight' => 60,
            ]);
            $assignment = DiniyyahTeacherAssignment::create([
                'diniyyah_class_subject_id' => $classSubject->id,
                'teacher_id' => $teacher->id,
                'assignment_role' => 'primary',
                'starts_at' => '2026-08-01',
            ]);
            DiniyyahTeachingSchedule::create([
                'diniyyah_teacher_assignment_id' => $assignment->id,
                'class_session_id' => $session->id,
                'day_of_week' => 4,
            ]);
        }

        return compact('user', 'teacher', 'term');
    }
}
