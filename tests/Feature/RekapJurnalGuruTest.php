<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Services\RekapJurnalGuruService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Rekap JP per guru diniyyah: asli/pengganti dihitung ke effectiveTeacher(),
 * tafsir serentak di-dedup per (guru, tanggal) jadi 1 JP.
 */
class RekapJurnalGuruTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_rekap_page(): void
    {
        $ctx = $this->makeContext();

        $response = $this->actingAs($ctx['admin'])
            ->get('/admin/rekap-jurnal-guru');

        $response->assertOk();
        $response->assertSee('Rekap Jurnal Kelas Semua Guru');
        $response->assertSee('Rekap JP per Guru');
    }

    public function test_guru_is_forbidden_to_open_rekap_page(): void
    {
        $ctx = $this->makeContext();

        $this->actingAs($ctx['userA'])
            ->get('/admin/rekap-jurnal-guru')
            ->assertForbidden();
    }

    public function test_aggregation_counts_asli_pengganti_and_dedups_tafsir(): void
    {
        $ctx = $this->makeContext();

        // 1) Sesi reguler oleh guru asli A -> 1 JP asli ke A.
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'substitute_teacher_id' => null,
            'date' => '2026-07-15',
            'session_hour' => '1',
            'material' => 'Reguler Bab 1',
            'jp_count' => 1,
        ]);

        // 2) Sesi pengganti: B menggantikan A -> 1 JP pengganti ke B (BUKAN ke A).
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'substitute_teacher_id' => $ctx['teacherB']->id,
            'date' => '2026-07-16',
            'session_hour' => '2',
            'material' => 'Pengganti Bab 2',
            'jp_count' => 1,
        ]);

        // 3) Tafsir serentak: 3 baris di tanggal SAMA, masing-masing di assignment
        //    berbeda (1 per kelas) namun semuanya milik guru A -> dedup 1 JP ke A.
        foreach ([$ctx['assignmentA']->id, $ctx['assignmentA2']->id, $ctx['assignmentA3']->id] as $i => $assignmentId) {
            DiniyyahClassJournal::create([
                'diniyyah_teacher_assignment_id' => $assignmentId,
                'substitute_teacher_id' => null,
                'date' => '2026-07-23',
                'session_hour' => 'tafsir',
                'material' => 'Tafsir serentak '.($i + 1),
                'jp_count' => 1,
            ]);
        }

        $recap = app(RekapJurnalGuruService::class)->build($ctx['term']->id, null, null);
        $byId = collect($recap['teachers'])->keyBy('teacher_id');

        // Teacher A: 1 asli + 1 tafsir (deduped dari 3) = 2 JP, 0 pengganti.
        $this->assertSame(2, $byId[$ctx['teacherA']->id]['total_jp']);
        $this->assertSame(1, $byId[$ctx['teacherA']->id]['sesi_asli']);
        $this->assertSame(0, $byId[$ctx['teacherA']->id]['sesi_pengganti']);
        $this->assertSame(1, $byId[$ctx['teacherA']->id]['sesi_tafsir']);

        // Teacher B: 1 JP pengganti, 0 asli, 0 tafsir.
        $this->assertSame(1, $byId[$ctx['teacherB']->id]['total_jp']);
        $this->assertSame(0, $byId[$ctx['teacherB']->id]['sesi_asli']);
        $this->assertSame(1, $byId[$ctx['teacherB']->id]['sesi_pengganti']);
        $this->assertSame(0, $byId[$ctx['teacherB']->id]['sesi_tafsir']);

        // Total: 1 (A asli) + 1 (B pengganti) + 1 (A tafsir) = 3 JP, 2 guru.
        $this->assertSame(3, $recap['stats']['total_jp']);
        $this->assertSame(2, $recap['stats']['total_teachers']);
        $this->assertSame(1, $recap['stats']['total_sesi_asli']);
        $this->assertSame(1, $recap['stats']['total_sesi_pengganti']);
        $this->assertSame(1, $recap['stats']['total_sesi_tafsir']);
    }

    public function test_date_range_filter_limits_journals(): void
    {
        $ctx = $this->makeContext();

        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'substitute_teacher_id' => null,
            'date' => '2026-07-01',
            'session_hour' => '1',
            'material' => 'Awal Bulan',
            'jp_count' => 1,
        ]);
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'substitute_teacher_id' => null,
            'date' => '2026-07-20',
            'session_hour' => '1',
            'material' => 'Akhir Bulan',
            'jp_count' => 1,
        ]);

        $recap = app(RekapJurnalGuruService::class)->build($ctx['term']->id, '2026-07-15', null);
        $byId = collect($recap['teachers'])->keyBy('teacher_id');

        // Hanya jurnal 07-20 yang masuk -> 1 JP.
        $this->assertSame(1, $byId[$ctx['teacherA']->id]['total_jp']);
        $this->assertSame(1, $recap['stats']['total_jp']);
    }

    public function test_csv_export_contains_per_teacher_rows_and_stats(): void
    {
        $ctx = $this->makeContext();

        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['assignmentA']->id,
            'substitute_teacher_id' => null,
            'date' => '2026-07-15',
            'session_hour' => '1',
            'material' => 'Reguler',
            'jp_count' => 1,
        ]);

        $response = $this->actingAs($ctx['admin'])
            ->get(route('admin.rekap-jurnal-guru.export', [
                'academic_term_id' => $ctx['term']->id,
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Rekap Jurnal Kelas Semua Guru', $content);
        $this->assertStringContainsString('Sesi Asli', $content);
        $this->assertStringContainsString('Sesi Pengganti', $content);
        $this->assertStringContainsString('Total JP', $content);
        $this->assertStringContainsString($ctx['teacherA']->name, $content);
    }

    public function test_guru_is_forbidden_to_export_csv(): void
    {
        $ctx = $this->makeContext();

        $this->actingAs($ctx['userA'])
            ->get(route('admin.rekap-jurnal-guru.export'))
            ->assertForbidden();
    }

    private function makeContext(): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['name' => 'Admin']);
        $admin->assignRole('admin');

        $userA = User::factory()->create(['name' => 'Guru A']);
        $userA->assignRole('guru');
        $teacherA = Teacher::create(['user_id' => $userA->id, 'name' => 'Guru A', 'niy' => 'N001', 'status' => 'active']);

        $userB = User::factory()->create(['name' => 'Guru B']);
        $userB->assignRole('guru');
        $teacherB = Teacher::create(['user_id' => $userB->id, 'name' => 'Guru B', 'niy' => 'N002', 'status' => 'active']);

        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => 'Genap',
            'semester' => 'genap',
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-12-31',
            'is_active' => true,
        ]);
        $classroom = Classroom::create(['name' => 'Mustawa 1']);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
            'name' => 'Mustawa 1 Ikhwan',
        ]);
        $subject = DiniyyahSubject::create(['code' => 'fiqih', 'name' => 'Fiqih', 'default_assessment_method' => 'weighted']);
        $classSubject = DiniyyahClassSubject::create([
            'classroom_term_id' => $classroomTerm->id,
            'subject_id' => $subject->id,
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
        ]);
        $assignmentA = DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $classSubject->id,
            'teacher_id' => $teacherA->id,
            'assignment_role' => 'primary',
        ]);

        // Dua assignment tambahan milik guru A (subject berbeda, classSubject
        // berbeda di classroomTerm sama) untuk mensimulasikan tafsir serentak
        // (1 jurnal per kelas/assignment di tanggal yang sama).
        $subject2 = DiniyyahSubject::create(['code' => 'tafsir2', 'name' => 'Tafsir M2', 'default_assessment_method' => 'weighted']);
        $classSubject2 = DiniyyahClassSubject::create([
            'classroom_term_id' => $classroomTerm->id,
            'subject_id' => $subject2->id,
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
        ]);
        $assignmentA2 = DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $classSubject2->id,
            'teacher_id' => $teacherA->id,
            'assignment_role' => 'primary',
        ]);

        $subject3 = DiniyyahSubject::create(['code' => 'tafsir3', 'name' => 'Tafsir M3', 'default_assessment_method' => 'weighted']);
        $classSubject3 = DiniyyahClassSubject::create([
            'classroom_term_id' => $classroomTerm->id,
            'subject_id' => $subject3->id,
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
        ]);
        $assignmentA3 = DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $classSubject3->id,
            'teacher_id' => $teacherA->id,
            'assignment_role' => 'primary',
        ]);

        return [
            'admin' => $admin,
            'userA' => $userA,
            'teacherA' => $teacherA,
            'userB' => $userB,
            'teacherB' => $teacherB,
            'term' => $term,
            'classroomTerm' => $classroomTerm,
            'assignmentA' => $assignmentA,
            'assignmentA2' => $assignmentA2,
            'assignmentA3' => $assignmentA3,
        ];
    }
}