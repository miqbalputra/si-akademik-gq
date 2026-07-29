<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\ClassSessionTime;
use App\Models\Classroom;
use App\Support\SessionTimetable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Atur matrix jam sesi diniyyah per-classroom (tabel `class_session_times`).
 *
 * `SessionTimetable::definitionForClassroom()` (kode) tetap sbg sumber DEFAULT
 * (dipakai {@see resetToDefault()} & seeding awal). Service ini menyediakan
 * operasi harian via UI admin: baca grid, simpan, propagasi per-gender, reset,
 * dan perbandingan Ikhwan vs Akhwat.
 *
 * Gotcha M1-Kamis: kode hanya beri slot Kamis `tafsir` utk level >= 2. M1 tidak
 * punya baris tafsir Kamis. Propagasi naif M1 -> M2 akan menghapus tafsir M2-6.
 * Penanganan: (1) scope level-band (M1 hanya ke M1; M2-6 ke M2-6), (2) merge
 * union-prefer-source (tidak menghapus baris target yg tidak ada di sumber).
 */
class SessionTimeMatrixService
{
    private const DAYS = [1, 2, 3, 4, 5]; // Senin..Jumat

    /**
     * Grid matrix utk satu classroom: semua (hari x sesi). Baris yg belum ada
     * di-isi waktu null agar admin bisa menambah sesi. Urut: hari, lalu sesi
     * (by global starts_at).
     *
     * @return array<int, array{day:int, session_id:int, session_name:string, is_break:bool, starts_at:?string, ends_at:?string, exists:bool}>
     */
    public function matrixFor(int $classroomId): array
    {
        $sessions = ClassSession::query()
            ->orderBy('starts_at')
            ->orderBy('session_name')
            ->get();

        $existing = ClassSessionTime::query()
            ->where('classroom_id', $classroomId)
            ->get()
            ->keyBy(fn (ClassSessionTime $t) => $t->day_of_week.'-'.$t->class_session_id);

        $rows = [];
        foreach (self::DAYS as $day) {
            foreach ($sessions as $session) {
                $key = $day.'-'.$session->id;
                $row = $existing[$key] ?? null;
                $rows[] = [
                    'day' => $day,
                    'session_id' => $session->id,
                    'session_name' => $session->session_name,
                    'is_break' => (bool) $session->is_break,
                    'starts_at' => $row?->starts_at,
                    'ends_at' => $row?->ends_at,
                    'exists' => (bool) $row,
                ];
            }
        }

        return $rows;
    }

    /**
     * Simpan grid. updateOrCreate per (classroom_id, day_of_week, class_session_id).
     * Baris dgn starts_at/ends_at kosong dihapus. Dibungkus transaksi.
     *
     * @param array<int, array{day:int|string, session_id:int|string, starts_at:?string, ends_at:?string}> $rows
     */
    public function saveMatrix(int $classroomId, array $rows): void
    {
        DB::transaction(function () use ($classroomId, $rows) {
            foreach ($rows as $row) {
                $day = (int) ($row['day'] ?? 0);
                $sessionId = (int) ($row['session_id'] ?? 0);
                if ($day < 1 || $day > 7 || $sessionId < 1) {
                    continue;
                }

                $starts = $this->normalizeTime($row['starts_at'] ?? null);
                $ends = $this->normalizeTime($row['ends_at'] ?? null);

                if ($starts === null || $ends === null) {
                    ClassSessionTime::query()
                        ->where('classroom_id', $classroomId)
                        ->where('day_of_week', $day)
                        ->where('class_session_id', $sessionId)
                        ->delete();
                    continue;
                }

                ClassSessionTime::updateOrCreate(
                    [
                        'classroom_id' => $classroomId,
                        'day_of_week' => $day,
                        'class_session_id' => $sessionId,
                    ],
                    [
                        'starts_at' => $starts,
                        'ends_at' => $ends,
                    ],
                );
            }
        });
    }

