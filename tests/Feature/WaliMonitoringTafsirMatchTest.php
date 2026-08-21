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
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Bug "Jam ?" + duplikat Tafsir di Pantau Jurnal Kelas.
 *
 * Skenario prod: slot jadwal Tafsir memakai ClassSession bernama "Tafsir (M2 - M6)"
 * (input admin via Filament), sedangkan jurnal Tafsir serentak menyimpan
 * session_hour='tafsir' (konstanta mesin). Sebelum fix, matching equality
 * session_hour === session_name gagal → slot KOSONG + jurnal muncul sebagai baris
 * "Terisi (Ekstra)" berlabel "Jam ?".
 *
 * Setelah fix: jurnal mengisi slot terjadwal (TERISI), tanpa baris ekstra / "Jam ?".
 * Form reguler guru tidak menawarkan slot Tafsir yang benar-benar serentak.
 */
class WaliMonitoringTafsirMatchTest extends TestCase
{
    use RefreshDatabase;

    /** 2025-03-06 = Kamis (day_of_week 4). Bulan lampau supaya bukan currentMonth. */
    private const KAMIS = '2025-03-06';

    public function test_wali_monitoring_binds_tafsir_journal_to_scheduled_slot(): void
    {
        $ctx = $this->makeContext();

        // Jurnal Tafsir serentak: session_hour='tafsir' (konstanta mesin), bukan
        // "Tafsir (M2 - M6)" — inilah sumber mismatch di prod.
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['tafsirAssignment']->id,
            'date' => self::KAMIS,
            'session_hour' => SessionTimetable::SESSION_TAFSIR,
            'session_starts_at' => '09:50:00',
            'session_ends_at' => '10:20:00',
            'material' => 'Muqaddimah tafsir ringkas surat An Nas',
            'jp_count' => 1,
        ]);

        $this->actingAs($ctx['waliUser'])
            ->get(route('wali.diniyyah-journals.index', ['month' => 3, 'year' => 2025]))
            ->assertOk()
            // Slot terjadwal terisi, badge pakai nama slot jadwal (bukan "Jam ?").
            ->assertSee('Jam Tafsir (M2 - M6)')
            ->assertSee('09:50')
            ->assertSee('Muqaddimah tafsir ringkas surat An Nas')
            ->assertSee('Terisi')
            // Tidak ada baris "Jam ?" / "Terisi (Ekstra)".
            ->assertDontSee('Jam ?')
            ->assertDontSee('Terisi (Ekstra)');
    }

    public function test_guru_regular_form_does_not_offer_tafsir_slot(): void
    {
        $ctx = $this->makeContext();

        // Buka form reguler untuk kelas Tafsir yang tergabung dengan satu kelas
        // lain pada jam yang sama. Slot Tafsir serentak tidak boleh muncul.
        $this->actingAs($ctx['tafsirUser'])
            ->get(route('guru.diniyyah-journals.index', [
                'classroom_term_id' => $ctx['classroomTerm']->id,
                'date' => self::KAMIS,
            ]))
            ->assertOk()
            ->assertSee('Sesi 1')
            ->assertDontSee('Tafsir Al Quran');
    }

    public function test_wali_monitoring_export_is_a_valid_styled_xlsx_workbook(): void
    {
        $ctx = $this->makeContext();
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $ctx['tafsirAssignment']->id,
            'date' => self::KAMIS,
            'session_hour' => SessionTimetable::SESSION_TAFSIR,
            'session_starts_at' => '09:50:00',
            'session_ends_at' => '10:20:00',
            'material' => 'Materi ekspor wali',
            'jp_count' => 1,
        ]);

        $response = $this->actingAs($ctx['waliUser'])
            ->get(route('wali.diniyyah-journals.export-excel', ['month' => 3, 'year' => 2025]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
        $this->assertSame('PK', substr($response->getContent(), 0, 2));

        $path = tempnam(sys_get_temp_dir(), 'wali-monitoring-xlsx-test-');
        $this->assertNotFalse($path);
        file_put_contents($path, $response->getContent());
        try {
            $workbook = IOFactory::load($path);
        } finally {
            @unlink($path);
        }
        $sheet = $workbook->getSheetByName('Rekap Jurnal');
        $this->assertNotNull($sheet);
        $this->assertSame('REKAPITULASI JURNAL MENGAJAR DINIYYAH', $sheet->getCell('A1')->getValue());
        $sheetText = collect($sheet->toArray())->flatten()->implode("\n");
        $this->assertStringContainsString('Materi ekspor wali', $sheetText);
        $workbook->disconnectWorksheets();
    }

    private function makeContext(): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);

        // Wali kelas (yang memantau).
        $waliUser = User::factory()->create(['name' => 'Wali Kelas']);
        $waliUser->assignRole('guru');
        $waliTeacher = Teacher::create(['user_id' => $waliUser->id, 'name' => 'Wali Kelas']);

        // Guru Tafsir (pengisi jurnal serentak).
        $tafsirUser = User::factory()->create(['name' => 'Mursyidah Zaen']);
        $tafsirUser->assignRole('guru');
        $tafsirTeacher = Teacher::create(['user_id' => $tafsirUser->id, 'name' => 'Mursyidah Zaen']);

        $school = School::create(['name' => 'Griya Quran']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2024/2025']);
        $termId = AcademicTerm::create([
            'academic_year_id' => $year->id,
            'name' => 'Genap',
            'semester' => 'genap',
        ])->id;

        $classroom = Classroom::create(['name' => 'Mustawa 5 Akhwat']);
        SessionTimetable::seedForClassroom($classroom);

        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $termId,
            'classroom_id' => $classroom->id,
            'name' => $classroom->name,
        ]);

        // Wali kelas aktif untuk classroom ini.
        HomeroomAssignment::create([
            'classroom_term_id' => $classroomTerm->id,
            'teacher_id' => $waliTeacher->id,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        // ClassSession Tafsir DENGAN NAMA ADMIN (bukan 'tafsir') — replikasi prod.
        $tafsirSession = ClassSession::create([
            'session_name' => 'Tafsir (M2 - M6)',
            'starts_at' => '09:50:00',
            'ends_at' => '10:20:00',
            'is_break' => false,
        ]);

        $tafsirSubject = DiniyyahSubject::firstOrCreate(
            ['code' => 'tafsir'],
            [
                'name' => 'Tafsir Al Quran',
                'default_assessment_method' => 'weighted',
                'is_active' => true,
            ],
        );
        $tafsirClassSubject = DiniyyahClassSubject::create([
            'classroom_term_id' => $classroomTerm->id,
            'subject_id' => $tafsirSubject->id,
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
        ]);
        $tafsirAssignment = DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $tafsirClassSubject->id,
            'teacher_id' => $tafsirTeacher->id,
            'assignment_role' => 'primary',
        ]);
        // Jadwal Tafsir Kamis (day_of_week 4) memakai session bernama "Tafsir (M2 - M6)".
        DiniyyahTeachingSchedule::create([
            'diniyyah_teacher_assignment_id' => $tafsirAssignment->id,
            'day_of_week' => 4,
            'class_session_id' => $tafsirSession->id,
        ]);

        // Kelas Tafsir kedua pada guru, hari, dan jam sama. Ini yang membuat
        // Tafsir pada kelas wali benar-benar termasuk sesi serentak.
        $secondClassroom = Classroom::create(['name' => 'Mustawa 4 Akhwat']);
        SessionTimetable::seedForClassroom($secondClassroom);
        $secondClassroomTerm = ClassroomTerm::create([
            'academic_term_id' => $termId,
            'classroom_id' => $secondClassroom->id,
            'name' => $secondClassroom->name,
        ]);
        $secondClassSubject = DiniyyahClassSubject::create([
            'classroom_term_id' => $secondClassroomTerm->id,
            'subject_id' => $tafsirSubject->id,
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
        ]);
        $secondTafsirAssignment = DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $secondClassSubject->id,
            'teacher_id' => $tafsirTeacher->id,
            'assignment_role' => 'primary',
        ]);
        DiniyyahTeachingSchedule::create([
            'diniyyah_teacher_assignment_id' => $secondTafsirAssignment->id,
            'day_of_week' => 4,
            'class_session_id' => $tafsirSession->id,
        ]);

        // Assignment non-Tafsir (Fiqih) untuk guru yang sama di kelas yang sama,
        // supaya form reguler punya slot untuk dirender dan kita bisa memastikan
        // slot Tafsir di-skip (bukan sekadar tidak ada assignment).
        $fiqihSubject = DiniyyahSubject::firstOrCreate(
            ['code' => 'fiqih'],
            ['name' => 'Fiqih', 'default_assessment_method' => 'weighted'],
        );
        $fiqihClassSubject = DiniyyahClassSubject::create([
            'classroom_term_id' => $classroomTerm->id,
            'subject_id' => $fiqihSubject->id,
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
        ]);
        $fiqihAssignment = DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $fiqihClassSubject->id,
            'teacher_id' => $tafsirTeacher->id,
            'assignment_role' => 'primary',
        ]);
        $sessionOne = ClassSession::firstOrCreate(
            ['session_name' => '1'],
            ['starts_at' => '10:30:00', 'ends_at' => '11:00:00', 'is_break' => false],
        );
        DiniyyahTeachingSchedule::create([
            'diniyyah_teacher_assignment_id' => $fiqihAssignment->id,
            'day_of_week' => 4,
            'class_session_id' => $sessionOne->id,
        ]);

        return [
            'waliUser' => $waliUser,
            'tafsirUser' => $tafsirUser,
            'tafsirTeacher' => $tafsirTeacher,
            'classroomTerm' => $classroomTerm,
            'tafsirAssignment' => $tafsirAssignment,
            'fiqihAssignment' => $fiqihAssignment,
        ];
    }
}
