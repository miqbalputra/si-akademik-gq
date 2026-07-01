<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\TahfidzHalaqah;
use App\Models\TahfidzHalaqahMember;
use App\Models\TahfidzMonthlyRecap;
use App\Models\TahfidzSemesterRecap;
use App\Models\TahfidzUasCategory;
use App\Models\TahfidzUasDay;
use App\Models\TahfidzUasResult;
use App\Models\TahfidzUasScore;
use App\Models\TahfidzWeek;
use App\Models\TahfidzWeeklyScore;
use App\Models\Teacher;
use App\Models\TeacherRole;
use App\Models\User;
use App\Services\TahfidzScoreCalculator;
use App\Services\TahfidzSabaqParser;
use App\Services\TahfidzUasCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TahfidzDemoSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::firstOrCreate(['name' => 'Griya Quran Demo']);
        $year = $school->academicYears()->firstOrCreate(['name' => '2025/2026'], [
            'hijri_label' => '1447-1448 H',
            'gregorian_label' => '2025-2026 M',
            'starts_at' => '2025-07-14',
            'ends_at' => '2026-06-20',
            'is_active' => true,
        ]);
        $term = \App\Models\AcademicTerm::firstOrCreate(['academic_year_id' => $year->id, 'semester' => 'ganjil'], [
            'name' => 'Semester Ganjil',
            'starts_at' => '2025-07-14',
            'ends_at' => '2025-12-20',
            'is_active' => true,
        ]);

        // Create tahfidz teacher
        $guruUser = User::firstOrCreate(['email' => 'guru.tahfidz@example.com'], [
            'name' => 'Ustadz Tahfidz Demo',
            'password' => Hash::make('password'),
        ]);
        $guruUser->assignRole('guru');

        $teacher = Teacher::firstOrCreate(['user_id' => $guruUser->id], [
            'name' => 'Ustadz Tahfidz Demo',
            'gender' => 'male',
            'email' => $guruUser->email,
            'started_at' => '2025-07-01',
            'status' => 'active',
        ]);
        TeacherRole::firstOrCreate(['teacher_id' => $teacher->id, 'role_type' => 'tahfidz_teacher']);

        // Create classroom for enrollment linking
        $classroom = Classroom::firstOrCreate(['name' => 'M1 Tahfidz Demo'], [
            'level_name' => 'Mustawa 1',
            'gender_group' => 'male',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $classroomTerm = ClassroomTerm::firstOrCreate([
            'academic_term_id' => $term->id,
            'classroom_id' => $classroom->id,
        ], ['name' => 'M1 Tahfidz Demo', 'status' => 'closed']);

        // Create halaqah
        $halaqah = TahfidzHalaqah::firstOrCreate([
            'academic_term_id' => $term->id,
            'name' => 'Halaqah Demo Ustadz Tahfidz',
        ], [
            'teacher_id' => $teacher->id,
            'status' => 'active',
            'created_by' => $guruUser->id,
        ]);

        // Create 5 demo students
        $studentData = [
            ['Ahmad Tahfidz', 'TFD-001'],
            ['Bilal Tahfidz', 'TFD-002'],
            ['Hasan Tahfidz', 'TFD-003'],
            ['Husain Tahfidz', 'TFD-004'],
            ['Zaid Tahfidz', 'TFD-005'],
        ];

        $members = collect();
        foreach ($studentData as $i => [$name, $nis]) {
            $student = Student::firstOrCreate(['nis' => $nis], [
                'name' => $name, 'gender' => 'male', 'status' => 'active',
            ]);

            $enrollment = ClassEnrollment::firstOrCreate([
                'academic_term_id' => $term->id,
                'student_id' => $student->id,
            ], [
                'classroom_term_id' => $classroomTerm->id,
                'roll_number' => $i + 1,
                'status' => 'active',
            ]);

            $member = TahfidzHalaqahMember::firstOrCreate([
                'tahfidz_halaqah_id' => $halaqah->id,
                'student_id' => $student->id,
            ], [
                'class_enrollment_id' => $enrollment->id,
                'joined_at' => '2025-07-14',
                'status' => 'active',
                'sort_order' => $i + 1,
            ]);
            $members->push($member);
        }

        // Create weeks (4 pekan for January, 4 for February — semester genap style demo)
        $weekData = [
            [1, 'Januari', 1, 'Tgl 5-9', '2025-07-14', '2025-07-18'],
            [2, 'Januari', 1, 'Tgl 12-16', '2025-07-21', '2025-07-25'],
            [3, 'Januari', 1, 'Tgl 19-23', '2025-07-28', '2025-08-01'],
            [4, 'Januari', 1, 'Tgl 26-30', '2025-08-04', '2025-08-08'],
            [5, 'Februari', 2, 'Tgl 2-6', '2025-08-11', '2025-08-15'],
            [6, 'Februari', 2, 'Tgl 9-13', '2025-08-18', '2025-08-22'],
            [7, 'Februari', 2, 'Tgl 16-20', '2025-08-25', '2025-08-29'],
            [8, 'Februari', 2, 'Tgl 23-27', '2025-09-01', '2025-09-05'],
        ];

        foreach ($weekData as [$num, $label, $month, $dateLabel, $start, $end]) {
            TahfidzWeek::firstOrCreate([
                'academic_term_id' => $term->id,
                'week_number' => $num,
            ], [
                'month_label' => $label,
                'month_number' => $month,
                'date_label' => $dateLabel,
                'starts_on' => $start,
                'ends_on' => $end,
                'is_active' => true,
            ]);
        }

        // Create weekly scores
        $parser = app(TahfidzSabaqParser::class);
        $calculator = app(TahfidzScoreCalculator::class);

        $surahData = [
            ['An Naas: 1-5', '3 Baris', 85],
            ['Al Falaq: 1-5', '3 Baris', 88],
            ['Al Ikhlas: 1-4', '2 Baris', 90],
            ['Al Lahab: 1-5', '3 Baris', 82],
            ['An Nasr: 1-3', '2 Baris', 87],
        ];

        $weeks = TahfidzWeek::where('academic_term_id', $term->id)->orderBy('week_number')->get();

        foreach ($members as $mi => $member) {
            foreach ($weeks as $wi => $week) {
                $surah = $surahData[($wi + $mi) % count($surahData)];

                TahfidzWeeklyScore::firstOrCreate([
                    'tahfidz_halaqah_member_id' => $member->id,
                    'tahfidz_week_id' => $week->id,
                ], [
                    'tahfidz_halaqah_id' => $halaqah->id,
                    'student_id' => $member->student_id,
                    'surah_ayat' => $surah[0],
                    'sabaq_amount' => $surah[1],
                    'sabaq_baris' => $parser->parseToBaris($surah[1]),
                    'score' => $surah[2] - $mi * 2,
                    'category' => 'sabaq',
                    'input_by' => $guruUser->id,
                    'input_at' => now(),
                    'status' => 'draft',
                ]);
            }

            // Calculate monthly recaps
            $calculator->recalculateMonthlyRecaps($member);

            // Add manzil data
            $recaps = TahfidzMonthlyRecap::where('tahfidz_halaqah_member_id', $member->id)->get();
            foreach ($recaps as $recap) {
                $recap->update([
                    'manzil_submitted' => 'Juz 30 2x',
                    'manzil_score' => 90 - $mi * 2,
                ]);
            }

            // Calculate semester recap
            $calculator->calculateSemesterRecap($member);
        }

        // Create UAS categories
        $uasCategories = [
            ['kelancaran', 'KELANCARAN', 30, 10],
            ['makhroj', 'MAKHROJ', 20, 20],
            ['tajwid', 'TAJWID', 30, 30],
            ['sifat', 'SIFAT', 20, 40],
        ];

        foreach ($uasCategories as [$code, $name, $max, $sort]) {
            TahfidzUasCategory::firstOrCreate([
                'academic_term_id' => $term->id,
                'code' => $code,
            ], [
                'name' => $name,
                'max_score' => $max,
                'sort_order' => $sort,
                'is_active' => true,
                'created_by' => $guruUser->id,
            ]);
        }

        // Create UAS days (3 days for demo)
        for ($d = 1; $d <= 3; $d++) {
            TahfidzUasDay::firstOrCreate([
                'academic_term_id' => $term->id,
                'day_number' => $d,
            ], [
                'label' => "Hari $d",
                'test_date' => "2025-12-1" . ($d - 1),
                'is_active' => true,
            ]);
        }

        // Create UAS scores
        $uasCalc = app(TahfidzUasCalculator::class);
        $cats = TahfidzUasCategory::where('academic_term_id', $term->id)->orderBy('sort_order')->get();
        $uasDays = TahfidzUasDay::where('academic_term_id', $term->id)->orderBy('day_number')->get();

        foreach ($members as $mi => $member) {
            $studentId = $member->student_id;

            foreach ($uasDays as $di => $day) {
                foreach ($cats as $ci => $cat) {
                    $baseScore = $cat->max_score - 2 - $mi;
                    $score = max($baseScore - $di, $cat->max_score - 8);

                    TahfidzUasScore::firstOrCreate([
                        'tahfidz_uas_day_id' => $day->id,
                        'tahfidz_uas_category_id' => $cat->id,
                        'student_id' => $studentId,
                    ], [
                        'academic_term_id' => $term->id,
                        'tahfidz_halaqah_id' => $halaqah->id,
                        'score' => $score,
                        'input_by' => $guruUser->id,
                    ]);
                }
            }

            // Calculate UAS result
            $result = $uasCalc->calculateForStudent($term->id, $studentId);
            $result->update(['juz_tested' => 'Juz 30']);
        }

        // Create wali santri for first student
        $waliUser = User::firstOrCreate(['email' => 'wali.tahfidz@example.com'], [
            'name' => 'Wali Santri Tahfidz Demo',
            'password' => Hash::make('password'),
        ]);
        $waliUser->assignRole('wali_santri');

        $guardian = Guardian::firstOrCreate(['nik' => '3173999999999999'], [
            'user_id' => $waliUser->id,
            'name' => 'Bapak Wali Tahfidz Demo',
            'gender' => 'male',
            'phone' => '081299990001',
            'email' => $waliUser->email,
            'status' => 'active',
        ]);
        $guardian->students()->syncWithoutDetaching([
            $members->first()->student_id => [
                'relationship' => 'father',
                'is_primary' => true,
                'can_login' => true,
            ],
        ]);
    }
}