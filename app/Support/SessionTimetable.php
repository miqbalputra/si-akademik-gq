<?php

namespace App\Support;

use App\Models\ClassSession;
use App\Models\ClassSessionTime;
use App\Models\Classroom;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Matrix jam sesi diniyyah per-classroom + hari.
 *
 * Sumber: jadwal_mustawa_ikhwan.md & jadwal_mustawa_akhwat_2026_2027.md.
 * Jam sesi berbeda antara Ikhwan & Akhwat pada Senin, serta khusus Kamis (ada
 * Tafsir 09:50 — HANYA Mustawa 2-6, bukan M1) & Jum'at (08:50).
 *
 * Matrix disimpan di tabel `class_session_times` (di-key per `classroom_id`).
 * Definisi matrix (single source of truth) ada di {@see definitionForClassroom()},
 * dipakai oleh migration reseed (prod) dan {@see seedForClassroom()} (tests).
 *
 * Label sesi hanya 3: "Sesi 1", "Sesi 2", "Sesi Lainnya (Tafsir)" —
 * nilai mesin `session_name` = '1' / '2' / 'tafsir'.
 */
class SessionTimetable
{
    public const SESSION_ONE = '1';
    public const SESSION_TWO = '2';
    public const SESSION_TAFSIR = 'tafsir';

    /**
     * Daftar slot sesi untuk (classroom, hari), urut by starts_at. Tiap slot:
     * { session_name, starts_at, ends_at, is_break }. Kosong bila matrix belum
     * di-seed atau tidak ada sesi di hari itu (mis. Sabtu/Minggu, atau classroom
     * non-Mustawa yang tidak punya matrix).
     */
    public static function slotsFor(int $classroomId, int $dayOfWeek): Collection
    {
        return ClassSessionTime::with('classSession')
            ->where('classroom_id', $classroomId)
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
     * Resolve jam [starts_at, ends_at] untuk (classroom, hari, session_name),
     * atau null bila tidak ada.
     */
    public static function resolve(int $classroomId, int $dayOfWeek, string $sessionName): ?array
    {
        $time = ClassSessionTime::query()
            ->where('classroom_id', $classroomId)
            ->where('day_of_week', $dayOfWeek)
            ->whereHas('classSession', fn ($q) => $q->where('session_name', $sessionName))
            ->first();

        if (! $time) {
            return null;
        }

        return ['starts_at' => $time->starts_at, 'ends_at' => $time->ends_at];
    }

    /**
     * Definisi matrix jam sesi per (gender, level Mustawa, hari). Single source
     * of truth — dipakai migration reseed & {@see seedForClassroom()}.
     *
     * Mengembalikan: [dayOfWeek => [['session_name', 'starts_at', 'ends_at'], ...]]
     * Tafsir 09:50-10:20 di Kamis HANYA untuk level >= 2 (M1 Kamis 09:50 = Tahfidz).
     *
     * @param string $gender 'ikhwan' | 'akhwat'
     * @param int $level 1..6 (Mustawa level)
     */
    public static function definitionForClassroom(string $gender, int $level): array
    {
        // Ikhwan Senin mulai 07:40 (lebih pagi); Akhwat & semua gender hari lain sama.
        $seninOne = $gender === 'ikhwan' ? ['1', '07:40:00', '08:10:00'] : ['1', '10:30:00', '11:00:00'];
        $seninTwo = $gender === 'ikhwan' ? ['2', '08:10:00', '08:40:00'] : ['2', '11:00:00', '11:30:00'];

        $matrix = [
            1 => [$seninOne, $seninTwo],
            2 => [['1', '10:30:00', '11:00:00'], ['2', '11:00:00', '11:30:00']],
            3 => [['1', '10:30:00', '11:00:00'], ['2', '11:00:00', '11:30:00']],
            4 => [['1', '10:30:00', '11:00:00'], ['2', '11:00:00', '11:30:00']],
            5 => [['1', '08:50:00', '09:20:00'], ['2', '09:20:00', '09:50:00']],
        ];

        // Kamis: Tafsir 09:50-10:20 hanya untuk Mustawa 2-6 (level >= 2).
        if ($level >= 2) {
            array_unshift($matrix[4], [self::SESSION_TAFSIR, '09:50:00', '10:20:00']);
        }

        return $matrix;
    }

    /**
     * Parse [gender, level] dari nama classroom "Mustawa N Ikhwan/Akhwat"
     * (format prod). Mengembalikan null bila bukan classroom Mustawa.
     *
     * @return array{0:string,1:int}|null
     */
    public static function parseClassroom(Classroom $classroom): ?array
    {
        if (! preg_match('/Mustawa\s+(\d+)\s+(Ikhwan|Akhwat)/i', (string) $classroom->name, $m)) {
            return null;
        }

        return [strtolower($m[2]), (int) $m[1]];
    }

    /**
     * Seed matrix {@see definitionForClassroom()} untuk sebuah classroom ke
     * tabel `class_session_times`. Idempoten (firstOrCreate). Dipakai tests.
     * Bila $gender/$level null, di-parse dari nama classroom.
     */
    public static function seedForClassroom(Classroom $classroom, ?string $gender = null, ?int $level = null): void
    {
        if ($gender === null || $level === null) {
            $parsed = self::parseClassroom($classroom);
            if ($parsed === null) {
                return;
            }
            [$gender, $level] = $parsed;
        }

        $sessions = self::ensureClassSessions();

        foreach (self::definitionForClassroom($gender, $level) as $day => $slots) {
            foreach ($slots as [$sessionName, $startsAt, $endsAt]) {
                ClassSessionTime::firstOrCreate(
                    [
                        'classroom_id' => $classroom->id,
                        'day_of_week' => $day,
                        'class_session_id' => $sessions[$sessionName]->id,
                    ],
                    [
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                    ],
                );
            }
        }
    }

    /**
     * Pastikan identitas label sesi ('1','2','tafsir') ada. Mengembalikan map
     * [session_name => ClassSession]. Dipakai internal seed.
     *
     * @return array<string, ClassSession>
     */
    private static function ensureClassSessions(): array
    {
        $defaults = [
            self::SESSION_ONE => ['10:30:00', '11:00:00'],
            self::SESSION_TWO => ['11:00:00', '11:30:00'],
            self::SESSION_TAFSIR => ['09:50:00', '10:20:00'],
        ];

        $map = [];
        foreach ($defaults as $name => [$start, $end]) {
            $map[$name] = ClassSession::firstOrCreate(
                ['session_name' => $name],
                ['starts_at' => $start, 'ends_at' => $end, 'is_break' => false],
            );
        }

        return $map;
    }

    /**
     * Label ramah untuk dropdown / tampilan: "Sesi Lainnya (Tafsir)" untuk
     * tafsir, selain itu "Sesi {n}".
     */
    public static function label(string $sessionName): string
    {
        if ($sessionName === self::SESSION_TAFSIR) {
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