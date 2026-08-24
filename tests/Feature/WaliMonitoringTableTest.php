<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassSession;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\HomeroomAssignment;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SessionTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WaliMonitoringTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitoring_table_shows_summary_and_can_filter_only_empty_slots(): void
    {
        $context = $this->makeContext();
        $journal = DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $context['assignment']->id,
            'date' => '2025-03-03',
            'session_hour' => '1',
            'session_starts_at' => '10:30:00',
            'session_ends_at' => '11:00:00',
            'material' => 'Materi terisi untuk pengujian tabel',
            'jp_count' => 1,
        ]);

        $this->actingAs($context['waliUser'])
            ->get(route('wali.diniyyah-journals.index', ['month' => 3, 'year' => 2025]))
            ->assertOk()
            ->assertSee('Total slot')
            ->assertSee('Sudah terisi')
            ->assertSee('Jurnal kosong')
            ->assertSee('slot ditampilkan')
            ->assertSee('Materi terisi untuk pengujian tabel')
            ->assertSee('KOSONG')
            ->assertSee('Belum ada data jurnal untuk slot ini.')
            ->assertSee('Hanya jurnal kosong');

        $this->actingAs($context['waliUser'])
            ->get(route('wali.diniyyah-journals.index', [
                'month' => 3,
                'year' => 2025,
                'status' => 'KOSONG',
            ]))
            ->assertOk()
            ->assertSee('slot ditampilkan')
            ->assertSee('Mode: hanya jurnal kosong')
            ->assertSee('Belum ada data jurnal untuk slot ini.')
            ->assertDontSee('Materi terisi untuk pengujian tabel');

        $this->actingAs($context['waliUser'])
            ->get(route('wali.diniyyah-journals.export-pdf', [
                'month' => 3,
                'year' => 2025,
                'status' => 'KOSONG',
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($context['waliUser'])
            ->get(route('wali.diniyyah-journals.export-excel', [
                'month' => 3,
                'year' => 2025,
                'status' => 'KOSONG',
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($context['waliUser'])
            ->get(route('wali.diniyyah-journals.index', [
                'month' => 3,
                'year' => 2025,
                'status' => 'TERISI',
            ]))
            ->assertOk()
            ->assertSee('slot ditampilkan')
            ->assertSee('Materi terisi untuk pengujian tabel')
            ->assertDontSee('Belum ada data jurnal untuk slot ini.');

        $this->assertTrue($journal->exists);
    }

    public function test_substitute_journal_fills_original_slot_and_is_not_marked_empty(): void
    {
        $context = $this->makeContext();
        $substituteUser = User::factory()->create(['name' => 'Guru Pengganti']);
        $substituteTeacher = Teacher::create(['user_id' => $substituteUser->id, 'name' => 'Guru Pengganti']);

        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $context['assignment']->id,
            'substitute_teacher_id' => $substituteTeacher->id,
            'date' => '2025-03-03',
            'session_hour' => '1',
            'session_starts_at' => '10:30:00',
            'session_ends_at' => '11:00:00',
            'material' => 'Materi diisi guru pengganti',
            'jp_count' => 1,
        ]);

        $this->actingAs($context['waliUser'])
            ->get(route('wali.diniyyah-journals.index', ['month' => 3, 'year' => 2025]))
            ->assertOk()
            ->assertSee('Diisi pengganti: Guru Pengganti')
            ->assertSee('Materi diisi guru pengganti')
            ->assertSee('jurnal belum diisi')
            ->assertSee('KOSONG');
    }

    public function test_monitoring_marks_teacher_absence_as_excused_instead_of_empty(): void
    {
        config([
            'services.attendance_journal.enabled' => true,
            'services.attendance_journal.base_url' => 'https://geo.example.test',
            'services.attendance_journal.api_key' => str_repeat('k', 32),
        ]);
        Cache::flush();
        $context = $this->makeContext();
        $teacher = $context['assignment']->teacher()->firstOrFail();
        $teacher->update(['niy' => 'WALI-001']);
        Http::fake([
            'https://geo.example.test/*' => Http::response([
                'success' => true,
                'data' => [[
                    'id_guru' => 'WALI-001',
                    'tanggal' => '2025-03-03',
                    'status' => 'izin',
                    'updated_at' => '2025-03-03T07:30:00+07:00',
                ]],
            ]),
        ]);

        $this->actingAs($context['waliUser'])
            ->get(route('wali.diniyyah-journals.index', ['month' => 3, 'year' => 2025]))
            ->assertOk()
            ->assertSee('IZIN · Dibebaskan')
            ->assertSee('Dibebaskan oleh presensi: izin.')
            ->assertSee('Dibebaskan')
            // The month still contains other scheduled Mondays; only the
            // slots on the presensi-excused date must change status.
            ->assertSee('Belum ada data jurnal untuk slot ini.')
            ->assertSee('jurnal belum diisi');
    }

    public function test_monitoring_shows_and_filters_attendance_journal_mismatches_for_its_class(): void
    {
        config([
            'services.attendance_journal.enabled' => true,
            'services.attendance_journal.base_url' => 'https://geo.example.test',
            'services.attendance_journal.api_key' => str_repeat('k', 32),
            'services.attendance_journal.cache_seconds' => 60,
        ]);
        Cache::flush();
        $context = $this->makeContext();
        $teacher = $context['assignment']->teacher()->firstOrFail();
        $teacher->update(['niy' => 'WALI-REKON-001']);
        Http::fake([
            'https://geo.example.test/api/v1/integrations/journal/teachers*' => Http::response([
                'success' => true,
                'data' => [['id_guru' => 'WALI-REKON-001']],
            ]),
            'https://geo.example.test/api/v1/integrations/journal/attendance*' => Http::response([
                'success' => true,
                'data' => [[
                    'id_guru' => 'WALI-REKON-001',
                    'tanggal' => '2025-03-03',
                    'status' => 'hadir',
                ]],
            ]),
        ]);

        $this->actingAs($context['waliUser'])
            ->get(route('wali.diniyyah-journals.index', ['month' => 3, 'year' => 2025]))
            ->assertOk()
            ->assertSee('Hadir tanpa jurnal')
            ->assertSee('Presensi & jurnal perlu dicek')
            ->assertSee('Hadir, jurnal belum diisi');

        $this->actingAs($context['waliUser'])
            ->get(route('wali.diniyyah-journals.index', [
                'month' => 3,
                'year' => 2025,
                'status' => 'REKONSILIASI',
            ]))
            ->assertOk()
            ->assertSee('Mode: presensi & jurnal perlu dicek')
            ->assertSee('Hadir, jurnal belum diisi');
    }

    private function makeContext(): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $waliUser = User::factory()->create(['name' => 'Wali Kelas']);
        $waliUser->assignRole('guru');
        $waliTeacher = Teacher::create(['user_id' => $waliUser->id, 'name' => 'Wali Kelas']);

        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2024/2025']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Genap', 'semester' => 'genap']);
        $classroom = Classroom::create(['name' => 'Mustawa 5 Ikhwan', 'gender_group' => 'male', 'is_active' => true]);
        SessionTimetable::seedForClassroom($classroom);
        $classroomTerm = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => $classroom->name]);
        HomeroomAssignment::create(['classroom_term_id' => $classroomTerm->id, 'teacher_id' => $waliTeacher->id]);

        $subject = DiniyyahSubject::create(['code' => 'fiqih', 'name' => 'Fiqih', 'default_assessment_method' => 'weighted']);
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
            'teacher_id' => $waliTeacher->id,
            'assignment_role' => 'primary',
        ]);
        $sessionOne = ClassSession::where('session_name', '1')->first();
        $sessionTwo = ClassSession::where('session_name', '2')->first();
        DiniyyahTeachingSchedule::create(['diniyyah_teacher_assignment_id' => $assignment->id, 'day_of_week' => 1, 'class_session_id' => $sessionOne->id]);
        DiniyyahTeachingSchedule::create(['diniyyah_teacher_assignment_id' => $assignment->id, 'day_of_week' => 1, 'class_session_id' => $sessionTwo->id]);

        return compact('waliUser', 'assignment');
    }
}
