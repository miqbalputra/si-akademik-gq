<?php

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentSet;
use App\Models\Guardian;
use App\Models\HomeroomAssignment;
use App\Models\PanelNotification;
use App\Models\SchoolEvent;
use App\Models\Student;
use App\Models\TahfidzHalaqah;
use App\Models\Teacher;
use App\Models\TasmiExaminerAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pusat pengiriman notifikasi untuk seluruh aplikasi.
 *
 * Satu-satunya tempat yang menulis ke tabel `panel_notifications`. Semua
 * observer / workflow service memanggil method di sini (jangan langsung
 * PanelNotification::create()) supaya:
 *  - Anti-spam (batching per 10 menit per user+type+link) konsisten.
 *  - Resolusi audiens (wali kelas, wali santri, guru pemilik, kabag) terpusat.
 *  - Notifikasi broadcast-by-role (user_id NULL + audience_role) didukung.
 *
 * Cara pakai dari observer/service:
 *   app(NotificationDispatcher::class)
 *       ->toHomeroomTeacher($classroomTermId, $title, $body, $type, $linkUrl)
 *       ->toGuardiansOfStudent($studentId, ...)
 *       ->toRole('kabag_diniyyah', ...)
 *       ->toUser($userId, ...)
 *
 * Atau langsung via dispatchToUsers(array $userIds, ...) bila audiens sudah
 * di-resolve manual. Batching otomatis berlaku untuk semua method.
 */
class NotificationDispatcher
{
    /** Window batching dalam detik (10 menit). */
    public const BATCH_WINDOW_SECONDS = 600;

    /**
     * Kirim notifikasi ke daftar user ID spesifik.
     * Batching: bila ada notif dengan (user, type, link) yang sama dalam
     * 10 menit terakhir dan belum dibaca/archived → increment batch_count
     * dan update body, alih2 insert baris baru.
     *
     * @param  array<int>  $userIds
     */
    public function dispatchToUsers(
        array $userIds,
        string $title,
        string $body,
        string $type,
        string $linkUrl,
        string $severity = 'info',
    ): void {
        if (empty($userIds)) {
            return;
        }
        $userIds = array_values(array_unique(array_filter($userIds)));
        if (empty($userIds)) {
            return;
        }

        $window = (int) floor(now()->timestamp / self::BATCH_WINDOW_SECONDS);
        $now = now();

        foreach ($userIds as $userId) {
            $batchKey = "{$userId}:{$type}:{$linkUrl}:{$window}";

            // Cari notif existing di window ini yang belum dibaca/archived.
            $existing = PanelNotification::query()
                ->where('user_id', $userId)
                ->where('batch_key', $batchKey)
                ->where('status', 'unread')
                ->whereNull('archived_at')
                ->first();

            if ($existing) {
                $newCount = $existing->batch_count + 1;
                $existing->update([
                    'batch_count' => $newCount,
                    'body' => $this->buildBatchBody($body, $newCount, $type),
                    'updated_at' => $now,
                ]);
            } else {
                PanelNotification::create([
                    'user_id' => $userId,
                    'audience_role' => null,
                    'title' => $title,
                    'body' => $body,
                    'severity' => $severity,
                    'notification_type' => $type,
                    'link_url' => $linkUrl,
                    'status' => 'unread',
                    'read_at' => null,
                    'batch_key' => $batchKey,
                    'batch_count' => 1,
                    'archived_at' => null,
                ]);
            }
        }
    }

    /**
     * Broadcast notifikasi ke SEMUA user dengan role Spatie tertentu.
     * Dipakai untuk notifikasi yang ditujukan ke peran (bukan user spesifik),
     * mis. "kabag_diniyyah" untuk monitoring progress nilai.
     *
     * Disimpan sebagai 1 baris dengan user_id=NULL + audience_role=role,
     * sehingga tidak duplikat per user. Saat ditampilkan, query membaca
     * baris ini untuk semua user yang punya role tsb (lihat scopeRelevantFor).
     */
    public function dispatchToRole(
        string $role,
        string $title,
        string $body,
        string $type,
        string $linkUrl,
        string $severity = 'info',
    ): void {
        // Broadcast-by-role: tidak dibatch per user (cukup 1 baris global).
        // Tapi tetap cek window untuk hindari spam beruntun: bila ada baris
        // dengan (audience_role, type, link) di 10 menit terakhir → update.
        $window = (int) floor(now()->timestamp / self::BATCH_WINDOW_SECONDS);
        $batchKey = "role:{$role}:{$type}:{$linkUrl}:{$window}";

        $existing = PanelNotification::query()
            ->whereNull('user_id')
            ->where('audience_role', $role)
            ->where('batch_key', $batchKey)
            ->where('status', 'unread')
            ->whereNull('archived_at')
            ->first();

        if ($existing) {
            $newCount = $existing->batch_count + 1;
            $existing->update([
                'batch_count' => $newCount,
                'body' => $this->buildBatchBody($body, $newCount, $type),
                'updated_at' => now(),
            ]);
        } else {
            PanelNotification::create([
                'user_id' => null,
                'audience_role' => $role,
                'title' => $title,
                'body' => $body,
                'severity' => $severity,
                'notification_type' => $type,
                'link_url' => $linkUrl,
                'status' => 'unread',
                'read_at' => null,
                'batch_key' => $batchKey,
                'batch_count' => 1,
                'archived_at' => null,
            ]);
        }
    }

