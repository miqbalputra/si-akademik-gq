<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DiniyyahClassJournal;
use App\Models\HomeroomAssignment;
use App\Models\ClassSession;
use App\Support\SessionTimetable;
use App\Services\AttendanceStatusClient;
use App\Services\DiniyyahNoKbmAgendaService;
use App\Services\TafsirScheduleGroupingService;
use App\Services\TeachingAttendanceReconciliationService;
use Illuminate\Support\Facades\Auth;

class WaliClassJournalMonitoringController extends Controller
{
    public function __construct(
        private readonly AttendanceStatusClient $attendanceStatusClient,
        private readonly DiniyyahNoKbmAgendaService $noKbmAgendaService,
        private readonly TafsirScheduleGroupingService $tafsirScheduleGroupingService,
        private readonly TeachingAttendanceReconciliationService $reconciliationService,
    ) {}

    public function index(Request $request)
    {
        $data = $this->getMonitoringData($request);
        return view('wali.diniyyah-journals.index', $data);
    }
    
    public function exportPdf(Request $request)
    {
        $data = $this->getMonitoringData($request);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('wali.diniyyah-journals.export-pdf', $data)
            ->setPaper('a4', 'landscape');
            
        $monthName = \Carbon\Carbon::create()->month($data['month'])->translatedFormat('F');
        $fileName = 'Rekap_Jurnal_Diniyyah_' . $monthName . '_' . $data['year'] . '.pdf';
        
        return $pdf->download($fileName);
    }
    
