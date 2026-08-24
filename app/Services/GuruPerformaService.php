<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ClassEnrollment;
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
 * Menghitung status jurnal dan realisasi JP dari sudut guru PEMILIK JADWAL
 * (teacherAssignment.teacher_id), bukan effectiveTeacher() (yang mencatat
 * JP ke pengganti):
 * - sudah_diisi : slot milik guru yang diisi jurnal oleh guru sendiri
 *   (substitute_teacher_id IS NULL).
 * - kosong      : slot milik guru, tanggal sudah lewat, tanpa jurnal, dan
 *   bukan hari libur atau agenda tanpa KBM.
 * - digantikan  : slot milik guru yang diisi teacher lain
 *   (substitute_teacher_id IS NOT NULL).
 * - jp_berhasil_terlaksana : JP dari slot yang diisi guru sendiri.
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
    public function __construct(
        private readonly AttendanceStatusClient $attendanceStatusClient,
        private readonly DiniyyahNoKbmAgendaService $noKbmAgendaService,
        private readonly TafsirScheduleGroupingService $tafsirScheduleGroupingService,
        private readonly TeachingAttendanceReconciliationService $reconciliationService,
    ) {}

    /**
     * Hitung performa guru untuk bulan/tahun tertentu.
     *
     * @return array{
     *   month: int,
     *   year: int,
     *   is_current_month: bool,
     *   month_label: string,
     *   stats: array{jp_berhasil_terlaksana: int, sudah_diisi: int, kosong: int, digantikan: int, dibebaskan: int, agenda: int, total: int, total_jurnal: int},
     *   journal_rows: Collection<int, array<string, mixed>>,
     *   jp_rows: Collection<int, array<string, mixed>>,
     *   agenda_rows: Collection<int, array<string, mixed>>,
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

        $summary = $this->summaryForRange($teacher, $startDate, $endDate, withReconciliation: true);
        $journalRows = $this->journalRows($summary['journals']);
        $jpSummary = $this->completedTeachingJp($summary['journals']);

        return [
            'month' => $month,
            'year' => $year,
            'is_current_month' => $isCurrentMonth,
            'month_label' => $this->monthLabel($month, $year),
            'stats' => [
                // Realisasi JP memakai jurnal aktual milik guru. Tafsir
                // dikelompokkan dari snapshot tanggal dan jam jurnal, bukan
                // dari banyaknya record kelas atau konfigurasi jadwal terbaru.
                'jp_berhasil_terlaksana' => $jpSummary['total'],
                'sudah_diisi' => $summary['sudah'],
                'kosong' => $summary['kosong'],
                'digantikan' => $summary['digantikan'],
                'dibebaskan' => $summary['dibebaskan'],
                'dibebaskan_izin' => $summary['dibebaskan_izin'],
                'dibebaskan_sakit' => $summary['dibebaskan_sakit'],
                'agenda' => $summary['agenda'],
                'total' => $summary['sudah'] + $summary['kosong'] + $summary['digantikan'] + $summary['dibebaskan'] + $summary['agenda'],
                'total_jurnal' => $journalRows->count(),
            ],
            'journal_rows' => $journalRows,
            'jp_rows' => $jpSummary['rows'],
            'agenda_rows' => collect($summary['agenda_rows']),
            'empty_slots' => $summary['empty_slots'],
            'reconciliation' => [
                'available' => $summary['attendance_verification']['available'],
                'mapped' => $summary['attendance_verification']['mapped'],
                'message' => $summary['attendance_verification']['message'],
                'rows' => collect($summary['reconciliation_rows']),
                'stats' => [
                    'hadir_tanpa_jurnal' => collect($summary['reconciliation_rows'])->where('reconciliation.state', TeachingAttendanceReconciliationService::HADIR_TANPA_JURNAL)->count(),
                    'presensi_belum_tercatat' => collect($summary['reconciliation_rows'])->where('reconciliation.state', TeachingAttendanceReconciliationService::PRESENSI_BELUM_TERCATAT)->count(),
                    'presensi_dan_jurnal_belum_tercatat' => collect($summary['reconciliation_rows'])->where('reconciliation.state', TeachingAttendanceReconciliationService::PRESENSI_DAN_JURNAL_BELUM_TERCATAT)->count(),
                ],
            ],
        ];
    }

    /**
     * Tunggakan jurnal untuk semester akademik yang sedang aktif.
     *
     * Berbeda dari performa bulanan, rentang ini dimulai dari awal semester
     * dan hanya memuat slot yang benar-benar telah lewat. Jika tanggal awal
     * semester belum dilengkapi, gunakan awal tahun ajaran lalu awal bulan
     * berjalan sebagai fallback aman agar data jadwal lama tidak ikut ditagih.
     *
     * @return array{
     *   term: AcademicTerm,
     *   term_label: string,
     *   count: int,
     *   class_count: int,
     *   dibebaskan: int,
     *   dibebaskan_izin: int,
     *   dibebaskan_sakit: int,
     *   empty_slots: list<array<string, mixed>>,
     * }|null
     */
    public function overdueForActiveTerm(Teacher $teacher): ?array
    {
        $term = AcademicTerm::query()
            ->with('academicYear')
            ->where('is_active', true)
            ->first();

        if (! $term) {
            return null;
        }

        $now = Carbon::now('Asia/Jakarta');
        $startDate = $term->starts_at
            ? Carbon::parse($term->starts_at, 'Asia/Jakarta')->startOfDay()
            : ($term->academicYear?->starts_at
                ? Carbon::parse($term->academicYear->starts_at, 'Asia/Jakarta')->startOfDay()
                : $now->copy()->startOfMonth());
        $endDate = $now->copy()->subDay()->endOfDay();

        if ($term->ends_at) {
            $endDate = $endDate->min(Carbon::parse($term->ends_at, 'Asia/Jakarta')->endOfDay());
        }

        if ($startDate->greaterThan($endDate)) {
            return [
                'term' => $term,
                'term_label' => $this->termLabel($term),
                'count' => 0,
                'class_count' => 0,
                'dibebaskan' => 0,
                'dibebaskan_izin' => 0,
                'dibebaskan_sakit' => 0,
                'agenda' => 0,
                'empty_slots' => [],
            ];
        }

        $summary = $this->summaryForRange($teacher, $startDate, $endDate, $term->id);
        $classCount = collect($summary['empty_slots'])
            ->flatMap(fn (array $slot) => $slot['classroom_names_list'] ?? [])
            ->filter()
            ->unique()
            ->count();

        return [
            'term' => $term,
            'term_label' => $this->termLabel($term),
            'count' => $summary['kosong'],
            'class_count' => $classCount,
            'dibebaskan' => $summary['dibebaskan'],
            'dibebaskan_izin' => $summary['dibebaskan_izin'],
            'dibebaskan_sakit' => $summary['dibebaskan_sakit'],
            'agenda' => $summary['agenda'],
            'empty_slots' => $summary['empty_slots'],
        ];
    }

    /**
     * Jalankan penghitungan slot untuk sebuah rentang tanggal. Satu sumber
     * kebenaran ini dipakai kartu performa bulanan dan pengingat semester.
     *
     * @return array{
     *   sudah: int,
     *   kosong: int,
     *   digantikan: int,
     *   dibebaskan: int,
     *   dibebaskan_izin: int,
     *   dibebaskan_sakit: int,
     *   agenda: int,
     *   journals: Collection<int, DiniyyahClassJournal>,
     *   agenda_rows: list<array<string, mixed>>,
     *   empty_slots: list<array<string, mixed>>,
     * }
     */
    private function summaryForRange(
        Teacher $teacher,
        Carbon $startDate,
        Carbon $endDate,
        ?int $academicTermId = null,
        bool $withReconciliation = false,
    ): array {
        $today = Carbon::now('Asia/Jakarta')->toDateString();

        $schedules = DiniyyahTeachingSchedule::with([
            'teacherAssignment.classSubject.subject',
            'teacherAssignment.classSubject.classroomTerm.classroom',
            'classSession',
        ])
            ->whereHas('teacherAssignment', function ($query) use ($teacher, $academicTermId): void {
                $query->where('teacher_id', $teacher->id)
                    ->when($academicTermId, function ($query, int $academicTermId): void {
                        $query->whereHas('classSubject.classroomTerm', fn ($query) => $query->where('academic_term_id', $academicTermId));
                    });
            })
            ->get();

        $journals = DiniyyahClassJournal::with([
            'substituteTeacher',
            'teacherAssignment.teacher',
            'teacherAssignment.classSubject.subject',
            'teacherAssignment.classSubject.classroomTerm.classroom',
            'absences',
        ])
            ->whereHas('teacherAssignment', function ($query) use ($teacher, $academicTermId): void {
                $query->where('teacher_id', $teacher->id)
                    ->when($academicTermId, function ($query, int $academicTermId): void {
                        $query->whereHas('classSubject.classroomTerm', fn ($query) => $query->where('academic_term_id', $academicTermId));
                    });
            })
            ->whereDate('date', '>=', $startDate->toDateString())
            ->whereDate('date', '<=', $endDate->toDateString())
            ->get();

        $holidays = SchoolHoliday::whereDate('holiday_date', '>=', $startDate->toDateString())
            ->whereDate('holiday_date', '<=', $endDate->toDateString())
            ->get()
            ->keyBy(fn ($h) => $h->holiday_date->format('Y-m-d'));

        $classroomTerms = $schedules
            ->map(fn ($schedule) => $schedule->teacherAssignment?->classSubject?->classroomTerm)
            ->filter()
            ->unique('id')
            ->values();
        $agendaEvents = $this->noKbmAgendaService->eventsForRange($classroomTerms, $startDate, $endDate);

        $sudah = 0;
        $kosong = 0;
        $digantikan = 0;
        $dibebaskan = 0;
        $dibebaskanIzin = 0;
        $dibebaskanSakit = 0;
        $agendaCount = 0;
        $emptySlots = [];
        $agendaRows = [];
        $attendance = $this->attendanceStatusClient->statusesForTeacher($teacher, $startDate, $endDate, $withReconciliation);
        $reconciliationRows = [];

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
            $absenceStatus = $attendance['available'] ? ($attendance['statuses'][$dateStr] ?? null) : null;
            $isExcused = $this->attendanceStatusClient->isExempt($absenceStatus);

            $tafsirGroups = $this->tafsirScheduleGroupingService->simultaneousGroupsForDate($daySchedules, $date);
            $simultaneousScheduleIds = $tafsirGroups
                ->flatMap(fn (array $group) => $group['schedules']->pluck('id'))
                ->map(fn ($id) => (int) $id)
                ->all();
            // Tafsir individual diperlakukan sama seperti slot reguler.
            $regularSched = $daySchedules
                ->reject(fn ($schedule) => in_array((int) $schedule->id, $simultaneousScheduleIds, true))
                ->values();

            // Reguler: 1 slot = 1 JP (jp_count).
            foreach ($regularSched as $sched) {
                $journal = $dayJournals
                    ->where('diniyyah_teacher_assignment_id', $sched->diniyyah_teacher_assignment_id)
                    ->first(fn ($journal) => $this->tafsirScheduleGroupingService->journalMatchesSchedule($journal, $sched));
                $classroomTerm = $sched->teacherAssignment?->classSubject?->classroomTerm;
                $agenda = $classroomTerm
                    ? $this->noKbmAgendaService->forClassroomTerm($agendaEvents, $classroomTerm, $date)
                    : null;

                if ($withReconciliation) {
                    $time = $this->resolveSessionTime($sched, $dayOfWeek);
                    $reconciliation = $this->reconciliationService->reconcile(
                        $dateStr,
                        $time['ends_at'] ?? null,
                        $absenceStatus,
                        (bool) $attendance['available'],
                        $journal !== null,
                        $journal?->substitute_teacher_id !== null,
                        $agenda !== null,
                    );
                    if ($reconciliation['actionable']) {
                        $reconciliationRows[] = array_merge(
                            $this->buildEmptySlot($sched, $date, $dayOfWeek),
                            ['reconciliation' => $reconciliation],
                        );
                    }
                }

                if ($journal) {
                    if ($journal->substitute_teacher_id === null) {
                        $sudah += (int) $journal->jp_count;
                    } else {
                        $digantikan += (int) $journal->jp_count;
                    }
                } else {
                    if ($agenda !== null) {
                        $agendaCount++;
                        $agendaRows[] = $this->buildAgendaRow($sched, $date, $dayOfWeek, $agenda);
                    } elseif ($isExcused) {
                        $dibebaskan += 1;
                        $absenceStatus === 'izin' ? $dibebaskanIzin++ : $dibebaskanSakit++;
                    } elseif ($isPast) {
                        $kosong += 1;
                        $emptySlots[] = $this->buildEmptySlot($sched, $date, $dayOfWeek);
                    }
                }
            }

            // Setiap kelompok Tafsir serentak dihitung 1 JP. Kelompok berbeda
            // pada hari yang sama tetapi jam berbeda tetap dihitung terpisah.
            foreach ($tafsirGroups as $tafsirGroup) {
                $tafsirSched = $tafsirGroup['schedules'];
                $tafsirAssignmentIds = $tafsirGroup['assignment_ids'];
                $tafsirJournals = $dayJournals
                    ->whereIn('diniyyah_teacher_assignment_id', $tafsirAssignmentIds)
                    ->filter(fn ($journal) => $tafsirSched->contains(fn ($schedule) => $this->tafsirScheduleGroupingService->journalMatchesSchedule($journal, $schedule)));
                $tafsirTerms = $tafsirSched
                    ->map(fn ($schedule) => $schedule->teacherAssignment?->classSubject?->classroomTerm)
                    ->filter()
                    ->unique('id')
                    ->values();
                $agenda = $this->noKbmAgendaService->forClassroomTerms($agendaEvents, $tafsirTerms, $date);

                if ($withReconciliation) {
                    $firstSchedule = $tafsirSched->first();
                    $time = $firstSchedule ? $this->resolveSessionTime($firstSchedule, $dayOfWeek) : ['ends_at' => null];
                    $allSubstitute = $tafsirJournals->isNotEmpty()
                        && $tafsirJournals->every(fn ($journal) => $journal->substitute_teacher_id !== null);
                    $reconciliation = $this->reconciliationService->reconcile(
                        $dateStr,
                        $time['ends_at'] ?? null,
                        $absenceStatus,
                        (bool) $attendance['available'],
                        $tafsirJournals->isNotEmpty(),
                        $allSubstitute,
                        $agenda !== null,
                    );
                    if ($reconciliation['actionable']) {
                        $reconciliationRows[] = array_merge(
                            $this->buildEmptyTafsirSlot($tafsirSched, $date, $dayOfWeek),
                            ['reconciliation' => $reconciliation],
                        );
                    }
                }

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
                    if ($agenda !== null) {
                        $agendaCount++;
                        $agendaRows[] = $this->buildAgendaTafsirRow($tafsirSched, $date, $dayOfWeek, $agenda);
                    } elseif ($isExcused) {
                        $dibebaskan += 1;
                        $absenceStatus === 'izin' ? $dibebaskanIzin++ : $dibebaskanSakit++;
                    } elseif ($isPast) {
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
        $reconciliationRows = collect($reconciliationRows)
            ->sortByDesc('date')
            ->values()
            ->all();

        return [
            'sudah' => $sudah,
            'kosong' => $kosong,
            'digantikan' => $digantikan,
            'dibebaskan' => $dibebaskan,
            'dibebaskan_izin' => $dibebaskanIzin,
            'dibebaskan_sakit' => $dibebaskanSakit,
            'agenda' => $agendaCount,
            'journals' => $journals,
            'agenda_rows' => $agendaRows,
            'empty_slots' => $emptySlots,
            'reconciliation_rows' => $reconciliationRows,
            'attendance_verification' => $this->attendanceVerification($teacher, $attendance, $withReconciliation),
        ];
    }

    /** @return array{available: bool, mapped: bool, message: ?string} */
    private function attendanceVerification(Teacher $teacher, array $attendance, bool $requested): array
    {
        if (! $requested) {
            return ['available' => false, 'mapped' => false, 'message' => null];
        }
        if (! (bool) config('services.attendance_journal.enabled', false)) {
            return ['available' => false, 'mapped' => false, 'message' => 'Integrasi GeoPresensi belum diaktifkan.'];
        }
        if (trim((string) $teacher->niy) === '') {
            return ['available' => false, 'mapped' => false, 'message' => 'NIY Anda belum terhubung ke GeoPresensi.'];
        }
        if (! ($attendance['mapped'] ?? false)) {
            return ['available' => false, 'mapped' => false, 'message' => 'NIY Anda belum ditemukan atau belum dapat diverifikasi di GeoPresensi.'];
        }
        if (! ($attendance['available'] ?? false)) {
            return ['available' => false, 'mapped' => true, 'message' => 'Status presensi belum dapat diverifikasi dari GeoPresensi.'];
        }

        return ['available' => true, 'mapped' => true, 'message' => null];
    }

    /** @param Collection<int, DiniyyahClassJournal> $journals */
    private function journalRows(Collection $journals): Collection
    {
        $classroomTermIds = $journals
            ->map(fn (DiniyyahClassJournal $journal) => $journal->teacherAssignment?->classSubject?->classroom_term_id)
            ->filter()
            ->unique()
            ->values();
        $activeEnrollmentCounts = $classroomTermIds->isEmpty()
            ? collect()
            : ClassEnrollment::query()
                ->whereIn('classroom_term_id', $classroomTermIds)
                ->where('status', 'active')
                ->selectRaw('classroom_term_id, count(*) as aggregate')
                ->groupBy('classroom_term_id')
                ->pluck('aggregate', 'classroom_term_id');

        return $journals->map(function (DiniyyahClassJournal $journal) use ($activeEnrollmentCounts): array {
            $assignment = $journal->teacherAssignment;
            $classSubject = $assignment?->classSubject;
            $classroomTerm = $classSubject?->classroomTerm;
            $subject = $classSubject?->subject;
            $absenceCounts = [
                'sick' => 0,
                'permission' => 0,
                'absent' => 0,
                'skipped' => 0,
            ];

            foreach ($journal->absences as $absence) {
                if (array_key_exists($absence->status, $absenceCounts)) {
                    $absenceCounts[$absence->status]++;
                }
            }

            $absenceTotal = array_sum($absenceCounts);
            $activeEnrollmentCount = (int) ($activeEnrollmentCounts[$classroomTerm?->id] ?? 0);
            $isSubstitute = $journal->substitute_teacher_id !== null;
            $date = $journal->date;

            return [
                'id' => $journal->id,
                'date' => $date?->toDateString(),
                'date_label' => $date?->locale('id')->translatedFormat('l, d M Y') ?? '-',
                'session_hour' => (string) $journal->session_hour,
                'session_label' => SessionTimetable::label((string) $journal->session_hour),
                'session_time' => $this->journalTime($journal),
                'kelas' => $classroomTerm?->name ?? '-',
                'mapel' => $subject?->name ?? '-',
                'material' => (string) ($journal->material ?? '-'),
                'jp' => (int) ($journal->jp_count ?? 0),
                'guru_asli' => $assignment?->teacher?->name ?? '-',
                'pengganti' => $journal->substituteTeacher?->name,
                'guru_mengajar' => $journal->effectiveTeacher()?->name ?? '-',
                'type' => $isSubstitute ? 'substitute' : 'regular',
                'type_label' => $isSubstitute ? 'Digantikan' : 'Diisi sendiri',
                'hadir' => max(0, $activeEnrollmentCount - $absenceTotal),
                'sakit' => $absenceCounts['sick'],
                'izin' => $absenceCounts['permission'],
                'alpa' => $absenceCounts['absent'],
                'bolos' => $absenceCounts['skipped'],
            ];
        })->values();
    }

    /**
     * Total JP yang benar-benar terlaksana oleh guru pemegang jadwal.
     *
     * Jurnal reguler menggunakan jp_count. Hanya jurnal dengan penanda resmi
     * session_hour="tafsir" yang diperlakukan sebagai Tafsir serentak; Tafsir
     * individual yang disimpan lewat sesi reguler tetap dihitung normal.
     * Jurnal serentak disatukan menurut tanggal dan snapshot jam mulai–selesai,
     * sehingga banyak record kelas pada satu pengajaran tetap bernilai 1 JP.
     *
     * @param  Collection<int, DiniyyahClassJournal>  $journals
     * @return array{total: int, rows: Collection<int, array<string, mixed>>}
     */
    private function completedTeachingJp(Collection $journals): array
    {
        $ownJournals = $journals
            ->filter(fn (DiniyyahClassJournal $journal): bool => $journal->substitute_teacher_id === null)
            ->values();

        $regularRows = $ownJournals
            ->reject(fn (DiniyyahClassJournal $journal): bool => $this->isSimultaneousTafsirJournal($journal))
            ->map(fn (DiniyyahClassJournal $journal): array => $this->regularJpRow($journal));

        $tafsirRows = $ownJournals
            ->filter(fn (DiniyyahClassJournal $journal): bool => $this->isSimultaneousTafsirJournal($journal))
            ->groupBy(fn (DiniyyahClassJournal $journal): string => $this->tafsirJournalSessionKey($journal))
            ->map(fn (Collection $sessionJournals): array => $this->simultaneousTafsirJpRow($sessionJournals));

        $rows = $regularRows
            ->concat($tafsirRows)
            ->sortBy(fn (array $row): array => [$row['date'] ?? '', $row['starts_at'] ?? '', $row['type'] ?? ''])
            ->values();

        return [
            'total' => (int) $rows->sum('jp'),
            'rows' => $rows,
        ];
    }

    private function isSimultaneousTafsirJournal(DiniyyahClassJournal $journal): bool
    {
        return strtolower(trim((string) $journal->session_hour)) === SessionTimetable::SESSION_TAFSIR;
    }

    /** @return array<string, mixed> */
    private function regularJpRow(DiniyyahClassJournal $journal): array
    {
        $assignment = $journal->teacherAssignment;
        $classSubject = $assignment?->classSubject;

        return [
            'key' => 'journal|'.$journal->id,
            'type' => 'regular',
            'type_label' => 'Jurnal kelas',
            'date' => $journal->date?->toDateString(),
            'date_label' => $journal->date?->locale('id')->translatedFormat('l, d F Y') ?? '-',
            'starts_at' => $this->journalTimePart($journal->session_starts_at),
            'ends_at' => $this->journalTimePart($journal->session_ends_at),
            'session_label' => SessionTimetable::label((string) $journal->session_hour),
            'session_time' => $this->journalTime($journal),
            'mapel' => $classSubject?->subject?->name ?? '-',
            'kelas' => $classSubject?->classroomTerm?->name ?? '-',
            'journal_count' => 1,
            'jp' => max(0, (int) $journal->jp_count),
        ];
    }

    /**
     * @param  Collection<int, DiniyyahClassJournal>  $journals
     * @return array<string, mixed>
     */
    private function simultaneousTafsirJpRow(Collection $journals): array
    {
        /** @var DiniyyahClassJournal $first */
        $first = $journals->first();
        $classes = $journals
            ->map(fn (DiniyyahClassJournal $journal) => $journal->teacherAssignment?->classSubject?->classroomTerm?->name)
            ->filter()
            ->unique()
            ->values();

        return [
            'key' => $this->tafsirJournalSessionKey($first),
            'type' => 'tafsir_simultaneous',
            'type_label' => 'Tafsir serentak',
            'date' => $first->date?->toDateString(),
            'date_label' => $first->date?->locale('id')->translatedFormat('l, d F Y') ?? '-',
            'starts_at' => $this->journalTimePart($first->session_starts_at),
            'ends_at' => $this->journalTimePart($first->session_ends_at),
            'session_label' => 'Tafsir serentak',
            'session_time' => $this->journalTime($first),
            'mapel' => 'Tafsir',
            'kelas' => $classes->implode(', ') ?: '-',
            'journal_count' => $journals->count(),
            'jp' => 1,
        ];
    }

    private function tafsirJournalSessionKey(DiniyyahClassJournal $journal): string
    {
        $date = $journal->date?->toDateString() ?? 'without-date';
        $startsAt = $this->journalTimePart($journal->session_starts_at);
        $endsAt = $this->journalTimePart($journal->session_ends_at);

        if ($startsAt !== null && $endsAt !== null) {
            return implode('|', [$date, $startsAt, $endsAt]);
        }

        // Kompatibilitas jurnal Tafsir lama yang belum menyimpan snapshot jam.
        return implode('|', [$date, 'legacy', (string) $journal->session_hour]);
    }

    private function journalTimePart(mixed $time): ?string
    {
        $time = trim((string) $time);

        return $time === '' ? null : substr($time, 0, 5);
    }

    private function journalTime(DiniyyahClassJournal $journal): ?string
    {
        $times = collect([$journal->session_starts_at, $journal->session_ends_at])
            ->filter()
            ->map(fn ($time): string => Carbon::parse($time)->format('H:i'));

        return $times->isEmpty() ? null : $times->implode(' - ');
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
     * Resolve jam sesi [starts_at, ends_at] untuk schedule memakai matrix
     * per-classroom (sumber kebenaran jam diniyyah), fallback ke jam default
     * global ClassSession. Mirror WaliClassJournalMonitoringController::resolveSessionTime.
     *
     * @return array{starts_at: ?string, ends_at: ?string}
     */
    private function resolveSessionTime($schedule, int $dayOfWeek): array
    {
        return $this->tafsirScheduleGroupingService->resolveSessionTime($schedule, $dayOfWeek);
    }

    /** @return array<string, mixed> */
    private function buildAgendaRow($sched, Carbon $date, int $dayOfWeek, array $agenda): array
    {
        $classSubject = $sched->teacherAssignment->classSubject;
        $classroomTerm = $classSubject->classroomTerm;
        $time = $this->resolveSessionTime($sched, $dayOfWeek);
        $sessionName = (string) ($sched->classSession->session_name ?? '');

        return [
            'id' => null,
            'date' => $date->toDateString(),
            'date_label' => $date->locale('id')->translatedFormat('l, d F Y'),
            'session_hour' => $sessionName,
            'session_label' => SessionTimetable::label($sessionName),
            'session_time' => collect([$time['starts_at'] ?? null, $time['ends_at'] ?? null])->filter()->implode(' - '),
            'kelas' => $classroomTerm?->name ?? '-',
            'mapel' => $classSubject->subject?->name ?? '-',
            'material' => $agenda['reason'],
            'jp' => 1,
            'guru_asli' => $sched->teacherAssignment?->teacher?->name ?? '-',
            'pengganti' => null,
            'guru_mengajar' => '-',
            'type' => 'agenda',
            'type_label' => 'Agenda tanpa KBM',
            'status' => 'AGENDA',
            'is_virtual' => true,
            'agenda_id' => $agenda['ids'][0] ?? null,
            'agenda_title' => $agenda['title'],
            'hadir' => 0,
            'sakit' => 0,
            'izin' => 0,
            'alpa' => 0,
            'bolos' => 0,
        ];
    }

    /** @param Collection<int, mixed> $tafsirSched */
    private function buildAgendaTafsirRow(Collection $tafsirSched, Carbon $date, int $dayOfWeek, array $agenda): array
    {
        $first = $tafsirSched->first();
        $time = $first ? $this->resolveSessionTime($first, $dayOfWeek) : ['starts_at' => null, 'ends_at' => null];
        if (($time['starts_at'] ?? null) === null) {
            $time = ['starts_at' => '09:50:00', 'ends_at' => '10:20:00'];
        }

        return [
            'id' => null,
            'date' => $date->toDateString(),
            'date_label' => $date->locale('id')->translatedFormat('l, d F Y'),
            'session_hour' => SessionTimetable::SESSION_TAFSIR,
            'session_label' => SessionTimetable::label(SessionTimetable::SESSION_TAFSIR),
            'session_time' => $time['starts_at'].' - '.$time['ends_at'],
            'kelas' => $tafsirSched->map(fn ($schedule) => $schedule->teacherAssignment?->classSubject?->classroomTerm?->name)->filter()->unique()->implode(', '),
            'mapel' => $first?->teacherAssignment?->classSubject?->subject?->name ?? 'Tafsir',
            'material' => $agenda['reason'],
            'jp' => 1,
            'guru_asli' => $first?->teacherAssignment?->teacher?->name ?? '-',
            'pengganti' => null,
            'guru_mengajar' => '-',
            'type' => 'agenda',
            'type_label' => 'Agenda tanpa KBM',
            'status' => 'AGENDA',
            'is_virtual' => true,
            'agenda_id' => $agenda['ids'][0] ?? null,
            'agenda_title' => $agenda['title'],
            'hadir' => 0,
            'sakit' => 0,
            'izin' => 0,
            'alpa' => 0,
            'bolos' => 0,
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
            'classroom_names_list' => [$classroomTerm?->classroom?->name ?? '-'],
            'subject_name' => $classSubject->subject?->name ?? '-',
            'assignment_id' => $sched->diniyyah_teacher_assignment_id,
            'session_hour' => $sessionName,
            'fill_url' => route('guru.diniyyah-journals.index', [
                'classroom_term_id' => $classroomTerm?->id,
                'date' => $date->toDateString(),
                'assignment_id' => $sched->diniyyah_teacher_assignment_id,
                'session_hour' => $sessionName,
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
            ->all();
        $assignmentIds = $tafsirSched
            ->pluck('diniyyah_teacher_assignment_id')
            ->unique()
            ->values()
            ->all();

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
            'classroom_names' => $classroomNames === [] ? '-' : implode(', ', $classroomNames),
            'classroom_names_list' => $classroomNames,
            'subject_name' => $first?->teacherAssignment?->classSubject?->subject?->name ?? 'Tafsir',
            'assignment_ids' => $assignmentIds,
            'fill_url' => route('guru.diniyyah-tafsir-journals.index', [
                'date' => $date->toDateString(),
                'assignment_ids' => $assignmentIds,
            ]),
        ];
    }

    private function monthLabel(int $month, int $year): string
    {
        return Carbon::createFromDate($year, $month, 1)->locale('id')->translatedFormat('F Y');
    }

    private function termLabel(AcademicTerm $term): string
    {
        return collect([$term->name, $term->academicYear?->name])
            ->filter()
            ->implode(' ');
    }
}