    public function dispatchToUser(
        int $userId,
        string $title,
        string $body,
        string $type,
        string $linkUrl,
        string $severity = 'info',
    ): void {
        $this->dispatchToUsers([$userId], $title, $body, $type, $linkUrl, $severity);
    }

    // ── Resolusi audiens ─────────────────────────────────────────────────

    /**
     * Kirim ke wali kelas (homeroom teacher) dari sebuah classroom_term.
     * Mencari HomeroomAssignment aktif (scope sama dengan User::canAccessAttendance).
     *
     * @return int jumlah penerima
     */
    public function dispatchToHomeroomTeacher(
        int $classroomTermId,
        string $title,
        string $body,
        string $type,
        string $linkUrl,
        string $severity = 'info',
    ): int {
        $today = now()->toDateString();
        $teacherUserIds = HomeroomAssignment::query()
            ->where('classroom_term_id', $classroomTermId)
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $today);
            })
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today);
            })
            ->with('teacher.user')
            ->get()
            ->map(fn ($a) => $a->teacher?->user_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->dispatchToUsers($teacherUserIds, $title, $body, $type, $linkUrl, $severity);

        return count($teacherUserIds);
    }

    /**
     * Kirim ke wali santri (orang tua) dari seorang santri.
     * Filter: hanya guardian dengan can_login=true (bisa login ke portal wali).
     *
     * @return int jumlah penerima
     */
    public function dispatchToGuardiansOfStudent(
        int $studentId,
        string $title,
        string $body,
        string $type,
        string $linkUrl,
        string $severity = 'info',
    ): int {
        $guardianUserIds = DB::table('student_guardians')
            ->join('guardians', 'guardians.id', '=', 'student_guardians.guardian_id')
            ->where('student_guardians.student_id', $studentId)
            ->where('student_guardians.can_login', true)
            ->whereNull('guardians.deleted_at')
            ->whereNotNull('guardians.user_id')
            ->pluck('guardians.user_id')
            ->unique()
            ->values()
            ->all();

        $this->dispatchToUsers($guardianUserIds, $title, $body, $type, $linkUrl, $severity);

        return count($guardianUserIds);
    }

    /**
     * Kirim ke guru pemilik (teacher) dari sebuah DiniyyahAssessmentSet.
     * Resolve: assessment_set → class_subject → teacher_assignments (aktif) → teacher.user.
     *
     * @return int jumlah penerima
     */
    public function dispatchToSubjectTeachers(
        int $assessmentSetId,
        string $title,
        string $body,
        string $type,
        string $linkUrl,
        string $severity = 'info',
    ): int {
        $set = DiniyyahAssessmentSet::with('classSubject.teacherAssignments.teacher.user')->find($assessmentSetId);
        if (! $set || ! $set->classSubject) {
            return 0;
        }
        $today = now()->toDateString();
        $userIds = $set->classSubject->teacherAssignments
            ->filter(function ($a) use ($today) {
                $startOk = $a->starts_at === null || $a->starts_at <= $today;
                $endOk = $a->ends_at === null || $a->ends_at >= $today;

                return $startOk && $endOk;
            })
            ->map(fn ($a) => $a->teacher?->user_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->dispatchToUsers($userIds, $title, $body, $type, $linkUrl, $severity);

        return count($userIds);
    }

    /**
     * Kirim ke guru pengampu + asisten dari sebuah halaqah tahfidz.
     *
     * @return int jumlah penerima
     */
    public function dispatchToHalaqahTeachers(
        int $halaqahId,
        string $title,
        string $body,
        string $type,
        string $linkUrl,
        string $severity = 'info',
    ): int {
        $halaqah = TahfidzHalaqah::with(['teacher.user', 'assistantTeacher.user'])->find($halaqahId);
        if (! $halaqah) {
            return 0;
        }
        $userIds = collect([$halaqah->teacher, $halaqah->assistantTeacher])
            ->map(fn ($t) => $t?->user_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->dispatchToUsers($userIds, $title, $body, $type, $linkUrl, $severity);

        return count($userIds);
    }

    /**
     * Kirim ke PJ Tasmi' yang menginput record tsb (sebagai konfirmasi receipt).
     */
    public function dispatchToExaminer(
        int $examinerTeacherId,
        string $title,
        string $body,
        string $type,
        string $linkUrl,
        string $severity = 'info',
    ): int {
        $userId = Teacher::find($examinerTeacherId)?->user_id;
        if (! $userId) {
            return 0;
        }
        $this->dispatchToUser($userId, $title, $body, $type, $linkUrl, $severity);

        return 1;
    }

    /**
     * Kirim ke guru yang baru saja ditugaskan sebagai PJ Tasmi'.
     */
    public function dispatchToTasmiExaminer(
        int $teacherId,
        string $title,
        string $body,
        string $type,
        string $linkUrl,
        string $severity = 'info',
    ): int {
        return $this->dispatchToExaminer($teacherId, $title, $body, $type, $linkUrl, $severity);
    }

    /**
     * Kirim ke semua guru yang mengajar di classroom_terms tertentu (untuk SchoolEvent).
     * Resolve via diniyyah_teacher_assignments + homeroom_assignments.
     *
     * @param  array<int>  $classroomTermIds
     * @return int jumlah penerima
     */
    public function dispatchToTeachersOfClassrooms(
        array $classroomTermIds,
        string $title,
        string $body,
        string $type,
        string $linkUrl,
        string $severity = 'info',
    ): int {
        if (empty($classroomTermIds)) {
            return 0;
        }
        // Guru diniyyah di kelas tsb.
        $diniyyahUserIds = DB::table('diniyyah_teacher_assignments')
            ->join('diniyyah_class_subjects', 'diniyyah_class_subjects.id', '=', 'diniyyah_teacher_assignments.diniyyah_class_subject_id')
            ->join('teachers', 'teachers.id', '=', 'diniyyah_teacher_assignments.teacher_id')
            ->whereIn('diniyyah_class_subjects.classroom_term_id', $classroomTermIds)
            ->whereNotNull('teachers.user_id')
            ->whereNull('teachers.deleted_at')
            ->pluck('teachers.user_id');

        // Wali kelas di kelas tsb.
        $today = now()->toDateString();
        $homeroomUserIds = HomeroomAssignment::query()
            ->whereIn('classroom_term_id', $classroomTermIds)
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $today);
            })
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today);
            })
            ->with('teacher.user')
            ->get()
            ->map(fn ($a) => $a->teacher?->user_id)
            ->filter();

        $userIds = $diniyyahUserIds->merge($homeroomUserIds)->unique()->values()->all();
        $this->dispatchToUsers($userIds, $title, $body, $type, $linkUrl, $severity);

        return count($userIds);
    }

    /**
     * Kirim ke wali santri yang punya anak di classroom_terms tertentu (untuk SchoolEvent).
     *
     * @param  array<int>  $classroomTermIds
     * @return int jumlah penerima
     */
    public function dispatchToGuardiansOfClassrooms(
        array $classroomTermIds,
        string $title,
        string $body,
        string $type,
        string $linkUrl,
        string $severity = 'info',
    ): int {
        if (empty($classroomTermIds)) {
            return 0;
        }
        $guardianUserIds = DB::table('class_enrollments')
            ->join('student_guardians', 'student_guardians.student_id', '=', 'class_enrollments.student_id')
            ->join('guardians', 'guardians.id', '=', 'student_guardians.guardian_id')
            ->whereIn('class_enrollments.classroom_term_id', $classroomTermIds)
            ->where('class_enrollments.status', 'active')
            ->where('student_guardians.can_login', true)
            ->whereNull('guardians.deleted_at')
            ->whereNotNull('guardians.user_id')
            ->pluck('guardians.user_id')
            ->unique()
            ->values()
            ->all();

        $this->dispatchToUsers($guardianUserIds, $title, $body, $type, $linkUrl, $severity);

        return count($guardianUserIds);
    }

    /**
     * Helper: bangun body notifikasi untuk batch (count > 1).
     * Format: "{original_body} (×{count} kali sebelumnya)" atau disesuaikan per type.
     */
    private function buildBatchBody(string $originalBody, int $count, string $type): string
    {
        if ($count <= 1) {
            return $originalBody;
        }

        // Untuk type tertentu, ganti body jadi format agregat.
        return match ($type) {
            'diniyyah_score_input' => "Ada {$count} input nilai baru yang perlu Anda tinjau.",
            'attendance_absent' => "Ada {$count} catatan ketidakhadiran santri baru.",
            'tahfidz_weekly' => "Ada {$count} input nilai tahfidz pekanan baru.",
            'tahfidz_uas' => "Ada {$count} input nilai UAS tahfidz baru.",
            'journal_created' => "Ada {$count} jurnal kelas baru diinput.",
            default => "{$originalBody} (×{$count} kali sebelumnya dalam 10 menit)",
        };
    }
}