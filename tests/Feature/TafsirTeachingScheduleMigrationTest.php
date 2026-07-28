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
use Tests\TestCase;

/**
 * Verifikasi migration 000006_seed_tafsir_teaching_schedule membentuk tepat
 * 10 class_subject + 10 assignment + 10 teaching_schedule Tafsir (M2-M6 Ikhwan
 * ke Farhan, M2-M6 Akhwat ke Mursyidah) bila data prod-like sudah ada, dan
 * no-op bila prasyarat tidak ada. Idempoten (jalankan 2x → tetap 10).
 */
class TafsirTeachingScheduleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_seeds_tafsir_schedule_for_m2_to_m6_per_gender(): void
    {
        $this->seedProdLikeData();

        // Migration 000006 sudah no-op saat RefreshDatabase setup (belum ada
        // data). Rollback lalu jalankan ulang agar up() jalan dengan data ada.
        Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);
        Artisan::call('migrate', ['--force' => true]);

        $this->assertSame(10, DiniyyahClassSubject::where('subject_id', $this->tafsirSubjectId())->count());
        $this->assertSame(10, DiniyyahTeacherAssignment::count());
        $this->assertSame(10, DiniyyahTeachingSchedule::count());

        // Ikhwan M2-M6 → Farhan; Akhwat M2-M6 → Mursyidah. M1 tidak punya Tafsir.
        $farhan = Teacher::where('name', 'Farhan Dhia Alauddin')->first();
        $mursyidah = Teacher::where('name', 'Mursyidah')->first();

        $this->assertSame(5, DiniyyahTeacherAssignment::where('teacher_id', $farhan->id)->count());
        $this->assertSame(5, DiniyyahTeacherAssignment::where('teacher_id', $mursyidah->id)->count());

        // Semua teaching_schedule = Kamis (day_of_week 4) + session tafsir.
        $this->assertSame(
            10,
            DiniyyahTeachingSchedule::where('day_of_week', 4)->count(),
        );
    }

    public function test_migration_is_idempotent(): void
    {
        $this->seedProdLikeData();

        Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);
        Artisan::call('migrate', ['--force' => true]);
        // Jalankan up() sekali lagi via rollback + migrate.
        Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);
        Artisan::call('migrate', ['--force' => true]);

        $this->assertSame(10, DiniyyahClassSubject::where('subject_id', $this->tafsirSubjectId())->count());
        $this->assertSame(10, DiniyyahTeacherAssignment::count());
        $this->assertSame(10, DiniyyahTeachingSchedule::count());
    }

    public function test_migration_is_noop_without_prerequisites(): void
    {
        // Tidak ada academic term / guru prod-like → migration tidak membuat apa-apa.
        Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);
        Artisan::call('migrate', ['--force' => true]);

        $this->assertSame(0, DiniyyahClassSubject::count());
        $this->assertSame(0, DiniyyahTeacherAssignment::count());
        $this->assertSame(0, DiniyyahTeachingSchedule::count());
    }

    private function tafsirSubjectId(): int
    {
        return DiniyyahSubject::where('code', 'tafsir')->value('id');
    }

    /**
     * Bangun fixture prod-like: 12 classroom Mustawa 1-6 Ikhwan/Akhwat, masing
     * -satu classroom_term di term "Tahun Ajaran 2026/2027 Ganjil", plus guru
     * Farhan & Mursyidah. Matrix sesi di-seed per classroom.
     */
    private function seedProdLikeData(): void
    {
        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => 'Tahun Ajaran 2026/2027 Ganjil',
            'semester' => 'ganjil',
        ]);

        $ikhwanUser = User::factory()->create(['name' => 'Farhan Dhia Alauddin']);
        Teacher::create(['user_id' => $ikhwanUser->id, 'name' => 'Farhan Dhia Alauddin']);
        // Mursyidah tanpa akun user (sesuai kondisi prod) — assignment tetap bisa dibuat.
        Teacher::create(['name' => 'Mursyidah']);

        foreach (range(1, 6) as $level) {
            foreach (['Ikhwan', 'Akhwat'] as $gender) {
                $classroom = Classroom::create(['name' => "Mustawa {$level} {$gender}"]);
                SessionTimetable::seedForClassroom($classroom);
                ClassroomTerm::create([
                    'academic_term_id' => $term->id,
                    'classroom_id' => $classroom->id,
                    'name' => $classroom->name,
                ]);
            }
        }
    }
};