    public function exportExcel(Request $request, \App\Services\Exports\WaliClassJournalMonitoringXlsxExporter $exporter)
    {
        $data = $this->getMonitoringData($request);
        $monthName = \Carbon\Carbon::create()->month($data['month'])->translatedFormat('F');
        $fileName = 'Rekap_Jurnal_Diniyyah_' . $monthName . '_' . $data['year'] . '.xlsx';
        $content = $exporter->export($data);

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Content-Length' => strlen($content),
        ]);
    }

    private function getMonitoringData(Request $request)
    {
        $teacher = Auth::user()->teacher;
        abort_unless($teacher && Auth::user()->canAccessAttendance(), 403);
        
        $month = (int) $request->input('month', date('n')); // 1-12
        $year = (int) $request->input('year', date('Y'));
        
        $filterSubjectId = $request->input('subject_id');
        $filterClassroomTermId = $request->input('classroom_term_id');
        $filterTeacherId = $request->input('teacher_id');
        $filterStatus = $request->input('status');
        
        // Cek apakah tanggal hari ini ada di bulan yang dipilih
        $isCurrentMonth = ($month == date('n') && $year == date('Y'));
        
        // Buat range tanggal
        $startDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth();
        
        // Jika bulan ini, maka end date adalah hari ini
        if ($isCurrentMonth) {
            $endDate = now()->endOfDay();
        }
        
        // Get classroom_term_ids where teacher is active homeroom
        $classroomTermIds = HomeroomAssignment::where('teacher_id', $teacher->id)
            ->where(function($query) {
                $query->whereNull('ends_at')
                      ->orWhere('ends_at', '>=', now());
            })
            ->pluck('classroom_term_id');
            
        // Get schedules
        $schedules = \App\Models\DiniyyahTeachingSchedule::with([
            'teacherAssignment.teacher', 
            'teacherAssignment.classSubject.subject', 
            'teacherAssignment.classSubject.classroomTerm.classroom',
            'classSession'
        ])
        ->whereHas('teacherAssignment.classSubject', function ($query) use ($classroomTermIds) {
            $query->whereIn('classroom_term_id', $classroomTermIds);
        })
        ->get();

        // Tafsir adalah satu sesi serentak lintas kelas. Ambil seluruh slot
        // Tafsir milik guru yang muncul pada kelas wali (bukan hanya kelas wali
        // tersebut) agar agenda dengan cakupan sebagian tidak membebaskan sesi
        // secara keliru.
        $tafsirTeacherIds = $schedules
            ->filter(fn ($schedule) => $this->tafsirScheduleGroupingService->isTafsirSchedule($schedule))
            ->map(fn ($schedule) => $schedule->teacherAssignment?->teacher_id)
            ->filter()
            ->unique()
            ->values();
        $globalTafsirSchedules = $tafsirTeacherIds->isEmpty()
            ? collect()
            : \App\Models\DiniyyahTeachingSchedule::with([
                'teacherAssignment.teacher',
                'teacherAssignment.classSubject.subject',
                'teacherAssignment.classSubject.classroomTerm.classroom',
                'classSession',
            ])
                ->whereHas('teacherAssignment', function ($query) use ($tafsirTeacherIds): void {
                    $query->whereIn('teacher_id', $tafsirTeacherIds)
                        ->whereHas('classSubject.subject', function ($query): void {
                            $query->where('code', SessionTimetable::SESSION_TAFSIR)
                                ->orWhere('name', 'like', '%tafsir%');
                        });
                })
                ->get();

        $attendanceStatuses = $this->attendanceStatusClient->statusesForTeachers(
            $schedules
                ->map(fn ($schedule) => $schedule->teacherAssignment?->teacher)
                ->filter()
                ->unique('id')
                ->values(),
            $startDate,
            $endDate,
            true,
        );
        
        // Collect options for filter dropdowns
        $subjectOptions = collect();
        $classOptions = collect();
        $teacherOptions = collect();
        
        foreach($schedules as $sch) {
            if($sch->teacherAssignment && $sch->teacherAssignment->classSubject) {
                $cs = $sch->teacherAssignment->classSubject;
                if($cs->subject) $subjectOptions->put($cs->subject->id, $cs->subject);
                if($cs->classroomTerm && $cs->classroomTerm->classroom) $classOptions->put($cs->classroomTerm->id, $cs->classroomTerm->name);
                if($sch->teacherAssignment->teacher) $teacherOptions->put($sch->teacherAssignment->teacher->id, $sch->teacherAssignment->teacher);
            }
        }
            
        // Get journals
        $journals = DiniyyahClassJournal::with([
            'absences.classEnrollment.student',
            'teacherAssignment.classSubject.subject',
            'teacherAssignment.classSubject.classroomTerm.classroom',
            'substituteTeacher',
        ])
            ->whereHas('teacherAssignment.classSubject', function ($query) use ($classroomTermIds) {
                $query->whereIn('classroom_term_id', $classroomTermIds);
            })
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get();
            
        // Get holidays
        $holidays = \App\Models\SchoolHoliday::whereBetween('holiday_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy(function($item) {
                return $item->holiday_date->format('Y-m-d');
            });

        $agendaTerms = $schedules
            ->concat($globalTafsirSchedules)
            ->map(fn ($schedule) => $schedule->teacherAssignment?->classSubject?->classroomTerm)
            ->filter()
            ->unique('id')
            ->values();
        $agendaEvents = $this->noKbmAgendaService->eventsForRange($agendaTerms, $startDate, $endDate);
            
        // Generate daily data
        $monitoringData = [];
        if (!$startDate->isFuture()) {
            $currentDate = $startDate->copy();
            $dates = [];
            while ($currentDate <= $endDate) {
                $dates[] = $currentDate->copy();
                $currentDate->addDay();
            }
            
            $reversedDates = array_reverse($dates);
            
            foreach ($reversedDates as $date) {
                $dateStr = $date->format('Y-m-d');
                $dayOfWeek = $date->dayOfWeekIso;
                
                $daySchedules = $schedules->where('day_of_week', $dayOfWeek);
                
                // Skip if no schedules on this day
                if ($daySchedules->isEmpty()) {
                    continue;
                }
                
                $holiday = $holidays->get($dateStr);

                $tafsirAgendaByScheduleId = [];
                $globalDayTafsir = $this->tafsirScheduleGroupingService
                    ->simultaneousGroupsForDate($globalTafsirSchedules, $date);
                $globalSimultaneousScheduleIds = $globalDayTafsir
                    ->flatMap(fn (array $group) => $group['schedules']->pluck('id'))
                    ->map(fn ($id) => (int) $id)
                    ->all();
                foreach ($globalDayTafsir as $tafsirGroup) {
                    $tafsirTerms = $tafsirGroup['schedules']
                        ->map(fn ($schedule) => $schedule->teacherAssignment?->classSubject?->classroomTerm)
                        ->filter()
                        ->unique('id')
                        ->values();
                    $groupAgenda = $this->noKbmAgendaService->forClassroomTerms($agendaEvents, $tafsirTerms, $date);
                    if ($groupAgenda !== null) {
                        foreach ($tafsirGroup['schedules'] as $tafsirSchedule) {
                            $tafsirAgendaByScheduleId[$tafsirSchedule->id] = $groupAgenda;
                        }
                    }
                }
                
                $dayData = [
                    'date' => $date,
                    'is_holiday' => $holiday !== null,
                    'holiday_name' => $holiday ? $holiday->title : null,
                    'items' => []
                ];
                
                // Get all journals for this specific date
                $dayJournals = $journals->filter(function($j) use ($dateStr) {
                    return $j->date->format('Y-m-d') === $dateStr;
                });
                
                $matchedJournalIds = [];
                
                // 1. Process all scheduled sessions
                foreach ($daySchedules as $schedule) {
                    $isSimultaneousTafsir = in_array((int) $schedule->id, $globalSimultaneousScheduleIds, true);

                    $journal = $dayJournals->where('diniyyah_teacher_assignment_id', $schedule->diniyyah_teacher_assignment_id)
                                         ->filter(fn ($journal) => $this->tafsirScheduleGroupingService->journalMatchesSchedule($journal, $schedule))
                                         ->first();

                    if ($journal) {
                        $matchedJournalIds[] = $journal->id;
                    }

                    $teacherAttendance = $attendanceStatuses[(string) ($schedule->teacherAssignment?->teacher_id ?? '')] ?? null;
                    $attendanceStatus = ($teacherAttendance['available'] ?? false)
                        ? ($teacherAttendance['statuses'][$dateStr] ?? null)
                        : null;
                    $classroomTerm = $schedule->teacherAssignment?->classSubject?->classroomTerm;
                    $agenda = $isSimultaneousTafsir
                        ? ($tafsirAgendaByScheduleId[$schedule->id] ?? null)
                        : ($classroomTerm
                            ? $this->noKbmAgendaService->forClassroomTerm($agendaEvents, $classroomTerm, $date)
                            : null);
                    $sessionTime = $this->resolveSessionTime($schedule, $dayOfWeek);
                    $status = $journal
                        ? 'TERISI'
                        : ($holiday
                            ? 'LIBUR'
                            : ($agenda !== null
                                ? 'AGENDA'
                                : ($this->attendanceStatusClient->isExempt($attendanceStatus)
                                    ? strtoupper((string) $attendanceStatus)
                                    : 'KOSONG')));
                    $reconciliation = $this->reconciliationService->reconcile(
                        $dateStr,
                        $sessionTime['ends_at'] ?? null,
                        $attendanceStatus,
                        (bool) ($teacherAttendance['available'] ?? false),
                        $journal !== null,
                        $journal?->substitute_teacher_id !== null,
                        $holiday !== null || $agenda !== null || $isSimultaneousTafsir,
                    );

                    $dayData['items'][] = [
                        'schedule' => $schedule,
                        'journal' => $journal,
                        'status' => $status,
                        'attendance_status' => $attendanceStatus,
                        'agenda' => $agenda,
                        'session_time' => $sessionTime,
                        'reconciliation' => $reconciliation,
                    ];
                }

                // 2. Process any journals that were filled but NOT in the schedule
                foreach ($dayJournals as $journal) {
                    if (!in_array($journal->id, $matchedJournalIds)) {
                        // Find if there's an assignment object to get the class/subject info
                        $assignment = $journal->teacherAssignment;
                        // Find classSession info if it exists
                        $session = \App\Models\ClassSession::where('session_name', $journal->session_hour)->first();

                        // Fallback bila tidak ada ClassSession dengan session_name == session_hour
                        // (mis. session_hour='tafsir' tapi ClassSession prod bernama lain): sintesis
                        // label ramah via SessionTimetable::label() + jam dari snapshot jurnal, agar
                        // badge tidak menampilkan "Jam ?" dan jam sesi tetap tampil.
                        if (! $session) {
                            $session = new \stdClass();
                            $session->session_name = SessionTimetable::label((string)$journal->session_hour);
                            $session->starts_at = $journal->session_starts_at;
                            $session->ends_at = $journal->session_ends_at;
                        }

                        // Create a mock schedule object so the UI can still render it
                        $mockSchedule = new \stdClass();
                        $mockSchedule->teacherAssignment = $assignment;
                        $mockSchedule->classSession = $session;

                        $dayData['items'][] = [
                            'schedule' => $mockSchedule,
                            'journal' => $journal,
                            'status' => 'TERISI_TIDAK_TERJADWAL',
                            'session_time' => $this->resolveSessionTime($mockSchedule, $dayOfWeek),
                        ];
                    }
                }
                
                // Sort items by session start time (per-classroom matrix), otherwise by session hour name
                usort($dayData['items'], function($a, $b) {
                    $startA = $a['session_time']['starts_at'] ?? '23:59:59';
                    $startB = $b['session_time']['starts_at'] ?? '23:59:59';
                    if ($startA === $startB) {
                        return strcmp($a['schedule']->classSession->session_name ?? '', $b['schedule']->classSession->session_name ?? '');
                    }
                    return strcmp($startA, $startB);
                });
                
                // Apply filters
                $filteredItems = [];
                foreach ($dayData['items'] as $item) {
                    $assignment = $item['schedule']->teacherAssignment ?? null;
                    
                    if ($filterSubjectId && $assignment && $assignment->classSubject->subject_id != $filterSubjectId) continue;
                    if ($filterClassroomTermId && $assignment && $assignment->classSubject->classroom_term_id != $filterClassroomTermId) continue;
                    if ($filterTeacherId && $assignment && $assignment->teacher_id != $filterTeacherId) continue;
                    if ($filterStatus === 'REKONSILIASI' && ! ($item['reconciliation']['actionable'] ?? false)) continue;
                    if ($filterStatus && $filterStatus !== 'REKONSILIASI' && $item['status'] !== $filterStatus) continue;
                    
                    $filteredItems[] = $item;
                }
                
                $dayData['items'] = $filteredItems;
                
                // Only add to monitoringData if there are items, or if no filters are applied and it's a holiday (to still show holidays)
                $hasFilters = $filterSubjectId || $filterClassroomTermId || $filterTeacherId || $filterStatus;
                
                if (count($dayData['items']) > 0 || (!$hasFilters && $dayData['is_holiday'])) {
                    $monitoringData[] = $dayData;
                }
            }
        }
        
        $monitoringRows = collect($monitoringData)
            ->flatMap(function (array $dayData): array {
                return collect($dayData['items'])->map(function (array $item) use ($dayData): array {
                    $assignment = $item['schedule']->teacherAssignment ?? null;
                    $classSubject = $assignment?->classSubject;
                    $classroomTerm = $classSubject?->classroomTerm;
                    $subject = $classSubject?->subject;
                    $journal = $item['journal'];

                    return [
                        'date' => $dayData['date'],
                        'date_label' => $dayData['date']->translatedFormat('l, d F Y'),
                        'is_holiday' => $dayData['is_holiday'],
                        'holiday_name' => $dayData['holiday_name'],
                        'session_name' => $item['schedule']->classSession->session_name ?? '?',
                        'session_time' => $item['session_time'],
                        'classroom_term_id' => $classSubject?->classroom_term_id,
                        'classroom_name' => $classroomTerm?->name ?? 'Kelas',
                        'subject_id' => $classSubject?->subject_id,
                        'subject_name' => $subject?->name ?? 'Mapel',
                        'teacher_id' => $assignment?->teacher_id,
                        'teacher_name' => $assignment?->teacher?->name ?? '-',
                        'substitute_teacher_name' => $journal?->substituteTeacher?->name,
                        'status' => $item['status'],
                        'attendance_status' => $item['attendance_status'] ?? null,
                        'reconciliation' => $item['reconciliation'] ?? null,
                        'agenda' => $item['agenda'] ?? null,
                        'agenda_title' => $item['agenda']['title'] ?? null,
                        'agenda_reason' => $item['agenda']['reason'] ?? null,
                        'journal' => $journal,
                        'schedule' => $item['schedule'],
                    ];
                })->all();
            })
            ->values();

        $summaryRows = $monitoringRows;
        $summary = [
            'total_slots' => $summaryRows->count(),
            'filled_slots' => $summaryRows->whereIn('status', ['TERISI', 'TERISI_TIDAK_TERJADWAL'])->count(),
            'empty_slots' => $summaryRows->where('status', 'KOSONG')->count(),
            'hadir_tanpa_jurnal_slots' => $summaryRows->filter(fn (array $row): bool => ($row['reconciliation']['state'] ?? null) === TeachingAttendanceReconciliationService::HADIR_TANPA_JURNAL)->count(),
            'presensi_belum_tercatat_slots' => $summaryRows->filter(fn (array $row): bool => in_array(($row['reconciliation']['state'] ?? null), [
                TeachingAttendanceReconciliationService::PRESENSI_BELUM_TERCATAT,
                TeachingAttendanceReconciliationService::PRESENSI_DAN_JURNAL_BELUM_TERCATAT,
            ], true))->count(),
            'excused_slots' => $summaryRows->whereIn('status', ['IZIN', 'SAKIT'])->count(),
            'izin_slots' => $summaryRows->where('status', 'IZIN')->count(),
            'sakit_slots' => $summaryRows->where('status', 'SAKIT')->count(),
            'agenda_slots' => $summaryRows->where('status', 'AGENDA')->count(),
            'holiday_slots' => $summaryRows->where('status', 'LIBUR')->count(),
            'empty_classrooms' => $summaryRows->where('status', 'KOSONG')->pluck('classroom_name')->filter()->unique()->values(),
            'empty_teachers' => $summaryRows->where('status', 'KOSONG')->pluck('teacher_name')->filter()->unique()->values(),
            'unverified_teachers' => collect($attendanceStatuses)->filter(fn (array $status): bool => ! ($status['available'] ?? false))->count(),
        ];

        return compact(
            'monitoringData',
            'monitoringRows',
            'summary',
            'month',
            'year',
            'subjectOptions',
            'classOptions',
            'teacherOptions',
            'filterSubjectId',
            'filterClassroomTermId',
            'filterTeacherId',
            'filterStatus',
            'teacher'
        );
    }

    private function assignmentActiveOn($assignment, \Carbon\CarbonInterface $date): bool
    {
        $value = $date->toDateString();
        $starts = $assignment?->starts_at?->toDateString();
        $ends = $assignment?->ends_at?->toDateString();

        return ($starts === null || $starts <= $value) && ($ends === null || $ends >= $value);
    }

    /**
     * Resolve jam sesi [starts_at, ends_at] untuk sebuah schedule (real atau mock)
     * memakai matrix per-classroom (`SessionTimetable::resolve`). Matrix inilah
     * sumber kebenaran jam diniyyah — berbeda per gender (Ikhwan Senin 07:40,
     * Akhwat 10:30). Fallback ke jam default global `ClassSession` bila matrix
     * belum di-seed untuk classroom/hari itu (mis. classroom non-Mustawa).
     */
    private function resolveSessionTime($schedule, int $dayOfWeek): ?array
    {
        return $this->tafsirScheduleGroupingService->resolveSessionTime($schedule, $dayOfWeek);
    }
}
