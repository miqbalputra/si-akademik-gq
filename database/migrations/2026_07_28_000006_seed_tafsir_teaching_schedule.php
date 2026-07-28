<?php

use App\Models\AcademicTerm;
use App\Models\ClassSession;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\Teacher;
use App\Support\SessionTimetable;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed "Jadwal Mengajar" Tafsir: Kamis 09:50-10:20, diajar serentak oleh
 * 1 Ustadz ke M2-M6 Ikhwan (Farhan Dhia Alauddin) dan 1 Ustadzah ke M2-M6
 * Akhwat (Mursyidah). Membentuk, per gender:
 *   - 5 DiniyyahClassSubject Tafsir (satu per classroom_term M2-M6)
 *   - 5 DiniyyahTeacherAssignment (guru → class_subject)
 *   - 5 DiniyyahTeachingSchedule (Kamis = day_of_week 4, class_session 'tafsir')
 *
 * Berjalan otomatis saat migrate (deploy Coolify). Idempoten (firstOrCreate di
 * tiap langkah) — aman dijalankan ulang. Di lingkungan yang tidak memiliki
 * tahun ajaran / guru / classroom_term Mustawa yang dimaksud (mis. test DB),
 * migration ini no-op (tidak membuat apa-apa).
 *
 * Tidak menyentuh akun user/profil existing, tidak reset password.
 */
return new class extends Migration
{
    /** Nama tahun ajaran aktif di prod (lihat hasil psql query 1). */
    private const ACADEMIC_TERM_NAME = 'Tahun Ajaran 2026/2027 Ganjil';

    /** Guru Tafsir Ikhwan (teacher.name di prod). */
    private const IKHWAN_TEACHER_NAME = 'Farhan Dhia Alauddin';

    /** Guru Tafsir Akhwat (teacher.name di prod). */
    private const AKHWAT_TEACHER_NAME = 'Mursyidah';

    /** Kamis = day_of_week 4 (1=Senin..7=Minggu). */
    private const KAMIS = 4;

    public function up(): void
    {
        $tafsirSubject = DiniyyahSubject::firstOrCreate(
            ['code' => 'tafsir'],
            [
                'name' => 'Tafsir Al Quran',
                'default_assessment_method' => 'weighted',
                'sort_order' => 70,
                'is_active' => true,
            ],
        );

        $tafsirSession = ClassSession::firstOrCreate(
            ['session_name' => 'tafsir'],
            ['is_break' => false],
        );

        $term = AcademicTerm::where('name', self::ACADEMIC_TERM_NAME)->first();
        $ikhwanTeacher = Teacher::where('name', self::IKHWAN_TEACHER_NAME)->first();
        $akhwatTeacher = Teacher::where('name', self::AKHWAT_TEACHER_NAME)->first();

        // Bila prasyarat prod belum ada (mis. test env), tidak membuat apa-apa.
        if (! $term || ! $ikhwanTeacher || ! $akhwatTeacher) {
            return;
        }

        [$ikhwanTerms, $akhwatTerms] = $this->tafsirClassroomTerms($term->id);

        $this->seedForGender($ikhwanTerms, $ikhwanTeacher->id, $tafsirSubject->id, $tafsirSession->id);
        $this->seedForGender($akhwatTerms, $akhwatTeacher->id, $tafsirSubject->id, $tafsirSession->id);
    }

    public function down(): void
    {
        // Data migration: rollback hanya menghapus teaching_schedule Tafsir
        // (aman, tidak ada FK ke jurnal). DiniyyahClassSubject/assignment sengaja
        // dibiarkan karena bisa sudah memiliki jurnal — menghapusnya cascade ke
        // jurnal guru. Hapus manual via admin bila benar-benar perlu.
        $tafsirSession = ClassSession::where('session_name', 'tafsir')->first();
        if (! $tafsirSession) {
            return;
        }

        DiniyyahTeachingSchedule::where('class_session_id', $tafsirSession->id)
            ->where('day_of_week', self::KAMIS)
            ->delete();
    }

    /**
     * Kembalikan classroom_term Mustawa M2-M6 (level >= 2) untuk term aktif,
     * dipisah per gender. M1 (level 1) dikecualikan karena Kamis 09:50-nya
     * Tahfidz, bukan Tafsir.
     *
     * @return array{0: \App\Models\ClassroomTerm[], 1: \App\Models\ClassroomTerm[]}
     */
    private function tafsirClassroomTerms(int $termId): array
    {
        $ikhwan = [];
        $akhwat = [];

        $terms = ClassroomTerm::with('classroom')
            ->where('academic_term_id', $termId)
            ->get();

        foreach ($terms as $classroomTerm) {
            $parsed = $classroomTerm->classroom
                ? SessionTimetable::parseClassroom($classroomTerm->classroom)
                : null;
            if (! $parsed) {
                continue;
            }

            [$gender, $level] = $parsed;
            if ($level < 2) {
                continue;
            }

            if ($gender === 'ikhwan') {
                $ikhwan[] = $classroomTerm;
            } elseif ($gender === 'akhwat') {
                $akhwat[] = $classroomTerm;
            }
        }

        return [$ikhwan, $akhwat];
    }

    /**
     * @param  \App\Models\ClassroomTerm[]  $classroomTerms
     */
    private function seedForGender(array $classroomTerms, int $teacherId, int $subjectId, int $tafsirSessionId): void
    {
        foreach ($classroomTerms as $classroomTerm) {
            $classSubject = DiniyyahClassSubject::firstOrCreate(
                [
                    'classroom_term_id' => $classroomTerm->id,
                    'subject_id' => $subjectId,
                ],
                [
                    'assessment_method' => 'weighted',
                    'kkm' => 70,
                    'daily_weight' => 40,
                    'exam_weight' => 60,
                    'appears_on_ledger' => true,
                    'appears_on_report' => true,
                    'sort_order' => 70,
                    'is_active' => true,
                ],
            );

            $assignment = DiniyyahTeacherAssignment::firstOrCreate(
                [
                    'diniyyah_class_subject_id' => $classSubject->id,
                    'teacher_id' => $teacherId,
                ],
                ['assignment_role' => 'primary'],
            );

            DiniyyahTeachingSchedule::firstOrCreate(
                [
                    'diniyyah_teacher_assignment_id' => $assignment->id,
                    'day_of_week' => self::KAMIS,
                    'class_session_id' => $tafsirSessionId,
                ],
            );
        }
    }
};