<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DiniyyahClassJournal;
use App\Models\HomeroomAssignment;
use App\Models\ClassSession;
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
    
    public function exportExcel(Request $request)
    {
        $data = $this->getMonitoringData($request);
        $monthName = \Carbon\Carbon::create()->month($data['month'])->translatedFormat('F');
        $fileName = 'Rekap_Jurnal_Diniyyah_' . $monthName . '_' . $data['year'] . '.xls';
        
        return response(view('wali.diniyyah-journals.export-excel', $data))
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }

    private function getMonitoringData(Request $request)
    {
        $teacher = Auth::user()->teacher;
        
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
        $journals = DiniyyahClassJournal::with(['absences.classEnrollment.student'])
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
                    $journal = $dayJournals->where('diniyyah_teacher_assignment_id', $schedule->diniyyah_teacher_assignment_id)
                                        ->filter(function($j) use ($schedule) {
                                            return (string)$j->session_hour === (string)($schedule->classSession->session_name ?? '');
                                        })
                                        ->first();
                                        
                    if ($journal) {
                        $matchedJournalIds[] = $journal->id;
                    }
                                        
                    $dayData['items'][] = [
                        'schedule' => $schedule,
                        'journal' => $journal,
                        'status' => $journal ? 'TERISI' : ($holiday ? 'LIBUR' : 'KOSONG')
                    ];
                }
                
                // 2. Process any journals that were filled but NOT in the schedule
                foreach ($dayJournals as $journal) {
                    if (!in_array($journal->id, $matchedJournalIds)) {
                        // Find if there's an assignment object to get the class/subject info
                        $assignment = $journal->teacherAssignment;
                        // Find classSession info if it exists
                        $session = \App\Models\ClassSession::where('session_name', $journal->session_hour)->first();
                        
                        // Create a mock schedule object so the UI can still render it
                        $mockSchedule = new \stdClass();
                        $mockSchedule->teacherAssignment = $assignment;
                        $mockSchedule->classSession = $session;
                        
                        $dayData['items'][] = [
                            'schedule' => $mockSchedule,
                            'journal' => $journal,
                            'status' => 'TERISI_TIDAK_TERJADWAL'
                        ];
                    }
                }
                
                // Sort items by session start time if available, otherwise by session hour name
                usort($dayData['items'], function($a, $b) {
                    $startA = $a['schedule']->classSession->starts_at ?? '23:59:59';
                    $startB = $b['schedule']->classSession->starts_at ?? '23:59:59';
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
        
        return compact(
            'monitoringData', 
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
}
