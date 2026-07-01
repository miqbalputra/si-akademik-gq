<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\TahfidzHalaqah;
use App\Models\TahfidzHalaqahMember;
use App\Models\TahfidzUasCategory;
use App\Models\TahfidzUasDay;
use App\Models\TahfidzUasScore;
use App\Models\TahfidzWeek;
use App\Models\TahfidzWeeklyScore;
use App\Models\Teacher;
use App\Models\TeacherRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TahfidzIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'wali_santri', 'kepala_sekolah', 'guru', 'kabag_tahfidz', 'kabag_diniyyah'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_guru_can_open_tahfidz_index(): void
    {
        [$guruUser, $halaqah] = $this->makeGuruWithHalaqah();

        $response = $this->actingAs($guruUser)->get(route('guru.tahfidz.index'));

        $response->assertOk();
        $response->assertSee($halaqah->name);
    }

    public function test_guru_can_open_tahfidz_show(): void
    {
        [$guruUser, $halaqah] = $this->makeGuruWithHalaqah();

        $response = $this->actingAs($guruUser)->get(route('guru.tahfidz.show', $halaqah));

        $response->assertOk();
    }

    public function test_guru_can_save_weekly_scores(): void
    {
        [$guruUser, $halaqah] = $this->makeGuruWithHalaqah();
        $member = $halaqah->activeMembers()->first();
        $week = TahfidzWeek::where('academic_term_id', $halaqah->academic_term_id)->first();

        $response = $this->actingAs($guruUser)->put(route('guru.tahfidz.update', $halaqah), [
            'scores' => [
                $member->id => [
                    $week->id => [
                        'surah_ayat' => 'An Naas: 1-5',
                        'sabaq_amount' => '3 Baris',
                        'score' => 85,
                        'category' => 'sabaq',
                        'notes' => 'Good',
                    ],
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tahfidz_weekly_scores', [
            'tahfidz_halaqah_member_id' => $member->id,
            'tahfidz_week_id' => $week->id,
            'surah_ayat' => 'An Naas: 1-5',
            'sabaq_baris' => 3,
            'score' => 85,
        ]);
    }

    public function test_guru_can_open_uas_input_page(): void
    {
        [$guruUser, $halaqah] = $this->makeGuruWithHalaqah();
        $this->makeUasSetup($halaqah);

        $response = $this->actingAs($guruUser)->get(route('guru.tahfidz.uas', $halaqah));

        $response->assertOk();
        $response->assertSee('UAS Tahfidz');
    }

    public function test_guru_can_save_uas_scores(): void
    {
        [$guruUser, $halaqah] = $this->makeGuruWithHalaqah();
        [$categories, $days] = $this->makeUasSetup($halaqah);
        $member = $halaqah->activeMembers()->first();

        $scores = [];
        foreach ($days as $day) {
            foreach ($categories as $cat) {
                $scores[$member->student_id][$day->id][$cat->id] = $cat->max_score - 2;
            }
        }

        $response = $this->actingAs($guruUser)->put(route('guru.tahfidz.uas.update', $halaqah), [
            'scores' => $scores,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tahfidz_uas_scores', [
            'student_id' => $member->student_id,
            'tahfidz_uas_day_id' => $days->first()->id,
            'tahfidz_uas_category_id' => $categories->first()->id,
        ]);

        // Verify UAS result was calculated
        $this->assertDatabaseHas('tahfidz_uas_results', [
            'student_id' => $member->student_id,
        ]);
    }

    public function test_wali_can_open_tahfidz_dashboard(): void
    {
        [$guruUser, $halaqah] = $this->makeGuruWithHalaqah();
        [$guardianUser, $student] = $this->makeGuardianWithStudent($halaqah);

        $response = $this->actingAs($guardianUser)->get(route('wali.tahfidz'));

        $response->assertOk();
        $response->assertSee($student->name);
    }

    public function test_wali_sees_multiple_children(): void
    {
        [$guruUser, $halaqah] = $this->makeGuruWithHalaqah();
        [$guardianUser, $student1] = $this->makeGuardianWithStudent($halaqah, 'Anak Pertama', 'TFD-W001');
        [$guardianUser2, $student2] = $this->makeGuardianWithStudent($halaqah, 'Anak Kedua', 'TFD-W002');

        // Connect both students to same guardian
        $guardian = $guardianUser->guardian;
        $guardian->students()->syncWithoutDetaching([
            $student2->id => ['relationship' => 'father', 'is_primary' => false, 'can_login' => true],
        ]);

        $response = $this->actingAs($guardianUser)->get(route('wali.tahfidz'));

        $response->assertOk();
        $response->assertSee('Anak Pertama');
        $response->assertSee('Anak Kedua');
    }

    public function test_kabag_tahfidz_can_access_filament(): void
    {
        $kabagUser = User::create([
            'name' => 'Kabag Tahfidz',
            'email' => 'kabag.tahfidz.test@example.com',
            'password' => bcrypt('password'),
        ]);
        $kabagUser->assignRole('kabag_tahfidz');

        $response = $this->actingAs($kabagUser)->get('/admin');

        $response->assertOk();
    }

    private function makeGuruWithHalaqah(): array
    {
        $school = School::create(['name' => 'Test School']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025/2026']);
        $term = AcademicTerm::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'semester' => 'ganjil']);

        $guruUser = User::create([
            'name' => 'Guru Tahfidz Test',
            'email' => 'guru.tahfidz.test@example.com',
            'password' => bcrypt('password'),
        ]);
        $guruUser->assignRole('guru');

        $teacher = Teacher::create([
            'user_id' => $guruUser->id,
            'name' => 'Guru Tahfidz Test',
            'gender' => 'male',
            'started_at' => '2025-07-01',
            'status' => 'active',
        ]);
        TeacherRole::create(['teacher_id' => $teacher->id, 'role_type' => 'tahfidz_teacher']);

        $halaqah = TahfidzHalaqah::create([
            'academic_term_id' => $term->id,
            'name' => 'Halaqah Test',
            'teacher_id' => $teacher->id,
            'status' => 'active',
        ]);

        $classroom = Classroom::create(['name' => 'M Test']);
        $classroomTerm = ClassroomTerm::create(['academic_term_id' => $term->id, 'classroom_id' => $classroom->id, 'name' => 'M Test']);

        for ($i = 1; $i <= 3; $i++) {
            $student = Student::create(['name' => "Santri $i", 'gender' => 'male', 'nis' => "STF-$i", 'status' => 'active']);
            $enrollment = ClassEnrollment::create(['academic_term_id' => $term->id, 'classroom_term_id' => $classroomTerm->id, 'student_id' => $student->id]);
            TahfidzHalaqahMember::create([
                'tahfidz_halaqah_id' => $halaqah->id,
                'student_id' => $student->id,
                'class_enrollment_id' => $enrollment->id,
                'joined_at' => '2025-07-14',
                'status' => 'active',
                'sort_order' => $i,
            ]);
        }

        // Create weeks
        for ($w = 1; $w <= 4; $w++) {
            TahfidzWeek::create([
                'academic_term_id' => $term->id,
                'week_number' => $w,
                'month_label' => 'Januari',
                'month_number' => 1,
                'date_label' => "Tgl $w",
                'is_active' => true,
            ]);
        }

        return [$guruUser, $halaqah];
    }

    private function makeUasSetup(TahfidzHalaqah $halaqah): array
    {
        $cats = [
            ['kelancaran', 'KELANCARAN', 30, 10],
            ['makhroj', 'MAKHROJ', 20, 20],
            ['tajwid', 'TAJWID', 30, 30],
            ['sifat', 'SIFAT', 20, 40],
        ];

        $categories = collect();
        foreach ($cats as [$code, $name, $max, $sort]) {
            $categories->push(TahfidzUasCategory::create([
                'academic_term_id' => $halaqah->academic_term_id,
                'code' => $code,
                'name' => $name,
                'max_score' => $max,
                'sort_order' => $sort,
                'is_active' => true,
            ]));
        }

        $days = collect();
        for ($d = 1; $d <= 2; $d++) {
            $days->push(TahfidzUasDay::create([
                'academic_term_id' => $halaqah->academic_term_id,
                'day_number' => $d,
                'label' => "Hari $d",
                'is_active' => true,
            ]));
        }

        return [$categories, $days];
    }

    private function makeGuardianWithStudent(TahfidzHalaqah $halaqah, string $name = 'Anak Wali', string $nis = 'TFD-W000'): array
    {
        $guardianUser = User::create([
            'name' => 'Wali ' . $name,
            'email' => 'wali.' . strtolower(str_replace(' ', '.', $nis)) . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $guardianUser->assignRole('wali_santri');

        $guardian = Guardian::create([
            'user_id' => $guardianUser->id,
            'name' => 'Bapak ' . $name,
            'nik' => '3173' . str_pad(random_int(0, 99999999999), 11, '0'),
            'gender' => 'male',
            'phone' => '081200000000',
            'status' => 'active',
        ]);

        $student = Student::create(['name' => $name, 'gender' => 'male', 'nis' => $nis, 'status' => 'active']);
        $guardian->students()->attach($student->id, ['relationship' => 'father', 'is_primary' => true, 'can_login' => true]);

        return [$guardianUser, $student];
    }
}