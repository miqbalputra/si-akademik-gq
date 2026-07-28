<?php

use App\Models\AcademicTerm;
use App\Models\ClassSession;
use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed "Jadwal Mengajar" mapel REGULER dari jadwal_mustawa_ikhwan.md &
 * jadwal_mustawa_akhwat_2026_2027.md (Senin–Jumat, Sesi 1/2 + Tafsir Kamis).
 *
 * Berbeda dengan 000006 (Tafsir yang gurunya diketahui), mapel reguler di file
 * jadwal TIDAK mencantumkan nama guru. Jadi migration ini DATA-DRIVEN: ia membangun
 * matriks (gender × level × hari × sesi → nama mapel) dari file, lalu saat jalan
 * mencari assignment yang SUDAH ADA di prod untuk (classroom_term, subject) itu.
 * Setiap assignment yang cocok di-link ke slot (day_of_week, class_session).
 *
 * Aturan:
 * - "Fiqih Ibadah" di file → cocokkan subject code `fiqih_ibadah` (Ikhwan) ATAU
 *   `fiqih` (Akhwat) — prod memakai dua code berbeda per gender.
 * - Tafsir Kamis M2-M6: sudah ditangani 000006; matrix tetap memuatnya (idempoten,
 *   firstOrCreate → no-op bila sudah ada).
 * - Slot Tahfidz & Tafsir Jumat M1 di-skip (Tahfidz modul terpisah; Tafsir hanya
 *   Kamis M2-M6 sesuai scope user).
 * - Bila (classroom_term, subject) belum punya class_subject/assignment → slot
 *   di-skip (guru belum di-assign). Daftar skip dilaporkan via query verifikasi.
 * - Multi-teacher (satu class_subject punya >1 assignment): SEMUA assignment di-link
 *   ke slot itu (jadwal per-guru). Ditandai untuk review user.
 *
 * Idempoten (firstOrCreate). No-op bila term aktif / guru / classroom_term Mustawa
 * tak ada (mis. test DB). Tidak menyentuh akun/profil existing.
 */
