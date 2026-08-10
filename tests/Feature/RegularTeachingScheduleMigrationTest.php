<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SessionTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifikasi migration 000007_seed_regular_teaching_schedules: data-driven,
 * mencari assignment existing lalu membuat teaching_schedule per slot matriks.
 * Menguji: match per (kelas, mapel), multi-teacher (semua assignment di-link),
 * skip bila tak ada assignment, split fiqih/fiqih_ibadah per gender, idempoten.
 */
class RegularTeachingScheduleMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedProdLike();
    }

    public function test_up_creates_schedules_only_for_matched_assignments(): void
    {
        $migration = require database_path('migrations/2026_07_28_000007_seed_regular_teaching_schedules.php');
        $migration->up();

        // 5 combo ber-assignment × slot-nya masing-masing = 10 teaching_schedule.
        $this->assertSame(10, DiniyyahTeachingSchedule::count());

        // M1 Ikhwan Khat (1 guru) → Senin s1 + Jumat s1 = 2 slot.
        $this->assertSame(2, $this->scheduleCountFor('Teacher A'));
        // M4 Akhwat Khat (2 guru) → Kamis s1 saja = 1 slot × 2 guru = 2.
        $this->assertSame(1, $this->scheduleCountFor('Teacher B'));
        $this->assertSame(1, $this->scheduleCountFor('Teacher C'));
        // M2 Ikhwan Aqidah (1 guru) → Senin s1 + Selasa s1 = 2.
        $this->assertSame(2, $this->scheduleCountFor('Teacher D'));
        // M1 Akhwat fiqih (1 guru) → Senin s1 + Selasa s2 = 2.
        $this->assertSame(2, $this->scheduleCountFor('Teacher E'));
        // M1 Ikhwan fiqih_ibadah (1 guru) → Senin s2 + Selasa s2 = 2.
        $this->assertSame(2, $this->scheduleCountFor('Teacher F'));

        // Combo tanpa assignment (M6 Ikhwan Imla') → 0 jadwal.
        $this->assertSame(0, DiniyyahTeachingSchedule::query()
            ->whereRelation('teacherAssignment.teacher', 'name', 'Teacher G')
            ->count());
    }

    public function test_up_is_idempotent(): void
    {
        $migration = require database_path('migrations/2026_07_28_000007_seed_regular_teaching_schedules.php');
        $migration->up();
        $countAfterFirst = DiniyyahTeachingSchedule::count();

        $migration->up(); // jalankan ulang tanpa hapus → tidak ada duplikat.

        $this->assertSame($countAfterFirst, DiniyyahTeachingSchedule::count());
        $this->assertSame(10, DiniyyahTeachingSchedule::count());
    }

    public function test_down_deletes_regular_sessions_but_keeps_tafsir(): void
    {
        $migration = require database_path('migrations/2026_07_28_000007_seed_regular_teaching_schedules.php');
        $migration->up();

        // Tambah satu teaching_schedule sesi 'tafsir' secara manual (milik 000006).
        $tafsirSession = \App\Models\ClassSession::firstOrCreate(['session_name' => 'tafsir'], ['is_break' => false]);
        $anyAssignment = DiniyyahTeacherAssignment::first();
        DiniyyahTeachingSchedule::create([
            'diniyyah_teacher_assignment_id' => $anyAssignment->id,
            'day_of_week' => 4,
            'class_session_id' => $tafsirSession->id,
        ]);
        $this->assertSame(11, DiniyyahTeachingSchedule::count());

        $migration->down();

        // Sesi reguler (1/2) terhapus; tafsir tetap.
        $this->assertSame(1, DiniyyahTeachingSchedule::count());
        $this->assertSame(1, DiniyyahTeachingSchedule::where('class_session_id', $tafsirSession->id)->count());
    }

    public function test_migrate_command_integration_runs_without_error(): void
    {
        // Simulasikan rollback migration 000007 dengan menghapus entry-nya di
        // tabel `migrations` beserta data jadwal yang dibuatnya, lalu migrate
        // ulang via Artisan → 10 jadwal terbentuk kembali. Dilakukan eksplisit
        // per-migration (bukan `migrate:rollback --step=1`) supaya tetap robust
        // bila ada migration baru muncul setelah 000007 — `--step=1` hanya
        // merollback migration paling akhir, yang bisa bukan 000007.
        DB::table('migrations')->where('migration', '2026_07_28_000007_seed_regular_teaching_schedules')->delete();
        DiniyyahTeachingSchedule::query()->delete();
        $this->assertSame(0, DiniyyahTeachingSchedule::count());

        Artisan::call('migrate', ['--force' => true]);
        $this->assertSame(10, DiniyyahTeachingSchedule::count());
    }

    public function test_jumat_m1_tafsir_scheduled_in_regular_session_two(): void
    {
        // Jumat sesi 2 (09:20-09:50) M1 = Tafsir Al Quran (subject Tafsir di slot sesi
        // reguler '2', bukan sesi tafsir khusus Kamis). Buat assignment M1 Ikhwan
        // Tafsir (Farhan di prod) → harus terjadwalkan tepat 1x di Jumat (day 5).
        $this->assign(1, 'ikhwan', 'tafsir', ['Teacher H']);

        $migration = require database_path('migrations/2026_07_28_000007_seed_regular_teaching_schedules.php');
        $migration->up();

        $tafsirSession = \App\Models\ClassSession::where('session_name', 'tafsir')->first();
        $sessionTwo = \App\Models\ClassSession::where('session_name', '2')->first();

        $hSchedules = DiniyyahTeachingSchedule::query()
            ->whereRelation('teacherAssignment.teacher', 'name', 'Teacher H')
            ->get();
        $this->assertCount(1, $hSchedules);
        $this->assertSame(5, $hSchedules->first()->day_of_week); // Jumat
        // Pakai sesi reguler '2', BUKAN sesi 'tafsir'.
        $this->assertSame($sessionTwo->id, $hSchedules->first()->class_session_id);
        $this->assertNotEquals($tafsirSession->id, $hSchedules->first()->class_session_id);
    }

    private function scheduleCountFor(string $teacherName): int
    {
        return DiniyyahTeachingSchedule::query()
            ->whereRelation('teacherAssignment.teacher', 'name', $teacherName)
            ->count();
    }

    /**
     * Fixture prod-like: term aktif + 4 classroom_term + 6 guru dengan assignment
     * yang sengaja memetakan combo berbeda (multi-teacher, split fiqih, dan satu
     * combo tanpa assignment untuk menguji skip).
     */
    private function seedProdLike(): void
    {
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => 'Tahun Ajaran 2026/2027 Ganjil',
            'semester' => 'ganjil',
        ]);

        $this->ensureClassroomTerm(1, 'ikhwan');
        $this->ensureClassroomTerm(2, 'ikhwan');
        $this->ensureClassroomTerm(4, 'akhwat');
        $this->ensureClassroomTerm(1, 'akhwat');
        $this->ensureClassroomTerm(6, 'ikhwan'); // untuk combo tanpa assignment (Imla')

        // M1 Ikhwan Khat → 1 guru (Teacher A).
        $this->assign(1, 'ikhwan', 'khat', ['Teacher A']);
        // M4 Akhwat Khat → 2 guru (multi-teacher, Teacher B & C).
        $this->assign(4, 'akhwat', 'khat', ['Teacher B', 'Teacher C']);
        // M2 Ikhwan Aqidah Akhlaq → 1 guru (Teacher D).
        $this->assign(2, 'ikhwan', 'akidah_akhlak', ['Teacher D']);
        // M1 Akhwat Fiqih (code fiqih, matrix "Fiqih Ibadah") → 1 guru (Teacher E).
        $this->assign(1, 'akhwat', 'fiqih', ['Teacher E']);
        // M1 Ikhwan Fiqih Ibadah (code fiqih_ibadah) → 1 guru (Teacher F).
        $this->assign(1, 'ikhwan', 'fiqih_ibadah', ['Teacher F']);
        // M6 Ikhwan Imla' → TIDAK dibuat assignment-nya (uji skip). Guru G biarkan menggantung.
        Teacher::create(['name' => 'Teacher G']);
    }

    private function ensureClassroomTerm(int $level, string $gender): ClassroomTerm
    {
        $name = 'Mustawa '.$level.' '.ucfirst($gender);
        $classroom = Classroom::where('name', $name)->first();
        if (! $classroom) {
            $classroom = Classroom::create(['name' => $name]);
            SessionTimetable::seedForClassroom($classroom);
        }
        $term = AcademicTerm::where('name', 'Tahun Ajaran 2026/2027 Ganjil')->first();

        return ClassroomTerm::firstOrCreate(
            ['academic_term_id' => $term->id, 'classroom_id' => $classroom->id],
            ['name' => $name],
        );
    }

    /**
     * @param  string[]  $teacherNames
     */
    private function assign(int $level, string $gender, string $subjectCode, array $teacherNames): void
    {
        $classroomTerm = $this->ensureClassroomTerm($level, $gender);
        $subject = DiniyyahSubject::firstOrCreate(
            ['code' => $subjectCode],
            ['name' => $subjectCode, 'default_assessment_method' => 'weighted', 'is_active' => true],
        );
        $classSubject = DiniyyahClassSubject::firstOrCreate(
            ['classroom_term_id' => $classroomTerm->id, 'subject_id' => $subject->id],
            ['assessment_method' => 'weighted', 'kkm' => 70, 'daily_weight' => 40, 'exam_weight' => 60],
        );
        foreach ($teacherNames as $name) {
            $teacher = Teacher::where('name', $name)->first()
                ?? Teacher::create(['name' => $name]);
            DiniyyahTeacherAssignment::firstOrCreate(
                ['diniyyah_class_subject_id' => $classSubject->id, 'teacher_id' => $teacher->id],
                ['assignment_role' => 'primary'],
            );
        }
    }
};