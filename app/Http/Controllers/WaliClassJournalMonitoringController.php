<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DiniyyahClassJournal;
use App\Models\HomeroomAssignment;
use App\Models\ClassSession;
use App\Support\SessionTimetable;
use Illuminate\Support\Facades\Auth;

class WaliClassJournalMonitoringController extends Controller
{
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
                    $isTafsir = $this->isTafsirSchedule($schedule);

                    $journal = $dayJournals->where('diniyyah_teacher_assignment_id', $schedule->diniyyah_teacher_assignment_id)
                                        ->filter(function($j) use ($schedule, $isTafsir) {
                                            // Cocokkan persis session_hour vs session_name slot jadwal.
                                            if ((string)$j->session_hour === (string)($schedule->classSession->session_name ?? '')) {
                                                return true;
                                            }
                                            // Pengecualian Tafsir: jurnal serentak menyimpan
                                            // session_hour='tafsir' (konstanta mesin), sedangkan slot
                                            // jadwal bisa memakai ClassSession bernama lain (mis.
                                            // "Tafsir (M2 - M6)" via admin). Ikat berdasar assignment
                                            // supaya jurnal mengisi slot terjadwal, bukan jadi baris
                                            // "Ekstra" dengan label "Jam ?".
                                            return $isTafsir
                                                && strtolower((string)$j->session_hour) === SessionTimetable::SESSION_TAFSIR;
                                        })
                                        ->first();

                    if ($journal) {
                        $matchedJournalIds[] = $journal->id;
                    }

                    $dayData['items'][] = [
                        'schedule' => $schedule,
                        'journal' => $journal,
                        'status' => $journal ? 'TERISI' : ($holiday ? 'LIBUR' : 'KOSONG'),
                        'session_time' => $this->resolveSessionTime($schedule, $dayOfWeek),
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
                    if ($filterStatus && $item['status'] !== $filterStatus) continue;
                    
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
            'holiday_slots' => $summaryRows->where('status', 'LIBUR')->count(),
            'empty_classrooms' => $summaryRows->where('status', 'KOSONG')->pluck('classroom_name')->filter()->unique()->values(),
            'empty_teachers' => $summaryRows->where('status', 'KOSONG')->pluck('teacher_name')->filter()->unique()->values(),
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

    /**
     * Apakah schedule ini milik penugasan Tafsir (subject code 'tafsir' atau nama
     * mengandung 'Tafsir'). Dipakai untuk pencocokan jurnal serentak yang menyimpan
     * session_hour='tafsir' ke slot jadwal yang mungkin memakai nama session lain.
     * Identifikasi sama dengan GuruDiniyyahTafsirJournalController::tafsirAssignmentsFor.
     */
    private function isTafsirSchedule($schedule): bool
    {
        $subject = $schedule->teacherAssignment?->classSubject?->subject ?? null;
        if (! $subject) {
            return false;
        }

        return strtolower($subject->code) === SessionTimetable::SESSION_TAFSIR
            || str_contains(strtolower($subject->name), 'tafsir');
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
        $classroom = $schedule->teacherAssignment?->classSubject?->classroomTerm?->classroom ?? null;
        $sessionName = $schedule->classSession?->session_name ?? null;

        if ($classroom && $sessionName) {
            $resolved = \App\Support\SessionTimetable::resolve($classroom->id, $dayOfWeek, $sessionName);
            if ($resolved) {
                return $resolved;
            }
        }

        // Fallback: jam default global ClassSession bila matrix per-classroom
        // belum di-seed (mis. classroom non-Mustawa / hari tanpa matrix).
        if ($schedule->classSession && $schedule->classSession->starts_at) {
            return [
                'starts_at' => $schedule->classSession->starts_at,
                'ends_at' => $schedule->classSession->ends_at,
            ];
        }

        return null;
    }
}