return new class extends Migration
{
    private const ACADEMIC_TERM_NAME = 'Tahun Ajaran 2026/2027 Ganjil';

    /** day name (matrix key) → day_of_week (1=Senin..7=Minggu). */
    private const DAY_MAP = [
        'senin' => 1,
        'selasa' => 2,
        'rabu' => 3,
        'kamis' => 4,
        'jumat' => 5,
    ];

    /** Nama mapel di file jadwal → kode subject yang cocok (urut: prioritaskan kode utama). */
    private const NAME_TO_CODES = [
        'Aqidah Akhlaq' => ['akidah_akhlak'],
        'Bahasa Arab' => ['bahasa_arab'],
        'Fiqih Ibadah' => ['fiqih_ibadah', 'fiqih'],
        'Khat' => ['khat'],
        'Praktek Ibadah' => ['praktik_ibadah'],
        'Tajwid' => ['tajwid'],
        'Imla\'' => ['imla'],
        'Tafsir Al Quran' => ['tafsir'],
    ];

    public function up(): void
    {
        // Pastikan subject Tajwid & Imla' ada (idempoten; di prod biasanya sudah).
        DiniyyahSubject::firstOrCreate(['code' => 'tajwid'], ['name' => 'Tajwid', 'default_assessment_method' => 'weighted', 'sort_order' => 80, 'is_active' => true]);
        DiniyyahSubject::firstOrCreate(['code' => 'imla'], ['name' => "Imla'", 'default_assessment_method' => 'weighted', 'sort_order' => 85, 'is_active' => true]);

        $term = AcademicTerm::where('name', self::ACADEMIC_TERM_NAME)->first();
        if (! $term) {
            return; // bukan env prod
        }

        $sessions = $this->sessionMap();
        if (! isset($sessions['1']) || ! isset($sessions['2']) || ! isset($sessions['tafsir'])) {
            return; // class_sessions belum lengkap
        }

        $matrix = $this->jadwalMatrix();

        $created = 0;
        $existed = 0;
        $skippedNoClassroomTerm = 0;
        $skippedNoAssignment = [];
        $multiTeacher = [];

        foreach ($matrix as $gender => $days) {
            foreach ($days as $dayName => $sessions_) {
                $dow = self::DAY_MAP[$dayName];
                foreach ($sessions_ as $sessionName => $levels) {
                    $session = $sessions[$sessionName] ?? null;
                    if (! $session) {
                        continue;
                    }
                    foreach ($levels as $level => $subjectName) {
                        $codes = self::NAME_TO_CODES[$subjectName] ?? null;
                        if (! $codes) {
                            continue; // Tahfidz / tidak dikenal → skip
                        }

                        $classroomName = 'Mustawa '.$level.' '.ucfirst($gender);
                        $classroom = Classroom::where('name', $classroomName)->first();
                        if (! $classroom) {
                            $skippedNoClassroomTerm++;
                            continue;
                        }
                        $classroomTerm = ClassroomTerm::where('academic_term_id', $term->id)
                            ->where('classroom_id', $classroom->id)
                            ->first();
                        if (! $classroomTerm) {
                            $skippedNoClassroomTerm++;
                            continue;
                        }

                        $classSubjects = DiniyyahClassSubject::where('classroom_term_id', $classroomTerm->id)
                            ->whereIn('subject_id', DiniyyahSubject::whereIn('code', $codes)->pluck('id'))
                            ->get();

                        if ($classSubjects->isEmpty()) {
                            $skippedNoAssignment[] = $classroomName.' / '.$subjectName.' ('.$dayName.' sesi '.$sessionName.')';
                            continue;
                        }

                        foreach ($classSubjects as $classSubject) {
                            $assignments = DiniyyahTeacherAssignment::where('diniyyah_class_subject_id', $classSubject->id)->get();
                            if ($assignments->isEmpty()) {
                                $skippedNoAssignment[] = $classroomName.' / '.$subjectName.' ('.$dayName.' sesi '.$sessionName.')';
                                continue;
                            }
                            if ($assignments->count() > 1) {
                                $multiTeacher[$classroomName.' / '.$subjectName] = $assignments->count();
                            }
                            foreach ($assignments as $assignment) {
                                $schedule = DiniyyahTeachingSchedule::firstOrCreate(
                                    [
                                        'diniyyah_teacher_assignment_id' => $assignment->id,
                                        'day_of_week' => $dow,
                                        'class_session_id' => $session->id,
                                    ],
                                );
                                $schedule->wasRecentlyCreated ? $created++ : $existed++;
                            }
                        }
                    }
                }
            }
        }

        echo "[000007] Jadwal Mengajar reguler: dibuat {$created} baru, {$existed} sudah ada, ".
            count($skippedNoAssignment)." slot tanpa assignment, {$skippedNoClassroomTerm} slot tanpa classroom_term.\n";
        if ($multiTeacher) {
            echo "[000007] Multi-teacher (review manual): ";
            $parts = [];
            foreach ($multiTeacher as $label => $n) {
                $parts[] = $label.' ('.$n.' guru)';
            }
            echo implode('; ', $parts).".\n";
        }
        if ($skippedNoAssignment) {
            echo "[000007] Slot tanpa assignment (perlu set guru via admin):\n";
            foreach (array_slice(array_unique($skippedNoAssignment), 0, 200) as $s) {
                echo "  - {$s}\n";
            }
        }
    }

    public function down(): void
    {
        // Data migration. Rollback menghapus teaching_schedule sesi reguler ('1','2')
        // yang dibuat migration ini. Tafsir (sesi 'tafsir') milik 000006 — dibiarkan.
        $sessionIds = ClassSession::whereIn('session_name', ['1', '2'])->pluck('id');
        if ($sessionIds->isNotEmpty()) {
            DiniyyahTeachingSchedule::whereIn('class_session_id', $sessionIds)->delete();
        }
    }

    /**
     * @return array<string, ClassSession> session_name → model
     */
    private function sessionMap(): array
    {
        return ClassSession::whereIn('session_name', ['1', '2', 'tafsir'])
            ->get()
            ->keyBy('session_name')
            ->all();
    }

    /**
     * Matriks jadwal: [gender => [day => [session => [level => subjectName]]]].
     * Sumber: jadwal_mustawa_ikhwan.md & jadwal_mustawa_akhwat_2026_2027.md.
     * Slot Tahfidz & Tafsir Jumat M1 tidak dimuat (di-skip sesuai scope).
     */
    private function jadwalMatrix(): array
    {
        $ikhwan = [
            'senin' => [
                '1' => [1 => 'Khat', 2 => 'Aqidah Akhlaq', 3 => 'Praktek Ibadah', 4 => 'Bahasa Arab', 5 => 'Aqidah Akhlaq', 6 => 'Imla\''],
                '2' => [1 => 'Fiqih Ibadah', 2 => 'Khat', 3 => 'Aqidah Akhlaq', 4 => 'Praktek Ibadah', 5 => 'Khat', 6 => 'Aqidah Akhlaq'],
            ],
            'selasa' => [
                '1' => [1 => 'Bahasa Arab', 2 => 'Aqidah Akhlaq', 3 => 'Aqidah Akhlaq', 4 => 'Bahasa Arab', 5 => 'Aqidah Akhlaq', 6 => 'Fiqih Ibadah'],
                '2' => [1 => 'Fiqih Ibadah', 2 => 'Praktek Ibadah', 3 => 'Khat', 4 => 'Aqidah Akhlaq', 5 => 'Tajwid', 6 => 'Aqidah Akhlaq'],
            ],
            'rabu' => [
                '1' => [1 => 'Aqidah Akhlaq', 2 => 'Bahasa Arab', 3 => 'Fiqih Ibadah', 4 => 'Khat', 5 => 'Fiqih Ibadah', 6 => 'Bahasa Arab'],
                '2' => [1 => 'Bahasa Arab', 2 => 'Fiqih Ibadah', 3 => 'Bahasa Arab', 4 => 'Aqidah Akhlaq', 5 => 'Tajwid', 6 => 'Bahasa Arab'],
            ],
            'kamis' => [
                'tafsir' => [2 => 'Tafsir Al Quran', 3 => 'Tafsir Al Quran', 4 => 'Tafsir Al Quran', 5 => 'Tafsir Al Quran', 6 => 'Tafsir Al Quran'],
                '1' => [1 => 'Aqidah Akhlaq', 2 => 'Fiqih Ibadah', 3 => 'Fiqih Ibadah', 4 => 'Tajwid', 5 => 'Bahasa Arab', 6 => 'Fiqih Ibadah'],
                '2' => [1 => 'Praktek Ibadah', 2 => 'Bahasa Arab', 3 => 'Bahasa Arab', 4 => 'Fiqih Ibadah', 5 => 'Fiqih Ibadah', 6 => 'Tajwid'],
            ],
            'jumat' => [
                '1' => [1 => 'Khat', 2 => 'Khat', 3 => 'Praktek Ibadah', 4 => 'Tajwid', 5 => 'Bahasa Arab', 6 => 'Praktek Ibadah'],
                '2' => [2 => 'Praktek Ibadah', 3 => 'Khat', 4 => 'Fiqih Ibadah', 5 => 'Praktek Ibadah', 6 => 'Tajwid'], // M1=Tafsir (skip scope)
            ],
        ];

        $akhwat = [
            'senin' => [
                '1' => [1 => 'Fiqih Ibadah', 2 => 'Bahasa Arab', 3 => 'Praktek Ibadah', 4 => 'Praktek Ibadah', 5 => 'Praktek Ibadah', 6 => 'Bahasa Arab'],
                '2' => [1 => 'Bahasa Arab', 2 => 'Fiqih Ibadah', 3 => 'Aqidah Akhlaq', 4 => 'Fiqih Ibadah', 5 => 'Fiqih Ibadah', 6 => 'Aqidah Akhlaq'],
            ],
            'selasa' => [
                '1' => [1 => 'Bahasa Arab', 2 => 'Bahasa Arab', 3 => 'Bahasa Arab', 4 => 'Tajwid', 5 => 'Fiqih Ibadah', 6 => 'Imla\''],
                '2' => [1 => 'Fiqih Ibadah', 2 => 'Fiqih Ibadah', 3 => 'Aqidah Akhlaq', 4 => 'Fiqih Ibadah', 5 => 'Tajwid', 6 => 'Aqidah Akhlaq'],
            ],
            'rabu' => [
                '1' => [1 => 'Khat', 2 => 'Aqidah Akhlaq', 3 => 'Khat', 4 => 'Tajwid', 5 => 'Aqidah Akhlaq', 6 => 'Fiqih Ibadah'],
                '2' => [1 => 'Aqidah Akhlaq', 2 => 'Praktek Ibadah', 3 => 'Fiqih Ibadah', 4 => 'Bahasa Arab', 5 => 'Tajwid', 6 => 'Praktek Ibadah'],
            ],
            'kamis' => [
                'tafsir' => [2 => 'Tafsir Al Quran', 3 => 'Tafsir Al Quran', 4 => 'Tafsir Al Quran', 5 => 'Tafsir Al Quran', 6 => 'Tafsir Al Quran'],
                '1' => [1 => 'Praktek Ibadah', 2 => 'Aqidah Akhlaq', 3 => 'Praktek Ibadah', 4 => 'Khat', 5 => 'Aqidah Akhlaq', 6 => 'Tajwid'],
                '2' => [1 => 'Aqidah Akhlaq', 2 => 'Khat', 3 => 'Fiqih Ibadah', 4 => 'Aqidah Akhlaq', 5 => 'Bahasa Arab', 6 => 'Tajwid'],
            ],
            'jumat' => [
                '1' => [1 => 'Khat', 2 => 'Praktek Ibadah', 3 => 'Khat', 4 => 'Aqidah Akhlaq', 5 => 'Khat', 6 => 'Bahasa Arab'],
                '2' => [2 => 'Khat', 3 => 'Praktek Ibadah', 4 => 'Bahasa Arab', 5 => 'Bahasa Arab', 6 => 'Fiqih Ibadah'], // M1=Tafsir (skip scope)
            ],
        ];

        return ['ikhwan' => $ikhwan, 'akhwat' => $akhwat];
    }
};