<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\ClassSession;
use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\School;
use App\Models\SchoolHoliday;
use App\Models\Teacher;
use App\Models\User;
use App\Services\GuruPerformaService;
use App\Support\SessionTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kartu performa jurnal mengajar guru: 3 angka (sudah diisi / kosong /
 * digantikan) per bulan, plus daftar slot kosong. Tafsir serentak di-dedup per
 * (guru, tanggal, jam mulai, jam selesai) = 1 JP, konsisten dengan rekap jurnal.
 *
 * "Today" dibekukan ke Senin 2026-08-10 (WIB) supaya tanggal lewat/future
 * deterministik. App tz=UTC → test now diset ke WIB noon (UTC same date).
 */
class GuruPerformaTest extends TestCase
{
    use RefreshDatabase;

    /** Senin 2026-08-10 — hari ini (WIB). */
    private const TODAY = '2026-08-10';

    /** Selasa 2026-08-04 (lewat, bulan berjalan). */
    private const SELASA_LEWAT = '2026-08-04';

    /** Kamis 2026-08-06 (lewat, bulan berjalan) — hari Tafsir. */
    private const KAMIS_LEWAT = '2026-08-06';

    /** Rabu 2026-08-12 (future, masih bulan berjalan). */
    private const RABU_FUTURE = '2026-08-12';

    /** Selasa 2026-07-14 (bulan lalu). */
    private const SELASA_BULAN_LALU = '2026-07-14';

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    public function test_dashboard_skips_performa_card_for_guru_without_assignment(): void
    {
        $this->setNow(self::TODAY);
        $guru = $this->makeGuru('Guru Tanpa Tugas');

        $response = $this->actingAs($guru['user'])->get(route('guru.dashboard'));

        $response->assertOk()->assertDontSee('Performa Mengajar Saya');
    }

