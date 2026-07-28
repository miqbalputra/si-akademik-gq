<?php

namespace App\Support;

use App\Models\ClassSessionTime;
use App\Models\ClassroomTerm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Matrix jam sesi diniyyah per gender (ikhwan/akhwat) + hari.
 *
 * Sumber: jadwal_mustawa_ikhwan.md & jadwal_mustawa_akhwat_2026_2027.md.
 * Jam sesi berbeda antara Ikhwan & Akhwat pada Senin, serta khusus Kamis (ada
 * Tafsir 09:50) & Jum'at (08:50). Matrix disimpan di tabel `class_session_times`
 * dan di-seed oleh migration `2026_07_28_000003_seed_class_session_timetable`.
 *
 * Label sesi hanya 3: "Sesi 1", "Sesi 2", "Sesi Lainnya (Tafsir)" —
 * nilai mesin `session_name` = '1' / '2' / 'tafsir'.
 */
class SessionTimetable
{
    public const GROUP_IKHWAN = 'ikhwan';
    public const GROUP_AKHWAT = 'akhwat';

    /**
     * Tentukan gender group (ikhwan/akhwat) untuk sebuah classroom term.
     * Sumber preferensi: kolom `classrooms.gender_group`. Di prod nilai kolom
     * ini 'male'/'female' (bukan ikhwan/akhwat), jadi keduanya dipetakan.
     * Bila kolom kosong/mixed/unknown, fallback parse kata kunci dari nama
     * classroom (mis. "Mustawa 1 Ikhwan") lalu nama classroom_term.
     */
    public static function genderFor(ClassroomTerm $classroomTerm): string
    {
        $group = $classroomTerm->classroom?->gender_group;

        return match ($group) {
            self::GROUP_IKHWAN, 'male', 'putra', 'laki-laki' => self::GROUP_IKHWAN,
            self::GROUP_AKHWAT, 'female', 'putri', 'perempuan' => self::GROUP_AKHWAT,
            default => self::genderFromName($classroomTerm),
        };
    }

    private static function genderFromName(ClassroomTerm $classroomTerm): string
    {
        // Cek nama classroom dulu (di prod berakhiran Ikhwan/Akhwat), lalu nama term.
        foreach (array_filter([$classroomTerm->classroom?->name, $classroomTerm->name]) as $name) {
            if (preg_match('/akhwat/i', $name)) {
                return self::GROUP_AKHWAT;
            }
            if (preg_match('/ikhwan/i', $name)) {
                return self::GROUP_IKHWAN;
            }
        }

        return self::GROUP_IKHWAN;
    }

    /**
     * Daftar slot sesi untuk (gender, hari), urut by starts_at. Tiap slot:
     * { session_name, starts_at, ends_at, is_break }. Kosong bila matrix belum
     * di-seed atau tidak ada sesi di hari itu (mis. Sabtu/Minggu).
     */
    public static function slotsFor(string $group, int $dayOfWeek): Collection
    {
        return ClassSessionTime::with('classSession')
            ->where('classroom_group', $group)
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('starts_at')
            ->get()
            ->map(fn (ClassSessionTime $time) => (object) [
                'session_name' => $time->classSession->session_name,
                'starts_at' => $time->starts_at,
                'ends_at' => $time->ends_at,
                'is_break' => (bool) $time->classSession->is_break,
            ]);
    }

    /**
     * Resolve jam [starts_at, ends_at] untuk (gender, hari, session_name),
     * atau null bila tidak ada.
     */
    public static function resolve(string $group, int $dayOfWeek, string $sessionName): ?array
    {
        $time = ClassSessionTime::query()
            ->where('classroom_group', $group)
            ->where('day_of_week', $dayOfWeek)
            ->whereHas('classSession', fn ($q) => $q->where('session_name', $sessionName))
            ->first();

        if (! $time) {
            return null;
        }

        return ['starts_at' => $time->starts_at, 'ends_at' => $time->ends_at];
    }

    /**
     * Label ramah untuk dropdown / tampilan: "Sesi Lainnya (Tafsir)" untuk
     * tafsir, selain itu "Sesi {n}".
     */
    public static function label(string $sessionName): string
    {
        if ($sessionName === 'tafsir') {
            return 'Sesi Lainnya (Tafsir)';
        }

        return 'Sesi '.$sessionName;
    }

    /**
     * dayOfWeekIso (1=Senin..7=Minggu) dari sebuah tanggal string/Carbon.
     */
    public static function dayOfWeekIso(string|Carbon $date): int
    {
        return Carbon::parse($date)->dayOfWeekIso;
    }
}