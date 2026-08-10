<?php

namespace App\Services;

use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\SchoolHoliday;
use App\Models\Teacher;
use App\Support\SessionTimetable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Kartu performa jurnal mengajar diniyyah per guru, per bulan.
 *
 * Menghitung tiga angka dari sudut guru PEMILIK JADWAL
 * (teacherAssignment.teacher_id), bukan effectiveTeacher() (yang mencatat
 * JP ke pengganti):
 * - sudah_diisi : slot milik guru yang diisi jurnal oleh guru sendiri
 *   (substitute_teacher_id IS NULL).
 * - kosong      : slot milik guru, tanggal sudah lewat, tanpa jurnal, dan
 *   bukan hari libur.
 * - digantikan  : slot milik guru yang diisi teacher lain
 *   (substitute_teacher_id IS NOT NULL).
 *
 * Satuan = JP dengan dedup Tafsir (konsisten dengan {@see RekapJurnalGuruService}):
 * 1 sesi Tafsir serentak ke beberapa kelas di hari yang sama dihitung 1 JP
 * per (guru, tanggal). Non-tafsir: 1 slot = jp_count (umumnya 1).
 *
 * App timezone = UTC; "hari ini" / "bulan berjalan" memakai Asia/Jakarta
 * eksplisit supaya tidak meleset di larut malam WIB.
 */