    public function test_performa_counts_filled_regular_slot(): void
    {
        $this->setNow(self::TODAY);
        $guru = $this->makeGuru('Guru A');
        [$assignment] = $this->makeRegularAssignment($guru['teacher'], 'Fiqih', 2, '1');

        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'date' => self::SELASA_LEWAT,
            'session_hour' => '1',
            'session_starts_at' => '10:30:00',
            'session_ends_at' => '11:00:00',
            'material' => 'Bab Thaharah',
            'jp_count' => 2,
        ]);

        $performa = app(GuruPerformaService::class)->calculate($guru['teacher'], 8, 2026);

        $this->assertSame(2, $performa['stats']['jp_berhasil_terlaksana']);
        $this->assertSame(2, $performa['stats']['sudah_diisi']);
        $this->assertSame(0, $performa['stats']['kosong']);
        $this->assertSame(0, $performa['stats']['digantikan']);
        $this->assertSame(0, $performa['stats']['menggantikan_guru_lain']);
    }

    public function test_performa_counts_empty_regular_slot(): void
    {
        $this->setNow(self::TODAY);
        $guru = $this->makeGuru('Guru A');
        [$assignment] = $this->makeRegularAssignment($guru['teacher'], 'Fiqih', 2, '1');

        $performa = app(GuruPerformaService::class)->calculate($guru['teacher'], 8, 2026);

        $this->assertSame(0, $performa['stats']['jp_berhasil_terlaksana']);
        $this->assertSame(1, $performa['stats']['kosong']);
        $this->assertCount(1, $performa['empty_slots']);
        $slot = $performa['empty_slots'][0];
        $this->assertStringContainsString('classroom_term_id=', $slot['fill_url']);
        $this->assertStringContainsString('date='.self::SELASA_LEWAT, $slot['fill_url']);
        $this->assertStringContainsString('diniyyah-journals', $slot['fill_url']);
    }

    public function test_performa_counts_substituted_slot(): void
    {
        $this->setNow(self::TODAY);
        $guru = $this->makeGuru('Guru A');
        [$assignment] = $this->makeRegularAssignment($guru['teacher'], 'Fiqih', 2, '1');
        $pengganti = $this->makeGuru('Pengganti');

        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'substitute_teacher_id' => $pengganti['teacher']->id,
            'date' => self::SELASA_LEWAT,
            'session_hour' => '1',
            'session_starts_at' => '10:30:00',
            'session_ends_at' => '11:00:00',
            'material' => 'Digantikan',
            'jp_count' => 1,
        ]);

        $performa = app(GuruPerformaService::class)->calculate($guru['teacher'], 8, 2026);

        $this->assertSame(1, $performa['stats']['digantikan']);
        $this->assertSame(0, $performa['stats']['jp_berhasil_terlaksana']);
        $this->assertSame(0, $performa['stats']['sudah_diisi']);
        $this->assertSame(0, $performa['stats']['kosong']);
        $this->assertSame(0, $performa['stats']['menggantikan_guru_lain']);

        $performaPengganti = app(GuruPerformaService::class)->calculate($pengganti['teacher'], 8, 2026);

        $this->assertSame(1, $performaPengganti['stats']['jp_berhasil_terlaksana']);
        $this->assertSame(1, $performaPengganti['stats']['menggantikan_guru_lain']);
        $this->assertSame(1, $performaPengganti['stats']['total_jurnal']);
        $this->assertCount(1, $performaPengganti['journal_rows']);
        $this->assertSame('Digantikan', $performaPengganti['journal_rows']->first()['material']);
        $this->assertSame('Jurnal pengganti', $performaPengganti['journal_rows']->first()['type_label']);
    }

    public function test_performa_tafsir_dedup_filled_counts_one(): void
    {
        $this->setNow(self::TODAY);
        $guru = $this->makeGuru('Guru Tafsir');
        $assignments = $this->makeTafsirAssignments($guru['teacher'], ['Mustawa 2 Ikhwan', 'Mustawa 3 Ikhwan', 'Mustawa 4 Ikhwan']);

        foreach ($assignments as $assignment) {
            DiniyyahClassJournal::create([
                'diniyyah_teacher_assignment_id' => $assignment->id,
                'date' => self::KAMIS_LEWAT,
                'session_hour' => SessionTimetable::SESSION_TAFSIR,
                'session_starts_at' => '09:50:00',
                'session_ends_at' => '10:20:00',
                'material' => 'Tafsir Al-Fatihah',
                'jp_count' => 1,
            ]);
        }

        $performa = app(GuruPerformaService::class)->calculate($guru['teacher'], 8, 2026);

        $this->assertSame(1, $performa['stats']['jp_berhasil_terlaksana'], '3 jurnal tafsir serentak = 1 JP berhasil terlaksana.');
        $this->assertSame(1, $performa['stats']['sudah_diisi'], '3 jurnal tafsir serentak = 1 JP (dedup).');
        $this->assertSame(0, $performa['stats']['kosong']);
    }

    public function test_total_jp_deduplicates_tafsir_using_actual_journal_session_after_schedule_changes(): void
    {
        $this->setNow(self::TODAY);
        $guru = $this->makeGuru('Guru Tafsir');
        $assignments = $this->makeTafsirAssignments($guru['teacher'], ['Mustawa 2 Akhwat', 'Mustawa 3 Akhwat']);

        // Salah satu jadwal berubah setelah jurnal dibuat. Snapshot jurnal
        // membuktikan keduanya tetap dilaksanakan serentak pukul 09:50-10:20.
        DiniyyahTeachingSchedule::query()
            ->where('diniyyah_teacher_assignment_id', $assignments[1]->id)
            ->update(['class_session_id' => ClassSession::where('session_name', '2')->firstOrFail()->id]);

        foreach ($assignments as $assignment) {
            DiniyyahClassJournal::create([
                'diniyyah_teacher_assignment_id' => $assignment->id,
                'date' => self::KAMIS_LEWAT,
                'session_hour' => SessionTimetable::SESSION_TAFSIR,
                'session_starts_at' => '09:50:00',
                'session_ends_at' => '10:20:00',
                'material' => 'Tafsir serentak',
                'jp_count' => 1,
            ]);
        }

        // Penanda sesi jurnal adalah sumber kebenaran untuk Tafsir serentak,
        // walaupun metadata mapel kemudian berubah.
        $assignments[0]->classSubject->subject->update(['code' => 'kajian', 'name' => 'Kajian Al-Quran']);

        $performa = app(GuruPerformaService::class)->calculate($guru['teacher'], 8, 2026);

        $this->assertSame(1, $performa['stats']['jp_berhasil_terlaksana']);
        $this->assertCount(1, $performa['jp_rows']);
        $this->assertSame('tafsir_simultaneous', $performa['jp_rows']->first()['type']);
        $this->assertSame(2, $performa['jp_rows']->first()['journal_count']);
    }

    public function test_total_jp_keeps_different_simultaneous_tafsir_times_separate_on_same_date(): void
    {
        $this->setNow(self::TODAY);
        $guru = $this->makeGuru('Guru Dua Sesi Tafsir');
        $assignments = $this->makeTafsirAssignments($guru['teacher'], [
            'Mustawa 2 Akhwat',
            'Mustawa 3 Akhwat',
            'Mustawa 4 Akhwat',
        ]);

        foreach ($assignments as $index => $assignment) {
            $isSecondSession = $index === 2;

            DiniyyahClassJournal::create([
                'diniyyah_teacher_assignment_id' => $assignment->id,
                'date' => self::KAMIS_LEWAT,
                'session_hour' => SessionTimetable::SESSION_TAFSIR,
                'session_starts_at' => $isSecondSession ? '10:30:00' : '09:50:00',
                'session_ends_at' => $isSecondSession ? '11:00:00' : '10:20:00',
                'material' => 'Tafsir serentak',
                'jp_count' => 1,
            ]);
        }

        $performa = app(GuruPerformaService::class)->calculate($guru['teacher'], 8, 2026);

        $this->assertSame(2, $performa['stats']['jp_berhasil_terlaksana']);
        $this->assertCount(2, $performa['jp_rows']);
        $this->assertSame([1, 2], $performa['jp_rows']->pluck('journal_count')->sort()->values()->all());
    }

    public function test_total_jp_treats_individual_tafsir_in_regular_session_as_regular_journal(): void
    {
        $this->setNow(self::TODAY);
        $guru = $this->makeGuru('Guru Tafsir Individual');
        [$assignment] = $this->makeTafsirAssignments($guru['teacher'], ['Mustawa 1 Akhwat']);

        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'date' => self::KAMIS_LEWAT,
            'session_hour' => '2',
            'session_starts_at' => '11:00:00',
            'session_ends_at' => '11:30:00',
            'material' => 'Tafsir individual',
            'jp_count' => 1,
        ]);

        $performa = app(GuruPerformaService::class)->calculate($guru['teacher'], 8, 2026);

        $this->assertSame(1, $performa['stats']['jp_berhasil_terlaksana']);
        $this->assertCount(1, $performa['jp_rows']);
        $this->assertSame('regular', $performa['jp_rows']->first()['type']);
        $this->assertSame('Sesi 2', $performa['jp_rows']->first()['session_label']);
    }

    public function test_performa_tafsir_dedup_substituted_counts_one(): void
    {
        $this->setNow(self::TODAY);
        $guru = $this->makeGuru('Guru Tafsir');
        $assignments = $this->makeTafsirAssignments($guru['teacher'], ['Mustawa 2 Ikhwan', 'Mustawa 3 Ikhwan']);
        $pengganti = $this->makeGuru('Pengganti');

        foreach ($assignments as $assignment) {
            DiniyyahClassJournal::create([
                'diniyyah_teacher_assignment_id' => $assignment->id,
                'substitute_teacher_id' => $pengganti['teacher']->id,
                'date' => self::KAMIS_LEWAT,
                'session_hour' => SessionTimetable::SESSION_TAFSIR,
                'session_starts_at' => '09:50:00',
                'session_ends_at' => '10:20:00',
                'material' => 'Pengganti',
                'jp_count' => 1,
            ]);
        }

        $performa = app(GuruPerformaService::class)->calculate($guru['teacher'], 8, 2026);

        $this->assertSame(1, $performa['stats']['digantikan']);
        $this->assertSame(0, $performa['stats']['sudah_diisi']);

        $performaPengganti = app(GuruPerformaService::class)->calculate($pengganti['teacher'], 8, 2026);

        $this->assertSame(1, $performaPengganti['stats']['jp_berhasil_terlaksana']);
        $this->assertSame(1, $performaPengganti['stats']['menggantikan_guru_lain']);
        $this->assertSame(2, $performaPengganti['stats']['total_jurnal']);
        $this->assertCount(1, $performaPengganti['jp_rows']);
        $this->assertSame('tafsir_simultaneous', $performaPengganti['jp_rows']->first()['type']);
    }

    public function test_performa_tafsir_dedup_empty_counts_one(): void
    {
        $this->setNow(self::TODAY);
        $guru = $this->makeGuru('Guru Tafsir');
        $this->makeTafsirAssignments($guru['teacher'], ['Mustawa 2 Ikhwan', 'Mustawa 3 Ikhwan', 'Mustawa 4 Ikhwan']);

        $performa = app(GuruPerformaService::class)->calculate($guru['teacher'], 8, 2026);

        $this->assertSame(1, $performa['stats']['kosong'], '3 slot tafsir kosong di hari yang sama = 1 (dedup).');
        $this->assertCount(1, $performa['empty_slots']);
        $slot = $performa['empty_slots'][0];
        $this->assertTrue($slot['is_tafsir']);
        $this->assertStringContainsString('diniyyah-tafsir-journals', $slot['fill_url']);
        $this->assertStringContainsString('date='.self::KAMIS_LEWAT, $slot['fill_url']);
        $this->assertStringContainsString('Mustawa 2 Ikhwan', $slot['classroom_names']);
        $this->assertStringContainsString('Mustawa 4 Ikhwan', $slot['classroom_names']);
    }

    public function test_performa_keeps_individual_tafsir_separate_from_simultaneous_group(): void
    {
        $this->setNow(self::TODAY);
        $guru = $this->makeGuru('Guru Tafsir Campuran');
        $simultaneous = $this->makeTafsirAssignments($guru['teacher'], ['Mustawa 2 Akhwat', 'Mustawa 3 Akhwat']);
        foreach ($simultaneous as $assignment) {
            $assignment->update(['starts_at' => self::KAMIS_LEWAT, 'ends_at' => self::KAMIS_LEWAT]);
        }

        $classroom = Classroom::create(['name' => 'Mustawa 1 Akhwat']);
        SessionTimetable::seedForClassroom($classroom);
        $classroomTerm = ClassroomTerm::create([
            'academic_term_id' => $this->academicTermId(),
            'classroom_id' => $classroom->id,
            'name' => 'Mustawa 1 Akhwat',
        ]);
        $tafsir = DiniyyahSubject::where('code', 'tafsir')->firstOrFail();
        $classSubject = DiniyyahClassSubject::create([
            'classroom_term_id' => $classroomTerm->id,
            'subject_id' => $tafsir->id,
            'assessment_method' => 'weighted',
            'kkm' => 70,
            'daily_weight' => 40,
            'exam_weight' => 60,
        ]);
        $individual = DiniyyahTeacherAssignment::create([
            'diniyyah_class_subject_id' => $classSubject->id,
            'teacher_id' => $guru['teacher']->id,
            'assignment_role' => 'primary',
            'starts_at' => '2026-08-07',
            'ends_at' => '2026-08-07',
        ]);
        DiniyyahTeachingSchedule::create([
            'diniyyah_teacher_assignment_id' => $individual->id,
            'class_session_id' => ClassSession::where('session_name', '2')->firstOrFail()->id,
            'day_of_week' => 5,
        ]);

        $performa = app(GuruPerformaService::class)->calculate($guru['teacher'], 8, 2026);

        $this->assertSame(2, $performa['stats']['kosong']);
        $this->assertCount(2, $performa['empty_slots']);
        $this->assertCount(1, array_filter($performa['empty_slots'], fn (array $slot): bool => $slot['is_tafsir']));
        $this->assertCount(1, array_filter($performa['empty_slots'], fn (array $slot): bool => ! $slot['is_tafsir']));
        $this->assertContains('Mustawa 1 Akhwat', array_column($performa['empty_slots'], 'classroom_names'));
    }

    public function test_performa_excludes_future_dates_in_current_month(): void
    {
        $this->setNow(self::TODAY);
        $guru = $this->makeGuru('Guru A');
        // Jadwal Selasa. Tuesday lewat (2026-08-04) diisi; Tuesday future
        // (2026-08-11/18/25) dibiarkan kosong → harus di-exclude.
        [$assignment] = $this->makeRegularAssignment($guru['teacher'], 'Fiqih', 2, '1');
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'date' => self::SELASA_LEWAT,
            'session_hour' => '1',
            'session_starts_at' => '10:30:00',
            'session_ends_at' => '11:00:00',
            'material' => 'Pengisi Selasa lewat',
            'jp_count' => 1,
        ]);

        $performa = app(GuruPerformaService::class)->calculate($guru['teacher'], 8, 2026);

        $this->assertSame(0, $performa['stats']['kosong'], 'Tuesday future di-exclude; Tuesday lewat sudah terisi.');
        $this->assertSame(1, $performa['stats']['sudah_diisi']);
    }

    public function test_performa_excludes_holidays_from_kosong(): void
    {
        $this->setNow(self::TODAY);
        $guru = $this->makeGuru('Guru A');
        $this->makeRegularAssignment($guru['teacher'], 'Fiqih', 2, '1');

        $termId = $this->academicTermId();
        $schoolId = School::first()?->id ?? School::create(['name' => 'Griya Quran'])->id;
        SchoolHoliday::create([
            'school_id' => $schoolId,
            'academic_term_id' => $termId,
            'holiday_date' => self::SELASA_LEWAT,
            'title' => 'Libur Nasional',
        ]);

        $performa = app(GuruPerformaService::class)->calculate($guru['teacher'], 8, 2026);

        $this->assertSame(0, $performa['stats']['kosong'], 'Hari libur tidak dihitung kosong.');
        $this->assertSame(0, $performa['stats']['total']);
    }

    public function test_performa_today_unfilled_not_counted_as_kosong(): void
    {
        // "Today" = Senin 2026-08-03 (Senin pertama bulan) → tidak ada Senin
        // lewat lain di bulan ini, supaya jelas bahwa hari ini sendiri yang
        // belum terisi TIDAK dihitung kosong.
        $this->setNow('2026-08-03');
        $guru = $this->makeGuru('Guru A');
        $this->makeRegularAssignment($guru['teacher'], 'Fiqih', 1, '1');

        $performa = app(GuruPerformaService::class)->calculate($guru['teacher'], 8, 2026);

        $this->assertSame(0, $performa['stats']['kosong'], 'Hari ini belum lewat → tidak kosong.');
        $this->assertSame(0, $performa['stats']['sudah_diisi']);
        $this->assertSame([], $performa['empty_slots']);
    }

    public function test_performa_today_filled_counts_as_sudah(): void
    {
        $this->setNow('2026-08-03');
        $guru = $this->makeGuru('Guru A');
        [$assignment] = $this->makeRegularAssignment($guru['teacher'], 'Fiqih', 1, '1');

        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'date' => '2026-08-03',
            'session_hour' => '1',
            'session_starts_at' => '10:30:00',
            'session_ends_at' => '11:00:00',
            'material' => 'Hari ini',
            'jp_count' => 1,
        ]);

        $performa = app(GuruPerformaService::class)->calculate($guru['teacher'], 8, 2026);

        $this->assertSame(1, $performa['stats']['sudah_diisi']);
        $this->assertSame(0, $performa['stats']['kosong']);
    }

    public function test_performa_past_month_uses_full_month_range(): void
    {
        $this->setNow(self::TODAY);
        $guru = $this->makeGuru('Guru A');
        // Jadwal Selasa. Juli 2026 punya 4 Tuesday (7, 14, 21, 28) — semua
        // lewat. Range bulan lalu mencakup sampai akhir bulan (28 Jul).
        $this->makeRegularAssignment($guru['teacher'], 'Fiqih', 2, '1');

        $performa = app(GuruPerformaService::class)->calculate($guru['teacher'], 7, 2026);

        $this->assertSame(4, $performa['stats']['kosong'], '4 Tuesday di Juli semuanya kosong.');
        $this->assertFalse($performa['is_current_month']);
        $dates = array_column($performa['empty_slots'], 'date');
        $this->assertContains('2026-07-28', $dates, 'Akhir bulan (28 Jul) termasuk, bukan dipotong di hari ini.');
    }

    public function test_performa_future_month_query_clamped_to_current(): void
    {
        $this->setNow(self::TODAY);
        $guru = $this->makeGuru('Guru A');
        $this->makeRegularAssignment($guru['teacher'], 'Fiqih', 2, '1');

        $performa = app(GuruPerformaService::class)->calculate($guru['teacher'], 12, 2026);

        $this->assertSame(8, $performa['month'], 'Bulan masa depan di-clamp ke bulan berjalan.');
        $this->assertSame(2026, $performa['year']);
        $this->assertTrue($performa['is_current_month']);
        $this->assertSame(1, $performa['stats']['kosong'], 'Setelah clamp, slot Agustus yang lewat tetap dihitung.');
    }

    public function test_performa_detail_page_lists_empty_slots(): void
    {
        $this->setNow(self::TODAY);
        $guru = $this->makeGuru('Guru A');
        $this->makeRegularAssignment($guru['teacher'], 'Fiqih', 2, '1');

        $response = $this->actingAs($guru['user'])
            ->get(route('guru.performa', ['month' => 8, 'year' => 2026]));

        $response->assertOk()
            ->assertSee('Performa Mengajar Saya')
            ->assertSee('Slot Jurnal Kosong')
            ->assertSee('Fiqih')
            ->assertSee('Isi Jurnal');
    }

    public function test_performa_detail_renders_status_chart_and_accessible_summary(): void
    {
        $this->setNow(self::TODAY);
        $guru = $this->makeGuru('Guru A');
        $this->makeRegularAssignment($guru['teacher'], 'Fiqih', 2, '1');

        $response = $this->actingAs($guru['user'])
            ->get(route('guru.performa', ['month' => 8, 'year' => 2026]));

        $response->assertOk()
            ->assertSee('guru-performance-chart', false)
            ->assertSee('Status jadwal mengajar Anda')
            ->assertSee('Rincian status pengisian jurnal')
            ->assertSee('Total JP')
            ->assertSee('Jurnal yang Anda isi, termasuk pengganti')
            ->assertSee('Menggantikan Guru Lain')
            ->assertSee('Rincian perhitungan Total JP')
            ->assertSee('data-jp-calculator="journal-session-v2"', false)
            ->assertSee('Sudah Diisi')
            ->assertSee('Kosong')
            ->assertSee('Digantikan')
            ->assertSee('Dibebaskan')
            ->assertSee('data-performance-chart-fallback', false)
            ->assertSee("window.addEventListener('load', initialiseChart", false)
            ->assertSee("indexAxis: 'y'", false);
    }

    public function test_performa_detail_offers_excel_and_pdf_downloads(): void
    {
        $this->setNow(self::TODAY);
        $guru = $this->makeGuru('Guru A');
        [$assignment] = $this->makeRegularAssignment($guru['teacher'], 'Fiqih', 2, '1');
        DiniyyahClassJournal::create([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'date' => self::SELASA_LEWAT,
            'session_hour' => '1',
            'session_starts_at' => '10:30:00',
            'session_ends_at' => '11:00:00',
            'material' => 'Bab Thaharah',
            'jp_count' => 1,
        ]);

        $page = $this->actingAs($guru['user'])
            ->get(route('guru.performa', ['month' => 8, 'year' => 2026]));

        $page->assertOk()
            ->assertSee(route('guru.performa.export', ['format' => 'xlsx', 'month' => 8, 'year' => 2026], false))
            ->assertSee(route('guru.performa.export', ['format' => 'pdf', 'month' => 8, 'year' => 2026], false));

        $xlsx = $this->actingAs($guru['user'])
            ->get(route('guru.performa.export', ['format' => 'xlsx', 'month' => 8, 'year' => 2026]));
        $xlsx->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertSame('PK', substr($xlsx->getContent(), 0, 2));
        $path = tempnam(sys_get_temp_dir(), 'performa-xlsx-test-');
        $this->assertNotFalse($path);
        file_put_contents($path, $xlsx->getContent());
        try {
            $workbook = IOFactory::load($path);
        } finally {
            @unlink($path);
        }
        $this->assertNotNull($workbook->getSheetByName('Detail Jurnal'));
        $this->assertNotNull($workbook->getSheetByName('Slot Kosong'));
        $this->assertNotNull($workbook->getSheetByName('Rincian Total JP'));
        $summaryText = collect($workbook->getSheetByName('Ringkasan')->toArray())->flatten()->implode("\n");
        $this->assertStringContainsString('Total JP berhasil terlaksana', $summaryText);
        $this->assertStringContainsString('Menggantikan guru lain', $summaryText);
        $detailText = collect($workbook->getSheetByName('Detail Jurnal')->toArray())->flatten()->implode("\n");
        $this->assertStringContainsString('Bab Thaharah', $detailText);
        $this->assertStringContainsString('Mustawa 2 Ikhwan', $detailText);
        $workbook->disconnectWorksheets();

        $pdf = $this->actingAs($guru['user'])
            ->get(route('guru.performa.export', ['format' => 'pdf', 'month' => 8, 'year' => 2026]));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_dashboard_performa_card_links_to_detail(): void
    {
        $this->setNow(self::TODAY);
        $guru = $this->makeGuru('Guru A');
        $this->makeRegularAssignment($guru['teacher'], 'Fiqih', 2, '1');

        $response = $this->actingAs($guru['user'])->get(route('guru.dashboard'));

        $response->assertOk()
            ->assertSee('Performa Mengajar Saya')
            ->assertSee(route('guru.performa', ['month' => 8, 'year' => 2026], false));
    }

    public function test_non_guru_role_cannot_access_performa(): void
    {
        $this->setNow(self::TODAY);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['name' => 'Admin']);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('guru.performa'));

        $response->assertForbidden();
    }

    // ----- Helpers -----

    private function setNow(string $wibDate): void
    {
        // WIB noon → UTC (app tz) supaya Carbon::now('Asia/Jakarta') mendarat
        // di $wibDate dan UTC date == WIB date (hindari boundary larut malam).
        Carbon::setTestNow(Carbon::parse($wibDate.' 12:00:00', 'Asia/Jakarta')->setTimezone('UTC'));
    }

    /** @return array{0: User, 1: Teacher} */
    private function makeGuru(string $name): array
    {
        Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('guru');
        $teacher = Teacher::create(['user_id' => $user->id, 'name' => $name]);

        return ['user' => $user, 'teacher' => $teacher];
    }

    /**
     * Buat assignment reguler (non-tafsir) + jadwal (day_of_week, session).
     * Mengembalikan [assignment, classroomTerm].
     *
     * @return array{0: DiniyyahTeacherAssignment, 1: ClassroomTerm}
     */
    private function makeRegularAssignment(Teacher $teacher, string $subjectName, int $dayOfWeek, string $sessionName): array
    {
        $classroom = Classroom::create(['name' => 'Mustawa 2 Ikhwan']);
        SessionTimetable::seedForClassroom($classroom);
        $termId = $this->academicTermId();
        $classroomTerm = ClassroomTerm::create(['academic_term_id' => $termId, 'classroom_id' => $classroom->id, 'name' => 'Mustawa 2 Ikhwan']);

        $subject = DiniyyahSubject::firstOrCreate(
            ['code' => strtolower($subjectName)],
            ['name' => $subjectName, 'default_assessment_method' => 'weighted', 'is_active' => true],
        );
        $classSubject = DiniyyahClassSubject::create(['classroom_term_id' => $classroomTerm->id, 'subject_id' => $subject->id, 'assessment_method' => 'weighted', 'kkm' => 70, 'daily_weight' => 40, 'exam_weight' => 60]);
        $assignment = DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $classSubject->id, 'teacher_id' => $teacher->id, 'assignment_role' => 'primary']);

        $session = ClassSession::where('session_name', $sessionName)->firstOrFail();
        DiniyyahTeachingSchedule::create([
            'diniyyah_teacher_assignment_id' => $assignment->id,
            'class_session_id' => $session->id,
            'day_of_week' => $dayOfWeek,
        ]);

        return [$assignment, $classroomTerm];
    }

    /**
     * Buat assignment Tafsir per nama kelas + jadwal Kamis (day 4).
     *
     * @param  list<string>  $classroomNames
     * @return list<DiniyyahTeacherAssignment>
     */
    private function makeTafsirAssignments(Teacher $teacher, array $classroomNames): array
    {
        $termId = $this->academicTermId();
        $tafsirSubject = DiniyyahSubject::firstOrCreate(
            ['code' => 'tafsir'],
            ['name' => 'Tafsir Al Quran', 'default_assessment_method' => 'weighted', 'is_active' => true],
        );
        $tafsirSession = ClassSession::where('session_name', SessionTimetable::SESSION_TAFSIR)->firstOrFail();

        $assignments = [];
        foreach ($classroomNames as $name) {
            $classroom = Classroom::create(['name' => $name]);
            SessionTimetable::seedForClassroom($classroom);
            $classroomTerm = ClassroomTerm::create(['academic_term_id' => $termId, 'classroom_id' => $classroom->id, 'name' => $name]);
            $classSubject = DiniyyahClassSubject::create(['classroom_term_id' => $classroomTerm->id, 'subject_id' => $tafsirSubject->id, 'assessment_method' => 'weighted', 'kkm' => 70, 'daily_weight' => 40, 'exam_weight' => 60]);
            $assignment = DiniyyahTeacherAssignment::create(['diniyyah_class_subject_id' => $classSubject->id, 'teacher_id' => $teacher->id, 'assignment_role' => 'primary']);

            DiniyyahTeachingSchedule::create([
                'diniyyah_teacher_assignment_id' => $assignment->id,
                'class_session_id' => $tafsirSession->id,
                'day_of_week' => 4, // Kamis
            ]);
            $assignments[] = $assignment;
        }

        return $assignments;
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