    /**
     * Propagasi matrix $source ke semua classroom gender sama (exclude source).
     * Scope level-band + merge union-prefer-source (lihat gotcha di docblock kelas).
     *
     * @param string $gender 'ikhwan'|'akhwat' — diabaikan bila sumber Mustawa; gender di-parse dari sumber.
     * @return array{copied:int, targets:int, warnings:array<int,string>}
     */
    public function applyToGender(int $sourceClassroomId, string $gender): array
    {
        $source = Classroom::find($sourceClassroomId);
        if (! $source) {
            return ['copied' => 0, 'targets' => 0, 'warnings' => ['Kelas sumber tidak ditemukan.']];
        }

        $parsed = SessionTimetable::parseClassroom($source);
        if (! $parsed) {
            return ['copied' => 0, 'targets' => 0, 'warnings' => ['Kelas sumber bukan classroom Mustawa (Ikhwan/Akhwat).']];
        }
        [$sourceGender, $sourceLevel] = $parsed;

        $sourceRows = ClassSessionTime::query()
            ->where('classroom_id', $sourceClassroomId)
            ->get();

        $targets = Classroom::query()
            ->whereKeyNot($sourceClassroomId)
            ->get()
            ->filter(function (Classroom $c) use ($sourceGender, $sourceLevel) {
                $p = SessionTimetable::parseClassroom($c);
                if (! $p) {
                    return false;
                }
                [$g, $lvl] = $p;
                if ($g !== $sourceGender) {
                    return false;
                }
                // level-band: sama persis, atau keduanya >= 2 (M2-M6 band).
                return $lvl === $sourceLevel || ($sourceLevel >= 2 && $lvl >= 2);
            });

        $copied = 0;
        $targetCount = $targets->count();
        $warnings = [];

        DB::transaction(function () use ($sourceRows, $targets, &$copied) {
            foreach ($targets as $target) {
                // Merge union, prefer-source: upsert tiap baris sumber ke target.
                // Baris target yg tidak ada di sumber (mis. tafsir) dibiarkan.
                foreach ($sourceRows as $sr) {
                    ClassSessionTime::updateOrCreate(
                        [
                            'classroom_id' => $target->id,
                            'day_of_week' => $sr->day_of_week,
                            'class_session_id' => $sr->class_session_id,
                        ],
                        [
                            'starts_at' => $sr->starts_at,
                            'ends_at' => $sr->ends_at,
                        ],
                    );
                    $copied++;
                }
            }
        });

        $warnings[] = 'Propagasi hanya menyalin/menimpa jam. Baris sesi di kelas target yang tidak ada di sumber (mis. Tafsir) TIDAK dihapus — edit per kelas atau Reset untuk menghapus sesi.';

        if ($sourceLevel === 1 && $targetCount === 0) {
            $warnings[] = 'Mustawa 1 hanya punya 2 sesi di Kamis (tanpa Tafsir). Propagasi M1 hanya berlaku ke kelas M1 lain; tidak ada target lain. Untuk M2–M6, edit salah satu lalu "Terapkan".';
        }

        return ['copied' => $copied, 'targets' => $targetCount, 'warnings' => $warnings];
    }

    /**
     * Reset matrix classroom ke default kode: hapus semua baris lalu
     * SessionTimetable::seedForClassroom(). Mengembalikan jumlah baris hasil.
     */
    public function resetToDefault(int $classroomId): int
    {
        $classroom = Classroom::find($classroomId);
        if (! $classroom) {
            return 0;
        }

        return DB::transaction(function () use ($classroom) {
            ClassSessionTime::query()->where('classroom_id', $classroom->id)->delete();
            SessionTimetable::seedForClassroom($classroom);

            return ClassSessionTime::query()->where('classroom_id', $classroom->id)->count();
        });
    }

