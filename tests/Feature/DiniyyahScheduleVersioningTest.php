<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\ClassSession;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahScheduleChangeLog;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Filament\Pages\DiniyyahScheduleVersionManager;
use App\Services\AdminMonthlyJpReportService;
use App\Services\DiniyyahScheduleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DiniyyahScheduleVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_schedule_is_kept_and_correction_only_changes_selected_dates(): void
    {
        $ctx = $this->context();
        $legacy = $this->legacySchedule($ctx['assignment'], 1, $ctx['sessionOne']);
        $this->assertSame(DiniyyahTeachingSchedule::STATUS_LEGACY, $legacy->version_status);
        $this->assertFalse($ctx['assignment']->isDeletable(), 'Assignment dengan jadwal historis tidak boleh menghapus versi jadwal via cascade.');
        $journal = DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
            'date' => '2026-09-08',
            'session_hour' => '1',
            'material' => 'Jurnal tersimpan',
            'jp_count' => 1,
        ]);
        $oldLog = DiniyyahScheduleChangeLog::create([
            'teacher_id' => $ctx['teacher']->id,
            'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
            'diniyyah_teaching_schedule_id' => $legacy->id,
            'entity_type' => 'schedule',
            'event' => 'updated',
            'change_summary' => 'Riwayat legacy yang perlu ditinjau',
            'old_values' => ['day_of_week' => 1],
            'new_values' => ['day_of_week' => 3],
        ]);

        $audit = app(DiniyyahScheduleVersionService::class)->apply(
            $ctx['assignment']->id,
            'correction',
            '2026-09-08',
            '2026-09-22',
            [['day_of_week' => 2, 'class_session_id' => $ctx['sessionTwo']->id]],
            'Jadwal awal salah hari dan sesi',
            null,
            $oldLog->id,
        );

        $before = DiniyyahTeachingSchedule::query()->forDate('2026-09-07')->where('diniyyah_teacher_assignment_id', $ctx['assignment']->id)->get();
        $inside = DiniyyahTeachingSchedule::query()->forDate('2026-09-08')->where('diniyyah_teacher_assignment_id', $ctx['assignment']->id)->get();
        $after = DiniyyahTeachingSchedule::query()->forDate('2026-09-23')->where('diniyyah_teacher_assignment_id', $ctx['assignment']->id)->get();

        $this->assertSame([[1, $ctx['sessionOne']->id]], $before->map(fn ($row) => [(int) $row->day_of_week, (int) $row->class_session_id])->all());
        $this->assertSame([[2, $ctx['sessionTwo']->id]], $inside->map(fn ($row) => [(int) $row->day_of_week, (int) $row->class_session_id])->all());
        $this->assertSame([[1, $ctx['sessionOne']->id]], $after->map(fn ($row) => [(int) $row->day_of_week, (int) $row->class_session_id])->all());
        $this->assertSame(DiniyyahTeachingSchedule::STATUS_SUPERSEDED, $legacy->fresh()->version_status);
        $this->assertSame(4, DiniyyahTeachingSchedule::where('diniyyah_teacher_assignment_id', $ctx['assignment']->id)->count());
        $this->assertDatabaseHas('diniyyah_class_journals', ['id' => $journal->id, 'material' => 'Jurnal tersimpan']);
        $this->assertNotNull($oldLog->fresh()->reviewed_at);
        $this->assertSame('correction', $oldLog->fresh()->change_type);
        $this->assertSame('correction', $audit->change_type);
        $this->assertSame('Jadwal awal salah hari dan sesi', $audit->reason);
        $this->assertSame('2026-09-08', $audit->effective_from->toDateString());
    }

    public function test_approved_schedule_starts_on_effective_date_and_old_version_stops_the_day_before(): void
    {
        $ctx = $this->context();
        $this->legacySchedule($ctx['assignment'], 1, $ctx['sessionOne']);

        app(DiniyyahScheduleVersionService::class)->apply(
            $ctx['assignment']->id,
            'approved',
            '2026-09-14',
            null,
            [['day_of_week' => 4, 'class_session_id' => $ctx['sessionTwo']->id]],
            'Perubahan jadwal disetujui',
            'PG-2026-41',
        );

        $before = DiniyyahTeachingSchedule::query()->forDate('2026-09-13')->where('diniyyah_teacher_assignment_id', $ctx['assignment']->id)->sole();
        $after = DiniyyahTeachingSchedule::query()->forDate('2026-09-14')->where('diniyyah_teacher_assignment_id', $ctx['assignment']->id)->sole();
        $this->assertSame(1, (int) $before->day_of_week);
        $this->assertSame('2026-09-13', $before->effective_until->toDateString());
        $this->assertSame(4, (int) $after->day_of_week);
        $this->assertNull($after->effective_until);
        $this->assertSame('PG-2026-41', DiniyyahScheduleChangeLog::query()->where('event', 'approved')->sole()->request_reference);
    }

    public function test_correction_splits_only_the_part_of_an_existing_bounded_version_it_covers(): void
    {
        $ctx = $this->context();
        $bounded = DiniyyahTeachingSchedule::withoutEvents(fn () => DiniyyahTeachingSchedule::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
            'class_session_id' => $ctx['sessionOne']->id,
            'day_of_week' => 1,
            'effective_from' => '2026-08-01',
            'effective_until' => '2026-10-31',
            'version_status' => DiniyyahTeachingSchedule::STATUS_ACTIVE,
        ]));

        app(DiniyyahScheduleVersionService::class)->apply(
            $ctx['assignment']->id,
            'correction',
            '2026-09-08',
            '2026-09-22',
            [['day_of_week' => 2, 'class_session_id' => $ctx['sessionTwo']->id]],
            'Koreksi sebagian masa berlaku',
        );

        $before = DiniyyahTeachingSchedule::query()->forDate('2026-08-31')->where('diniyyah_teacher_assignment_id', $ctx['assignment']->id)->sole();
        $inside = DiniyyahTeachingSchedule::query()->forDate('2026-09-08')->where('diniyyah_teacher_assignment_id', $ctx['assignment']->id)->sole();
        $after = DiniyyahTeachingSchedule::query()->forDate('2026-09-23')->where('diniyyah_teacher_assignment_id', $ctx['assignment']->id)->sole();

        $this->assertSame('2026-08-01', $before->effective_from->toDateString());
        $this->assertSame('2026-09-07', $before->effective_until->toDateString());
        $this->assertSame(2, (int) $inside->day_of_week);
        $this->assertSame('2026-09-08', $inside->effective_from->toDateString());
        $this->assertSame('2026-09-22', $inside->effective_until->toDateString());
        $this->assertSame('2026-09-23', $after->effective_from->toDateString());
        $this->assertSame('2026-10-31', $after->effective_until->toDateString());
        $this->assertSame(DiniyyahTeachingSchedule::STATUS_SUPERSEDED, $bounded->fresh()->version_status);
    }

    public function test_report_preview_uses_proposed_pattern_and_lists_missing_slots_that_change(): void
    {
        $ctx = $this->context();
        $this->legacySchedule($ctx['assignment'], 1, $ctx['sessionOne']);
        $versions = app(DiniyyahScheduleVersionService::class);
        $before = app(AdminMonthlyJpReportService::class)->buildForRange($ctx['term']->id, '2026-09-21', '2026-09-22');
        $proposed = $versions->previewSchedules($ctx['assignment']->id, '2026-09-21', '2026-09-22', [
            ['day_of_week' => 2, 'class_session_id' => $ctx['sessionTwo']->id],
        ]);
        $after = app(AdminMonthlyJpReportService::class)->buildForRange($ctx['term']->id, '2026-09-21', '2026-09-22', $proposed);

        $this->assertSame(['2026-09-21'], collect($before['missing'])->pluck('date')->values()->all());
        $this->assertSame(['2026-09-22'], collect($after['missing'])->pluck('date')->values()->all());
    }

    public function test_overlapping_versions_outside_correction_window_are_rejected(): void
    {
        $ctx = $this->context();
        DiniyyahTeachingSchedule::withoutEvents(function () use ($ctx): void {
            DiniyyahTeachingSchedule::create([
                'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
                'day_of_week' => 1,
                'class_session_id' => $ctx['sessionOne']->id,
                'effective_from' => '2026-09-01',
                'effective_until' => '2026-09-30',
                'version_status' => 'active',
            ]);
            DiniyyahTeachingSchedule::create([
                'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
                'day_of_week' => 1,
                'class_session_id' => $ctx['sessionOne']->id,
                'effective_from' => '2026-09-15',
                'effective_until' => '2026-10-15',
                'version_status' => 'active',
            ]);
        });

        try {
            app(DiniyyahScheduleVersionService::class)->validateChange(
                $ctx['assignment']->id,
                'correction',
                '2026-09-05',
                '2026-09-20',
                [['day_of_week' => 2, 'class_session_id' => $ctx['sessionTwo']->id]],
            );
            $this->fail('Rentang jadwal bertumpang tindih seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slots', $exception->errors());
        }
    }

    public function test_schedule_version_manager_is_available_to_schedule_managers(): void
    {
        $ctx = $this->context();
        $this->legacySchedule($ctx['assignment'], 1, $ctx['sessionOne']);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['name' => 'Admin Jadwal']);
        $admin->assignRole($adminRole);

        $this->actingAs($admin)
            ->get(DiniyyahScheduleVersionManager::getUrl())
            ->assertOk()
            ->assertSee('Jadwal berlaku per tanggal')
            ->assertSee('Koreksi kesalahan')
            ->assertSee('Legacy · belum ditinjau');
    }

    public function test_manager_requires_preview_and_applies_the_weekly_pattern_from_the_form(): void
    {
        $ctx = $this->context();
        $this->legacySchedule($ctx['assignment'], 1, $ctx['sessionOne']);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['name' => 'Admin Pratinjau']);
        $admin->assignRole($adminRole);
        $this->actingAs($admin);

        Livewire::test(DiniyyahScheduleVersionManager::class)
            ->set('assignmentId', $ctx['assignment']->id)
            ->set('changeType', 'correction')
            ->set('effectiveFrom', '2026-09-21')
            ->set('effectiveUntil', '2026-09-22')
            ->set('reason', 'Koreksi hari dan sesi')
            ->set('weeklySlots', [['day_of_week' => '2', 'class_session_id' => (string) $ctx['sessionTwo']->id]])
            ->call('preview')
            ->assertSet('preview.gone.0.date', '2026-09-21')
            ->assertSet('preview.appeared.0.date', '2026-09-22')
            ->call('apply')
            ->assertSet('preview', null);

        $resolved = DiniyyahTeachingSchedule::query()->forDate('2026-09-22')->where('diniyyah_teacher_assignment_id', $ctx['assignment']->id)->sole();
        $this->assertSame(2, (int) $resolved->day_of_week);
        $this->assertDatabaseHas('diniyyah_schedule_change_logs', [
            'diniyyah_teacher_assignment_id' => $ctx['assignment']->id,
            'event' => 'correction',
            'reason' => 'Koreksi hari dan sesi',
        ]);
    }

    private function context(): array
    {
        $user = User::factory()->create(['name' => 'Guru Versi']);
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => $user->name, 'niy' => 'VERSI-01', 'status' => 'active']);
        $school = School::create(['name' => 'Sekolah Versi']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil', 'starts_at' => '2026-07-01', 'ends_at' => '2026-12-31', 'is_active' => true]);
        $classroom = Classroom::create(['name' => 'Kelas Versi']);
        $classroomTerm = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => 'Kelas Versi']);
        $subject = DiniyyahSubject::create(['name' => 'Fiqih', 'code' => 'fiqih', 'default_assessment_method' => 'weighted']);
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
        $sessionOne = ClassSession::create(['session_name' => '1', 'starts_at' => '08:00', 'ends_at' => '09:00', 'is_break' => false]);
        $sessionTwo = ClassSession::create(['session_name' => '2', 'starts_at' => '09:00', 'ends_at' => '10:00', 'is_break' => false]);

        return compact('teacher', 'term', 'assignment', 'sessionOne', 'sessionTwo');
    }

    private function legacySchedule(DiniyyahTeacherAssignment $assignment, int $day, ClassSession $session): DiniyyahTeachingSchedule
    {
        return DiniyyahTeachingSchedule::withoutEvents(fn () => DiniyyahTeachingSchedule::create([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'class_session_id' => $session->id,
            'day_of_week' => $day,
        ]));
    }
}
