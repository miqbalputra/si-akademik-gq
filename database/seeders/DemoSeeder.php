<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahScore;
use App\Models\DiniyyahScoreComponent;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\Guardian;
use App\Models\HomeroomAssignment;
use App\Models\School;
use App\Models\SchoolEvent;
use App\Models\SchoolEventResponse;
use App\Models\SchoolHoliday;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\TeacherRole;
use App\Models\User;
use App\Services\DiniyyahAssessmentWorkflow;
use App\Services\DiniyyahLedgerGenerator;
use App\Services\DiniyyahLedgerWorkflow;
use App\Services\DiniyyahScoreCalculator;
use App\Services\ReportCardBulkWorkflow;
use App\Services\ReportCardGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(DiniyyahSubjectSeeder::class);

        DB::transaction(function () {
            $admin = $this->user('admin@example.com', 'Admin Utama', 'admin');
            $kabag = $this->user('kabag@example.com', 'Kabag Diniyyah Demo', 'kabag_diniyyah');
            $kepala = $this->user('kepala@example.com', 'Kepala Sekolah Demo', 'kepala_sekolah');
            $guruUser = $this->user('guru@example.com', 'Guru Diniyyah Demo', 'guru');
            $homeroomUser = $this->user('walikelas@example.com', 'Wali Kelas Demo', 'guru');
            $guardianUser = $this->user('wali@example.com', 'Wali Santri Demo', 'wali_santri');

            $school = School::updateOrCreate(
                ['name' => 'Griya Quran Demo'],
                [
                    'short_name' => 'GQ Demo',
                    'address' => 'Alamat demo sekolah',
                    'phone' => '081200000000',
                    'email' => 'demo@griyaquran.test',
                ],
            );

            $year = AcademicYear::updateOrCreate(
                ['school_id' => $school->id, 'name' => '2025/2026'],
                [
                    'hijri_label' => '1447-1448 H',
                    'gregorian_label' => '2025-2026 M',
                    'starts_at' => '2025-07-14',
                    'ends_at' => '2026-06-20',
                    'is_active' => true,
                ],
            );

            $term = AcademicTerm::updateOrCreate(
                ['academic_year_id' => $year->id, 'semester' => 'ganjil'],
                [
                    'name' => 'Semester Ganjil',
                    'starts_at' => '2025-07-14',
                    'ends_at' => '2025-12-20',
                    'is_active' => true,
                ],
            );

            $classroom = Classroom::updateOrCreate(
                ['name' => 'M3 Ikhwan Demo'],
                [
                    'level_name' => 'Mustawa 3',
                    'gender_group' => 'male',
                    'sort_order' => 30,
                    'is_active' => true,
                ],
            );

            $classroomTerm = ClassroomTerm::updateOrCreate(
                ['academic_term_id' => $term->id, 'classroom_id' => $classroom->id],
                [
                    'name' => 'M3 Ikhwan Demo',
                    'capacity' => 30,
                    'status' => 'active',
                ],
            );

            $holiday = SchoolHoliday::query()
                ->where('school_id', $school->id)
                ->whereDate('holiday_date', '2025-07-17')
                ->first();

            $holidayPayload = [
                'school_id' => $school->id,
                'academic_term_id' => $term->id,
                'holiday_date' => '2025-07-17',
                'title' => 'Libur Muharram Demo',
                'description' => 'Contoh libur sekolah yang ditentukan admin.',
            ];

            $holiday ? $holiday->update($holidayPayload) : SchoolHoliday::create($holidayPayload);

            $outdoorEvent = SchoolEvent::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'academic_term_id' => $term->id,
                    'title' => 'Outdoor Class Demo',
                ],
                [
                    'event_type' => 'outdoor',
                    'target_scope' => 'classes',
                    'starts_on' => '2025-07-22',
                    'ends_on' => '2025-07-22',
                    'location' => 'Kebun Edukasi Demo',
                    'description' => 'Kegiatan outdoor untuk simulasi event sekolah di dashboard guru dan wali.',
                    'show_to_teachers' => true,
                    'show_to_guardians' => true,
                ],
            );
            $outdoorEvent->targetClassroomTerms()->sync([$classroomTerm->id]);

            SchoolEvent::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'academic_term_id' => $term->id,
                    'title' => 'Ujian Mustawa 3 Ikhwan Demo',
                ],
                [
                    'event_type' => 'exam',
                    'target_scope' => 'level_gender',
                    'target_level_name' => 'Mustawa 3',
                    'target_gender_group' => 'male',
                    'starts_on' => '2025-07-24',
                    'ends_on' => '2025-07-24',
                    'location' => 'Kelas M3 Ikhwan Demo',
                    'description' => 'Contoh event ujian yang hanya tampil untuk jenjang dan kelompok tertentu.',
                    'show_to_teachers' => true,
                    'show_to_guardians' => true,
                ],
            );
            $diniyyahTeacher = $this->teacher($guruUser, 'Ustadz Demo Diniyyah', 'diniyyah_subject_teacher');
            $homeroomTeacher = $this->teacher($homeroomUser, 'Ustadz Demo Wali Kelas', 'homeroom_teacher');

            HomeroomAssignment::updateOrCreate(
                ['classroom_term_id' => $classroomTerm->id, 'teacher_id' => $homeroomTeacher->id],
                ['starts_at' => '2025-07-14', 'ends_at' => null],
            );

            $enrollments = collect([
                ['name' => 'Ahmad Demo', 'nis' => 'DEMO-M3-001'],
                ['name' => 'Bilal Demo', 'nis' => 'DEMO-M3-002'],
                ['name' => 'Hasan Demo', 'nis' => 'DEMO-M3-003'],
                ['name' => 'Husain Demo', 'nis' => 'DEMO-M3-004'],
                ['name' => 'Zaid Demo', 'nis' => 'DEMO-M3-005'],
            ])->map(function (array $studentData, int $index) use ($term, $classroomTerm) {
                $student = Student::updateOrCreate(
                    ['nis' => $studentData['nis']],
                    ['name' => $studentData['name'], 'gender' => 'male', 'status' => 'active'],
                );

                return ClassEnrollment::updateOrCreate(
                    ['academic_term_id' => $term->id, 'student_id' => $student->id],
                    [
                        'classroom_term_id' => $classroomTerm->id,
                        'roll_number' => $index + 1,
                        'status' => 'active',
                    ],
                );
            });

            $guardian = Guardian::updateOrCreate(
                ['nik' => '3173000000000001'],
                [
                    'user_id' => $guardianUser->id,
                    'name' => 'Bapak Demo',
                    'gender' => 'male',
                    'phone' => '081211110001',
                    'whatsapp' => '081211110001',
                    'email' => $guardianUser->email,
                    'address' => 'Alamat wali santri demo',
                    'status' => 'active',
                ],
            );
            $guardian->students()->syncWithoutDetaching([
                $enrollments->first()->student_id => [
                    'relationship' => 'father',
                    'is_primary' => true,
                    'can_login' => true,
                ],
            ]);
            SchoolEventResponse::updateOrCreate(
                [
                    'school_event_id' => $outdoorEvent->id,
                    'guardian_id' => $guardian->id,
                ],
                [
                    'attendance_status' => 'attending',
                    'notes' => 'Contoh respon wali untuk demo event sekolah.',
                    'responded_at' => now(),
                ],
            );

            $validatedSets = collect([
                ['code' => 'fiqih', 'title' => 'Fiqih Demo', 'sort_order' => 10],
                ['code' => 'akidah_akhlak', 'title' => 'Akidah Akhlak Demo', 'sort_order' => 20],
            ])->map(fn (array $payload) => $this->assessmentSet(
                $payload['code'],
                $payload['title'],
                $payload['sort_order'],
                $classroomTerm,
                $diniyyahTeacher,
                $admin,
                true,
            ));

            $this->assessmentSet(
                'bahasa_arab',
                'Bahasa Arab Latihan Input',
                30,
                $classroomTerm,
                $diniyyahTeacher,
                $admin,
                false,
            );

            $scoreCalculator = app(DiniyyahScoreCalculator::class);
            $workflow = app(DiniyyahAssessmentWorkflow::class);

            foreach ($validatedSets as $setIndex => $assessmentSet) {
                $assessmentSet->load('components');

                foreach ($enrollments as $studentIndex => $enrollment) {
                    foreach ($assessmentSet->components as $component) {
                        DiniyyahScore::updateOrCreate(
                            [
                                'diniyyah_score_component_id' => $component->id,
                                'class_enrollment_id' => $enrollment->id,
                            ],
                            [
                                'diniyyah_assessment_set_id' => $assessmentSet->id,
                                'score' => $this->demoScore($setIndex, $studentIndex, $component->component_group),
                                'input_by' => $guruUser->id,
                                'input_at' => now(),
                                'status' => 'submitted',
                            ],
                        );
                    }

                    $scoreCalculator->calculate($assessmentSet, $enrollment);
                }

                if ($assessmentSet->status !== 'validated') {
                    $assessmentSet->update(['status' => 'submitted']);
                    $workflow->approve($assessmentSet, $kabag, 'Data demo sudah divalidasi.');
                }
            }

            $this->seedAttendances($term, $classroomTerm, $enrollments, $homeroomUser);

            $snapshot = $classroomTerm->diniyyahLedgerSnapshots()->first();

            if (! $snapshot || ! in_array($snapshot->status, ['locked', 'published'], true)) {
                $snapshot = app(DiniyyahLedgerGenerator::class)->generate($classroomTerm, $admin->id);
                app(DiniyyahLedgerWorkflow::class)->validate($snapshot, $kabag);
                app(DiniyyahLedgerWorkflow::class)->lock($snapshot->refresh(), $kabag);
            }

            $snapshot = $snapshot->refresh();
            $reportSummary = app(ReportCardBulkWorkflow::class)->summaryForSnapshot($snapshot);

            if ($reportSummary['missing'] > 0 || $reportSummary['total'] === 0) {
                app(ReportCardGenerator::class)->generateFromLedgerSnapshot($snapshot, $admin->id);
            }

            $bulkWorkflow = app(ReportCardBulkWorkflow::class);
            $reportSummary = $bulkWorkflow->summaryForSnapshot($snapshot);

            if ($reportSummary['draft'] > 0) {
                $bulkWorkflow->lockForSnapshot($snapshot, $admin);
            }

            $reportSummary = $bulkWorkflow->summaryForSnapshot($snapshot);

            if ($reportSummary['locked'] > 0) {
                $bulkWorkflow->publishForSnapshot($snapshot, $admin);
            }

            $kepala->touch();
        });
    }

    private function user(string $email, string $name, string $role): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
            ],
        );

        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user->assignRole($role);

        return $user;
    }

    private function teacher(User $user, string $name, string $roleType): Teacher
    {
        $teacher = Teacher::updateOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $name,
                'gender' => 'male',
                'email' => $user->email,
                'started_at' => '2025-07-01',
                'status' => 'active',
            ],
        );

        TeacherRole::updateOrCreate([
            'teacher_id' => $teacher->id,
            'role_type' => $roleType,
        ]);

        return $teacher;
    }

    private function assessmentSet(string $subjectCode, string $title, int $sortOrder, ClassroomTerm $classroomTerm, Teacher $teacher, User $admin, bool $appearsOnLedger): DiniyyahAssessmentSet
    {
        $subject = DiniyyahSubject::where('code', $subjectCode)->firstOrFail();
        $classSubject = DiniyyahClassSubject::updateOrCreate(
            ['classroom_term_id' => $classroomTerm->id, 'subject_id' => $subject->id],
            [
                'assessment_method' => 'weighted',
                'kkm' => 70,
                'daily_weight' => 40,
                'exam_weight' => 60,
                'appears_on_ledger' => $appearsOnLedger,
                'appears_on_report' => $appearsOnLedger,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        DiniyyahTeacherAssignment::updateOrCreate(
            ['diniyyah_class_subject_id' => $classSubject->id, 'teacher_id' => $teacher->id],
            [
                'assignment_role' => 'primary',
                'starts_at' => '2025-07-14',
                'ends_at' => null,
                'assigned_by' => $admin->id,
            ],
        );

        $assessmentSet = DiniyyahAssessmentSet::updateOrCreate(
            ['diniyyah_class_subject_id' => $classSubject->id, 'title' => $title],
            [
                'tested_material' => 'Materi demo semester ganjil',
                'assessment_method' => 'weighted',
                'kkm' => 70,
                'daily_weight' => 40,
                'exam_weight' => 60,
                'appears_on_ledger' => $appearsOnLedger,
                'appears_on_report' => $appearsOnLedger,
                'sort_order' => $sortOrder,
                'status' => $appearsOnLedger ? 'submitted' : 'active',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        foreach ([
            ['code' => 'keaktifan_presensi', 'name' => 'Keaktifan/Presensi', 'component_group' => 'daily', 'sort_order' => 10],
            ['code' => 'ulangan_harian_1', 'name' => 'Ulangan Harian 1', 'component_group' => 'daily', 'sort_order' => 20],
            ['code' => 'ulangan_harian_2', 'name' => 'Ulangan Harian 2', 'component_group' => 'daily', 'sort_order' => 30],
            ['code' => 'nilai_tugas', 'name' => 'Nilai Tugas', 'component_group' => 'daily', 'sort_order' => 40],
            ['code' => 'nilai_ujian', 'name' => 'Nilai Ujian', 'component_group' => 'exam', 'sort_order' => 50],
        ] as $component) {
            DiniyyahScoreComponent::updateOrCreate(
                ['diniyyah_assessment_set_id' => $assessmentSet->id, 'code' => $component['code']],
                $component + ['is_required' => true],
            );
        }

        return $assessmentSet;
    }

    private function demoScore(int $setIndex, int $studentIndex, string $componentGroup): int
    {
        $base = 82 + $setIndex * 2 - $studentIndex * 2;

        if ($componentGroup === 'exam') {
            return max($base + 4, 70);
        }

        return max($base, 70);
    }

    /** @param  Collection<int, ClassEnrollment>  $enrollments */
    private function seedAttendances(AcademicTerm $term, ClassroomTerm $classroomTerm, $enrollments, User $homeroomUser): void
    {
        $dates = [
            '2025-07-14' => [StudentAttendance::STATUS_PRESENT, StudentAttendance::STATUS_PRESENT, StudentAttendance::STATUS_SICK, StudentAttendance::STATUS_PRESENT, StudentAttendance::STATUS_PRESENT],
            '2025-07-15' => [StudentAttendance::STATUS_SICK, StudentAttendance::STATUS_PRESENT, StudentAttendance::STATUS_PRESENT, StudentAttendance::STATUS_PERMISSION, StudentAttendance::STATUS_PRESENT],
            '2025-07-16' => [StudentAttendance::STATUS_PERMISSION, StudentAttendance::STATUS_PRESENT, StudentAttendance::STATUS_PRESENT, StudentAttendance::STATUS_PRESENT, StudentAttendance::STATUS_ABSENT],
            '2025-07-17' => [StudentAttendance::STATUS_PRESENT, StudentAttendance::STATUS_ABSENT, StudentAttendance::STATUS_PRESENT, StudentAttendance::STATUS_PRESENT, StudentAttendance::STATUS_PRESENT],
            '2025-07-18' => [StudentAttendance::STATUS_HOLIDAY, StudentAttendance::STATUS_HOLIDAY, StudentAttendance::STATUS_HOLIDAY, StudentAttendance::STATUS_HOLIDAY, StudentAttendance::STATUS_HOLIDAY],
        ];

        foreach ($dates as $date => $statuses) {
            foreach ($enrollments->values() as $index => $enrollment) {
                $attendance = StudentAttendance::query()
                    ->where('class_enrollment_id', $enrollment->id)
                    ->whereDate('attendance_date', $date)
                    ->first();

                $payload = [
                    'academic_term_id' => $term->id,
                    'classroom_term_id' => $classroomTerm->id,
                    'student_id' => $enrollment->student_id,
                    'attendance_date' => $date,
                    'status' => $statuses[$index],
                    'input_by' => $homeroomUser->id,
                ];

                $attendance ? $attendance->update($payload) : StudentAttendance::create([
                    'class_enrollment_id' => $enrollment->id,
                    ...$payload,
                ]);
            }
        }
    }
}
