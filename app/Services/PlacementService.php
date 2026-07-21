<?php

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\TahfidzHalaqahMember;
use Illuminate\Support\Facades\DB;

/**
 * Menangani persistensi penempatan santri ke kelas (`class_enrollments`) dan
 * ke halaqah Tahfidz (`tahfidz_halaqah_members`) untuk board drag-and-drop.
 *
 * Aturan data:
 *  - class_enrollments punya unique(academic_term_id, student_id) → satu kelas
 *    per periode per santri. "Belum ditempatkan" = baris ada tapi status=inactive.
 *  - tahfidz_halaqah_members: satu member aktif per periode per santri (aturan
 *    app). Pindah halaqah = soft-move (lama jadi status=moved + left_at) lalu
 *    buat member aktif baru, supaya riwayat keanggotaan utuh.
 */
class PlacementService
{
    /**
     * Tempatkan santri ke sebuah kelas pada periode tertentu.
     *
     * @param  int  $termId           ID academic_term.
     * @param  int  $studentId        ID santri.
     * @param  int|null  $classroomTermId  ID classroom_term tujuan; null = "belum ditempatkan".
     */
    public function assignClass(int $termId, int $studentId, ?int $classroomTermId): ?ClassEnrollment
    {
        return DB::transaction(function () use ($termId, $studentId, $classroomTermId): ?ClassEnrollment {
            // Selalu ambil baris yang ada (unique term+student) — jangan create duplikat.
            $enrollment = ClassEnrollment::query()
                ->where('academic_term_id', $termId)
                ->where('student_id', $studentId)
                ->lockForUpdate()
                ->first();

            if ($classroomTermId === null) {
                // Pindah ke "belum ditempatkan": nonaktifkan baris (tidak dihapus
                // karena unique constraint butuh satu baris per term+student).
                if ($enrollment && $enrollment->status === 'active') {
                    $enrollment->update(['status' => 'inactive']);
                }

                return $enrollment?->fresh();
            }

            if ($enrollment) {
                $enrollment->update([
                    'classroom_term_id' => $classroomTermId,
                    'status' => 'active',
                ]);

                return $enrollment->fresh();
            }

            return ClassEnrollment::create([
                'academic_term_id' => $termId,
                'classroom_term_id' => $classroomTermId,
                'student_id' => $studentId,
                'status' => 'active',
            ]);
        });
    }

    /**
     * Tempatkan santri ke sebuah halaqah pada periode tertentu (soft-move bila pindah).
     *
     * @param  int  $termId       ID academic_term.
     * @param  int  $studentId    ID santri.
     * @param  int|null  $halaqahId  ID tahfidz_halaqah tujuan; null = "belum dihalaqah" (keluarkan).
     */
    public function assignHalaqah(int $termId, int $studentId, ?int $halaqahId): ?TahfidzHalaqahMember
    {
        return DB::transaction(function () use ($termId, $studentId, $halaqahId): ?TahfidzHalaqahMember {
            $active = $this->findActiveMember($termId, $studentId);

            if ($halaqahId === null) {
                // Keluarkan dari halaqah aktif (soft-move), tanpa membuat baru.
                $this->softMove($active);

                return $active?->fresh();
            }

            // Sudah aktif di halaqah yang sama → tidak perlu apa-apa.
            if ($active && (int) $active->tahfidz_halaqah_id === $halaqahId) {
                return $active;
            }

            // Pindah halaqah: soft-move yang lama, lalu buat member aktif baru.
            $this->softMove($active);

            $classEnrollmentId = ClassEnrollment::query()
                ->where('academic_term_id', $termId)
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->value('id');

            return TahfidzHalaqahMember::create([
                'tahfidz_halaqah_id' => $halaqahId,
                'student_id' => $studentId,
                'class_enrollment_id' => $classEnrollmentId,
                'status' => 'active',
                'joined_at' => now()->toDateString(),
            ]);
        });
    }

    protected function findActiveMember(int $termId, int $studentId): ?TahfidzHalaqahMember
    {
        return TahfidzHalaqahMember::query()
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->whereHas('halaqah', fn ($q) => $q->where('academic_term_id', $termId))
            ->lockForUpdate()
            ->first();
    }

    protected function softMove(?TahfidzHalaqahMember $member): void
    {
        if (! $member) {
            return;
        }

        if ($member->status === 'active') {
            $member->update([
                'status' => 'moved',
                'left_at' => $member->left_at ?? now()->toDateString(),
            ]);
        }
    }
}