class GuruPerformaService
{
    /**
     * Hitung performa guru untuk bulan/tahun tertentu.
     *
     * @return array{
     *   month: int,
     *   year: int,
     *   is_current_month: bool,
     *   month_label: string,
     *   stats: array{sudah_diisi: int, kosong: int, digantikan: int, total: int},
     *   empty_slots: list<array<string, mixed>>,
     * }
     */
    public function calculate(Teacher $teacher, int $month, int $year): array
    {
        $now = Carbon::now('Asia/Jakarta');
        $today = $now->toDateString();
        $currentMonth = (int) $now->format('n');
        $currentYear = (int) $now->format('Y');

        // Defensive: bulan masa depan di-clamp ke bulan berjalan (tidak ada
        // data relevan untuk menghitung slot "kosong" di masa depan).
        if ($year > $currentYear || ($year === $currentYear && $month > $currentMonth)) {
            $month = $currentMonth;
            $year = $currentYear;
        }
        $isCurrentMonth = ($month === $currentMonth && $year === $currentYear);

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endDate = $isCurrentMonth
            ? Carbon::parse($today, 'Asia/Jakarta')->endOfDay()
            : $startDate->copy()->endOfMonth();

        $schedules = DiniyyahTeachingSchedule::with([
            'teacherAssignment.classSubject.subject',
            'teacherAssignment.classSubject.classroomTerm.classroom',
            'classSession',
        ])
            ->whereHas('teacherAssignment', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->get();

        $journals = DiniyyahClassJournal::with(['substituteTeacher'])
            ->whereHas('teacherAssignment', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->whereDate('date', '>=', $startDate->toDateString())
            ->whereDate('date', '<=', $endDate->toDateString())
            ->get();

        $holidays = SchoolHoliday::whereDate('holiday_date', '>=', $startDate->toDateString())
            ->whereDate('holiday_date', '<=', $endDate->toDateString())
            ->get()
            ->keyBy(fn ($h) => $h->holiday_date->format('Y-m-d'));

        $sudah = 0;
        $kosong = 0;
        $digantikan = 0;
        $emptySlots = [];

        $date = $startDate->copy();
        while ($date <= $endDate) {
            $dateStr = $date->toDateString();

            // Slot tanggal setelah hari ini belum lewat — tidak masuk hitungan.
            if ($dateStr > $today) {
                $date->addDay();
                continue;
            }

            // "Kosong" hanya untuk tanggal yang SUDAH LEWAT (date < today).
            // Hari ini belum lewat: jurnal yang sudah diisi tetap dihitung "sudah",
            // tetapi slot yang belum terisi belum dikategorikan kosong.
            $isPast = $dateStr < $today;

            // Hari libur: exclude dari semua hitungan (bukan "kosong").
            if ($holidays->has($dateStr)) {
                $date->addDay();
                continue;
            }

            $dayOfWeek = $date->dayOfWeekIso;
            $daySchedules = $schedules->where('day_of_week', $dayOfWeek)
                ->filter(fn ($s) => $this->assignmentActiveOn($s->teacherAssignment, $date));

            if ($daySchedules->isEmpty()) {
                $date->addDay();
                continue;
            }

            $dayJournals = $journals->filter(fn ($j) => $j->date->format('Y-m-d') === $dateStr);

            $tafsirSched = $daySchedules->filter(fn ($s) => $this->isTafsir($s))->values();
            $regularSched = $daySchedules->reject(fn ($s) => $this->isTafsir($s))->values();

            // Reguler: 1 slot = 1 JP (jp_count).
            foreach ($regularSched as $sched) {
                $journal = $dayJournals
                    ->where('diniyyah_teacher_assignment_id', $sched->diniyyah_teacher_assignment_id)
                    ->filter(function ($j) use ($sched): bool {
                        // Cocokkan persis session_hour jurnal vs session_name slot jadwal.
                        return (string) $j->session_hour === (string) ($sched->classSession->session_name ?? '');
                    })
                    ->first();

                if ($journal) {
                    if ($journal->substitute_teacher_id === null) {
                        $sudah += (int) $journal->jp_count;
                    } else {
                        $digantikan += (int) $journal->jp_count;
                    }
                } else {
                    if ($isPast) {
                        $kosong += 1;
                        $emptySlots[] = $this->buildEmptySlot($sched, $date, $dayOfWeek);
                    }
                }
            }

            // Tafsir: dedup per (guru, tanggal) = 1 JP. 1 entry kosong gabungan
            // semua kelas tafsir hari itu (diisi serentak via 1 form).
            if ($tafsirSched->isNotEmpty()) {
                $tafsirAssignmentIds = $tafsirSched->pluck('diniyyah_teacher_assignment_id')->unique()->all();
                $tafsirJournals = $dayJournals
                    ->whereIn('diniyyah_teacher_assignment_id', $tafsirAssignmentIds)
                    ->filter(fn ($j) => strtolower((string) $j->session_hour) === SessionTimetable::SESSION_TAFSIR);

                if ($tafsirJournals->isNotEmpty()) {
                    // ≥1 jurnal tafsir ada → dihitung 1 sesi (dedup). Sebagian
                    // kelas terisi tidak membuat "setengah kosong"; konsisten
                    // dengan RekapJurnalGuruService. Guru asli dihitung "sudah"
                    // bila ada ≥1 jurnal tanpa pengganti.
                    $anyAsli = $tafsirJournals->contains(fn ($j) => $j->substitute_teacher_id === null);
                    if ($anyAsli) {
                        $sudah += 1;
                    } else {
                        $digantikan += 1;
                    }
                } else {
                    if ($isPast) {
                        $kosong += 1;
                        $emptySlots[] = $this->buildEmptyTafsirSlot($tafsirSched, $date, $dayOfWeek);
                    }
                }
            }

            $date->addDay();
        }

        // Urutkan slot kosong tanggal terbaru di atas (paling relevan untuk dikejar).
        $emptySlots = collect($emptySlots)
            ->sortByDesc('date')
            ->values()
            ->all();

        return [
            'month' => $month,
            'year' => $year,
            'is_current_month' => $isCurrentMonth,
            'month_label' => $this->monthLabel($month, $year),
            'stats' => [
                'sudah_diisi' => $sudah,
                'kosong' => $kosong,
                'digantikan' => $digantikan,
                'total' => $sudah + $kosong + $digantikan,
            ],
            'empty_slots' => $emptySlots,
        ];
    }

    /**
     * Apakah assignment guru aktif pada tanggal tertentu (tangani assignment
     * yang berakhir/tengah bulan).
     */
    private function assignmentActiveOn($assignment, Carbon $date): bool
    {
        $dateStr = $date->toDateString();
        $startStr = $assignment->starts_at ? Carbon::parse($assignment->starts_at)->toDateString() : null;
        $endStr = $assignment->ends_at ? Carbon::parse($assignment->ends_at)->toDateString() : null;

        return ($startStr === null || $startStr <= $dateStr)
            && ($endStr === null || $endStr >= $dateStr);
    }

    /**
     * Apakah schedule ini milik penugasan Tafsir. Identifikasi sama dengan
     * WaliClassJournalMonitoringController::isTafsirSchedule dan
     * GuruDiniyyahTafsirJournalController::tafsirAssignmentsFor.
     */
    private function isTafsir($schedule): bool
    {
        $subject = $schedule->teacherAssignment?->classSubject?->subject ?? null;
        if (! $subject) {
            return false;
        }

        return strtolower((string) $subject->code) === SessionTimetable::SESSION_TAFSIR
            || str_contains(strtolower((string) $subject->name), 'tafsir');
    }

    /**
     * Resolve jam sesi [starts_at, ends_at] untuk schedule memakai matrix
     * per-classroom (sumber kebenaran jam diniyyah), fallback ke jam default
     * global ClassSession. Mirror WaliClassJournalMonitoringController::resolveSessionTime.
     *
     * @return array{starts_at: ?string, ends_at: ?string}
     */
    private function resolveSessionTime($schedule, int $dayOfWeek): array
    {
        $classroom = $schedule->teacherAssignment?->classSubject?->classroomTerm?->classroom ?? null;
        $sessionName = (string) ($schedule->classSession->session_name ?? '');

        if ($classroom) {
            $resolved = SessionTimetable::resolve($classroom->id, $dayOfWeek, $sessionName);
            if ($resolved) {
                return ['starts_at' => $resolved['starts_at'], 'ends_at' => $resolved['ends_at']];
            }
        }

        return [
            'starts_at' => $schedule->classSession->starts_at ?? null,
            'ends_at' => $schedule->classSession->ends_at ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEmptySlot($sched, Carbon $date, int $dayOfWeek): array
    {
        $classSubject = $sched->teacherAssignment->classSubject;
        $classroomTerm = $classSubject->classroomTerm;
        $time = $this->resolveSessionTime($sched, $dayOfWeek);
        $sessionName = (string) ($sched->classSession->session_name ?? '');

        return [
            'date' => $date->toDateString(),
            'date_label' => $date->locale('id')->translatedFormat('l, d F Y'),
            'is_tafsir' => false,
            'session_label' => SessionTimetable::label($sessionName),
            'starts_at' => $time['starts_at'],
            'ends_at' => $time['ends_at'],
            'classroom_term_id' => $classroomTerm?->id,
            'classroom_names' => $classroomTerm?->classroom?->name ?? '-',
            'subject_name' => $classSubject->subject?->name ?? '-',
            'fill_url' => route('guru.diniyyah-journals.index', [
                'classroom_term_id' => $classroomTerm?->id,
                'date' => $date->toDateString(),
            ]),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $tafsirSched
     * @return array<string, mixed>
     */
    private function buildEmptyTafsirSlot(Collection $tafsirSched, Carbon $date, int $dayOfWeek): array
    {
        $first = $tafsirSched->first();
        $classroomNames = $tafsirSched
            ->map(fn ($s) => $s->teacherAssignment?->classSubject?->classroomTerm?->classroom?->name)
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');

        // Jam tafsir sama untuk semua classroom (09:50-10:20); resolve dari
        // classroom pertama, fallback ke default ClassSession tafsir.
        $time = $this->resolveSessionTime($first, $dayOfWeek);
        if ($time['starts_at'] === null) {
            $time = ['starts_at' => '09:50:00', 'ends_at' => '10:20:00'];
        }

        return [
            'date' => $date->toDateString(),
            'date_label' => $date->locale('id')->translatedFormat('l, d F Y'),
            'is_tafsir' => true,
            'session_label' => SessionTimetable::label(SessionTimetable::SESSION_TAFSIR),
            'starts_at' => $time['starts_at'],
            'ends_at' => $time['ends_at'],
            'classroom_term_id' => null,
            'classroom_names' => $classroomNames ?: '-',
            'subject_name' => $first?->teacherAssignment?->classSubject?->subject?->name ?? 'Tafsir',
            'fill_url' => route('guru.diniyyah-tafsir-journals.index', [
                'date' => $date->toDateString(),
            ]),
        ];
    }

    private function monthLabel(int $month, int $year): string
    {
        return Carbon::createFromDate($year, $month, 1)->locale('id')->translatedFormat('F Y');
    }
}