    /**
     * Bandingkan matrix dua classroom (join di (day_of_week, class_session_id)).
     *
     * @return array<int, array{day:int, session_name:string, ikhwan:?array{starts_at:string,ends_at:string}, akhwat:?array{starts_at:string,ends_at:string}, differs:bool}>
     */
    public function compare(int $ikhwanClassroomId, int $akhwatClassroomId): array
    {
        $iRows = $this->loadKeyed($ikhwanClassroomId);
        $aRows = $this->loadKeyed($akhwatClassroomId);

        $keys = $iRows->keys()->merge($aRows->keys())->unique();

        $rows = [];
        foreach ($keys as $key) {
            [$day, $sessionId] = explode('-', (string) $key, 2);
            $i = $iRows[$key] ?? null;
            $a = $aRows[$key] ?? null;
            $sessionName = ($i?->classSession?->session_name)
                ?? ($a?->classSession?->session_name)
                ?? '?';
            $iTime = $i ? ['starts_at' => $i->starts_at, 'ends_at' => $i->ends_at] : null;
            $aTime = $a ? ['starts_at' => $a->starts_at, 'ends_at' => $a->ends_at] : null;
            $rows[] = [
                'day' => (int) $day,
                'session_name' => $sessionName,
                'ikhwan' => $iTime,
                'akhwat' => $aTime,
                'differs' => ! $this->sameTime($iTime, $aTime),
            ];
        }

        usort($rows, function (array $x, array $y): int {
            if ($x['day'] !== $y['day']) {
                return $x['day'] <=> $y['day'];
            }
            $sx = $x['ikhwan']['starts_at'] ?? ($x['akhwat']['starts_at'] ?? '99:99:99');
            $sy = $y['ikhwan']['starts_at'] ?? ($y['akhwat']['starts_at'] ?? '99:99:99');

            return strcmp($sx, $sy);
        });

        return $rows;
    }

    /**
     * Opsi classroom Mustawa (parseable) utk dropdown, urut gender lalu level.
     *
     * @return array<int, string> classroom_id => name
     */
    public function mustawaClassroomOptions(): array
    {
        return Classroom::query()
            ->orderBy('name')
            ->get()
            ->filter(fn (Classroom $c) => SessionTimetable::parseClassroom($c) !== null)
            ->sortBy(fn (Classroom $c) => SessionTimetable::parseClassroom($c))
            ->mapWithKeys(fn (Classroom $c) => [$c->id => $c->name])
            ->all();
    }

    /**
     * Classroom Mustawa pertama utk sebuah gender (by level asc), atau null.
     */
    public function firstMustawaClassroomId(string $gender, int $level = 5): ?int
    {
        $name = sprintf('Mustawa %d %s', $level, ucfirst($gender));
        $classroom = Classroom::query()->where('name', $name)->first();
        if ($classroom) {
            return $classroom->id;
        }
        // fallback: classroom Mustawa pertama utk gender ini (level terkecil).
        return Classroom::query()
            ->orderBy('name')
            ->get()
            ->first(function (Classroom $c) use ($gender) {
                $p = SessionTimetable::parseClassroom($c);

                return $p && $p[0] === $gender;
            })
            ?->id;
    }

    /**
     * @return Collection<string, ClassSessionTime> keyed `day-sessionId`
     */
    private function loadKeyed(int $classroomId): Collection
    {
        return ClassSessionTime::query()
            ->with('classSession')
            ->where('classroom_id', $classroomId)
            ->get()
            ->keyBy(fn (ClassSessionTime $t) => $t->day_of_week.'-'.$t->class_session_id);
    }

    private function sameTime(?array $a, ?array $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return ($a['starts_at'] ?? null) === ($b['starts_at'] ?? null)
            && ($a['ends_at'] ?? null) === ($b['ends_at'] ?? null);
    }

    /**
     * Normalisasi input waktu ke `HH:MM:SS`. Terima `HH:MM` (input type=time)
     * atau `HH:MM:SS`. Invalid/kosong → null.
     */
    private function normalizeTime(?string $t): ?string
    {
        if ($t === null) {
            return null;
        }
        $t = trim($t);
        if ($t === '') {
            return null;
        }
        if (! preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $t, $m)) {
            return null;
        }
        $h = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $mi = $m[2];
        $s = $m[3] ?? '00';

        return "{$h}:{$mi}:{$s}";
    }
}