<?php

namespace Tests\Feature;

use App\Filament\Resources\DiniyyahClassJournals\DiniyyahClassJournalResource;
use App\Filament\Resources\DiniyyahClassJournals\Pages\CreateDiniyyahClassJournal;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SessionTimetable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Form admin (Filament) untuk mencatat jurnal terlewat di jadwal lama. Sengaja
 * TIDAK memeriksa kecocokan jadwal — admin membypass gate jadwal portal guru.
 * Diuji: backfill tanggal di luar jadwal saat ini berhasil + snapshot jam sesi
 * terisi dari matrix; guard duplikat menolak; peran (admin/kepala/guru) tepat;
 * guru pengganti mengkredit JP ke effectiveTeacher.
 */
class DiniyyahClassJournalAdminCreateTest extends TestCase
{
    use RefreshDatabase;

    /** Selasa 2026-08-04 — di luar jadwal saat ini (Senin). */
    private const SELASA_LAMA = '2026-08-04';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_create_backfill_journal_for_date_outside_current_schedule(): void
    {
        $admin = $this->makeAdmin();
        $guru = $this->makeGuru('Ustadz Ahmad');
        [$assignment] = $this->makeAssignmentWithSchedule($guru['teacher'], 'Fiqih', 1, '1'); // Senin sesi 1

        // Backfill tanggal Selasa (di luar jadwal Senin) — tanpa gate, harus berhasil.
        \Livewire\Livewire::actingAs($admin)
            ->test(CreateDiniyyahClassJournal::class)
            ->fillForm([
                'diniyyah_teacher_assignment_id' => $assignment->id,
                'date' => self::SELASA_LAMA,
                'session_hour' => '1',
                'material' => 'Bab Thaharah (backfill jadwal lama)',
                'jp_count' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Snapshot jam sesi terisi dari matrix Mustawa 2 Ikhwan Selasa sesi 1.
        // (tanggal diverifikasi terpisah via cast model — kolom date di-cast
        // 'date' lalu disimpan 'Y-m-d H:i:s', jangan pasangkan format raw di sini.)
        $this->assertDatabaseHas('diniyyah_class_journals', [
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'session_hour' => '1',
            'session_starts_at' => '10:30:00',
            'session_ends_at' => '11:00:00',
            'material' => 'Bab Thaharah (backfill jadwal lama)',
            'jp_count' => 1,
        ]);

        $journal = DiniyyahClassJournal::where('diniyyah_teacher_assignment_id', $assignment->id)->firstOrFail();
        $this->assertSame(self::SELASA_LAMA, $journal->date->format('Y-m-d'), 'tanggal tersimpan sesuai input.');
        $this->assertSame($guru['teacher']->id, $journal->effectiveTeacher()?->id, 'effectiveTeacher = guru pemilik (tanpa pengganti).');
    }

    public function test_duplicate_backfill_is_rejected_with_friendly_message(): void
    {
        $admin = $this->makeAdmin();
        $guru = $this->makeGuru('Ustadz Ahmad');
        [$assignment] = $this->makeAssignmentWithSchedule($guru['teacher'], 'Fiqih', 1, '1');

        // Catat jurnal terlewat sekali (berhasil).
        \Livewire\Livewire::actingAs($admin)
            ->test(CreateDiniyyahClassJournal::class)
            ->fillForm([
                'diniyyah_teacher_assignment_id' => $assignment->id,
                'date' => self::SELASA_LAMA,
                'session_hour' => '1',
                'material' => 'Bab Thaharah',
                'jp_count' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Submit kedua dengan (assignment, date, session) yang sama → ditolak.
        \Livewire\Livewire::actingAs($admin)
            ->test(CreateDiniyyahClassJournal::class)
            ->fillForm([
                'diniyyah_teacher_assignment_id' => $assignment->id,
                'date' => self::SELASA_LAMA,
                'session_hour' => '1',
                'material' => 'Pengulangan (harus ditolak)',
                'jp_count' => 1,
            ])
            ->call('create')
            ->assertHasFormErrors(['session_hour']);

        // Hanya satu jurnal yang tersimpan.
        $this->assertSame(1, DiniyyahClassJournal::where('diniyyah_teacher_assignment_id', $assignment->id)->count());
    }

    public function test_substitute_teacher_credits_jp_to_effective_teacher(): void
    {
        $admin = $this->makeAdmin();
        $guruPemilik = $this->makeGuru('Ustadz Ahmad');
        $guruPengganti = $this->makeGuru('Ustadz Budi');
        [$assignment] = $this->makeAssignmentWithSchedule($guruPemilik['teacher'], 'Fiqih', 1, '1');

        // Kasus tukar guru: admin isi substitute = guru lama yang mengajar tanggal itu.
        \Livewire\Livewire::actingAs($admin)
            ->test(CreateDiniyyahClassJournal::class)
            ->fillForm([
                'diniyyah_teacher_assignment_id' => $assignment->id,
                'substitute_teacher_id' => $guruPengganti['teacher']->id,
                'date' => self::SELASA_LAMA,
                'session_hour' => '1',
                'material' => 'Digantikan Budi (jadwal lama)',
                'jp_count' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $journal = DiniyyahClassJournal::where('diniyyah_teacher_assignment_id', $assignment->id)->firstOrFail();
        $this->assertSame($guruPengganti['teacher']->id, $journal->effectiveTeacher()?->id, 'JP/gaji mengkredit guru pengganti.');
    }

    public function test_resolve_session_times_normalizes_carbon_date(): void
    {
        $guru = $this->makeGuru('Ustadz Ahmad');
        [$assignment, $classroomTerm] = $this->makeAssignmentWithSchedule($guru['teacher'], 'Fiqih', 1, '1');

        // DatePicker/cast bisa mengembalikan Carbon — resolve harus tetap benar.
        $data = DiniyyahClassJournalResource::resolveSessionTimes([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'date' => Carbon::parse(self::SELASA_LAMA),
            'session_hour' => '1',
        ]);

        $this->assertSame('10:30:00', $data['session_starts_at']);
        $this->assertSame('11:00:00', $data['session_ends_at']);
    }

    public function test_role_gating_admin_kepala_guru(): void
    {
        // admin: view + manage
        $this->actingAs($this->makeAdmin());
        $this->assertTrue(DiniyyahClassJournalResource::canAccess());
        $this->assertTrue(DiniyyahClassJournalResource::canCreate());

        // kepala_sekolah: view saja (read-only)
        $kepala = $this->userWithRole('kepala_sekolah');
        $this->actingAs($kepala);
        $this->assertTrue(DiniyyahClassJournalResource::canAccess(), 'kepala_sekolah bisa lihat');
        $this->assertFalse(DiniyyahClassJournalResource::canCreate(), 'kepala_sekolah read-only');

        // guru: dilarang akses panel Filament
        $guru = $this->userWithRole('guru');
        $this->actingAs($guru);
        $this->assertFalse(DiniyyahClassJournalResource::canAccess(), 'guru tidak boleh akses panel');
    }

    // ----- Helpers -----

    private function makeAdmin(): User
    {
        return $this->userWithRole('admin');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** @return array{0: User, 1: Teacher} */
    private function makeGuru(string $name): array
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => $name]);

        return ['user' => $user, 'teacher' => $teacher];
    }

    /**
     * Buat assignment reguler + jadwal (day_of_week, session) tanpa actingAs
     * (observer created skip tanpa Auth → tidak terlog). Classroom "Mustawa 2
     * Ikhwan" dengan matrix di-seed. Mengembalikan [assignment, classroomTerm].
     *
     * @return array{0: DiniyyahTeacherAssignment, 1: ClassroomTerm}
     */
    private function makeAssignmentWithSchedule(Teacher $teacher, string $subjectName, int $dayOfWeek, string $sessionName): array
    {
        $classroom = Classroom::create(['name' => 'Mustawa 2 Ikhwan']);
        SessionTimetable::seedForClassroom($classroom);
        $termId = $this->academicTermId();
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $termId,
            'classroom_id' => $classroom->id,
            'name' => 'Mustawa 2 Ikhwan',
        ]);

        $subject = DiniyyahSubject::firstOrCreate(
            ['code' => strtolower($subjectName)],
            ['name' => $subjectName, 'default_assessment_method' => 'weighted', 'is_active' => true],
        );
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
        ]);

        $session = ClassSession::where('session_name', $sessionName)->firstOrFail();
        DiniyyahTeachingSchedule::create([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'class_session_id' => $session->id,
            'day_of_week' => $dayOfWeek,
        ]);

        return [$assignment, $classroomTerm];
    }

    private function academicTermId(): int
    {
        $school = School::first() ?? School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::first() ?? AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);

        return AcademicTerm::firstOrCreate(
            ['academic_year_id' => $year->id, 'name' => 'Ganjil'],
            ['semester' => 'ganjil'],
        )->id;
    }
}