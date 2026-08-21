<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ClassroomTerm;
use App\Models\Teacher;
use App\Models\TasmiExaminerAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TasmiService
{
    /**
     * Dapatkan penugasan PJ Tasmi' aktif untuk guru pada periode aktif.
     * Mengembalikan null bila guru tidak ditugaskan atau tidak punya teacher.
     */
    public function activeExaminerAssignment(Teacher $teacher): ?TasmiExaminerAssignment
    {
        return TasmiExaminerAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->where('status', 'active')
            ->whereHas('academicTerm', fn (Builder $q) => $q->where('is_active', true))
            ->first();
    }

    /**
     * Gender scope untuk ujian tasmi': ustadz (male) hanya menguji kelas ikhwan,
     * ustadzah (female) hanya menguji kelas akhwat. Aturan ini diturunkan dari
     * gender guru, BUKAN dari field di penugasan.
     */
    public function expectedGenderScope(Teacher $teacher): ?string
    {
        return match ($teacher->gender) {
            'male' => 'male',
            'female' => 'female',
            default => null,
        };
    }

    /**
     * Dapatkan daftar classroom_terms periode aktif yang sesuai gender guru PJ Tasmi'.
     * Kelas 'mixed' sengaja DIKECUALIKAN dari tasmi' (tidak ada gender pasti).
     */
    public function eligibleClassroomTerms(Teacher $teacher): Collection
    {
        $assignment = $this->activeExaminerAssignment($teacher);
        if (! $assignment) {
            return collect();
        }

        $genderScope = $this->expectedGenderScope($teacher);
        if ($genderScope === null) {
            return collect();
        }

        return ClassroomTerm::query()
            ->with(['classroom', 'academicTerm'])
            ->where('academic_term_id', $assignment->academic_term_id)
            ->where('status', 'active')
            ->whereHas('classroom', function (Builder $q) use ($genderScope) {
                $q->where('gender_group', $genderScope)
                    ->where('is_active', true);
            })
            ->orderByRaw('(SELECT sort_order FROM classrooms WHERE classrooms.id = classroom_terms.classroom_id)')
            ->get();
    }

    /**
     * Dapatkan active academic term.
     */
    public function activeAcademicTerm(): ?AcademicTerm
    {
        return AcademicTerm::where('is_active', true)->first();
    }

    /**
     * Konversi tanggal Masehi ke label Hijriyah Indonesia menggunakan ICU.
     * Tanggal diperlakukan sebagai awal hari WIB agar tidak bergeser pada
     * browser atau server yang menggunakan zona waktu lain.
     */
    public function hijriDateFor(string|Carbon $date): ?string
    {
        if (! class_exists(\IntlDateFormatter::class)) {
            return null;
        }

        $wibDate = $date instanceof Carbon
            ? $date->copy()->setTimezone('Asia/Jakarta')->startOfDay()
            : Carbon::parse($date, 'Asia/Jakarta')->startOfDay();
        $formatter = new \IntlDateFormatter(
            'id_ID@calendar=islamic',
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            'Asia/Jakarta',
            \IntlDateFormatter::TRADITIONAL,
            'd MMMM y G',
        );
        $formatted = $formatter->format($wibDate->getTimestamp());

        return is_string($formatted) ? $formatted : null;
    }
}
