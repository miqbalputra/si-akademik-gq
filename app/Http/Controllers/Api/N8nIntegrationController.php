<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\DiniyyahClassJournal;
use App\Models\SchoolHoliday;
use App\Services\AttendanceStatusClient;
use App\Services\DiniyyahNoKbmAgendaService;
use App\Services\TafsirScheduleGroupingService;
use Carbon\Carbon;

class N8nIntegrationController extends Controller
{
    public function getMissingDiniyyahJournals(Request $request)
    {
        // Fail-closed: the integration token MUST be configured in .env
        // (config/services.php → services.n8n.token). There is no default —
        // a missing token denies all access instead of authenticating against
        // a guessable hardcoded value. hash_equals() avoids timing leaks.
        $token = (string) config('services.n8n.token');

        if ($token === '') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $bearer = (string) $request->bearerToken();
        $apiKey = (string) $request->header('X-API-Key');

        $matchesBearer = $bearer !== '' && hash_equals($token, $bearer);
        $matchesApiKey = $apiKey !== '' && hash_equals($token, $apiKey);

        if (! $matchesBearer && ! $matchesApiKey) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $now = Carbon::now('Asia/Jakarta');
        // Iso day of week: 1 = Monday, 7 = Sunday
        $currentDayOfWeek = $now->dayOfWeekIso;
        $currentDate = $now->format('Y-m-d');
        $currentTime = $now->format('H:i:s');

        // Ambil semua jadwal hari ini dahulu agar kelompok Tafsir serentak tidak
        // terpecah hanya karena data kelas lain tidak ikut query awal.
        $allDaySchedules = DiniyyahTeachingSchedule::with([
                'teacherAssignment.teacher',
                'teacherAssignment.classSubject.subject',
                'teacherAssignment.classSubject.classroomTerm.classroom',
                'classSession'
            ])
            ->where('day_of_week', $currentDayOfWeek)
            ->get();

        $attendanceClient = app(AttendanceStatusClient::class);
        $agendaService = app(DiniyyahNoKbmAgendaService::class);
        $tafsirGroupingService = app(TafsirScheduleGroupingService::class);
        $schedules = $allDaySchedules->filter(function ($schedule) use ($tafsirGroupingService, $currentDayOfWeek, $currentTime): bool {
            $time = $tafsirGroupingService->resolveSessionTime($schedule, $currentDayOfWeek);

            return $time['ends_at'] !== null && $time['ends_at'] < $currentTime;
        })->values();
        $todayStart = Carbon::parse($currentDate, 'Asia/Jakarta')->startOfDay();
        $todayEnd = Carbon::parse($currentDate, 'Asia/Jakarta')->endOfDay();
        $agendaEvents = $agendaService->eventsForRange(
            $schedules
                ->map(fn ($schedule) => $schedule->teacherAssignment?->classSubject?->classroomTerm)
                ->filter()
                ->unique('id')
                ->values(),
            $todayStart,
            $todayEnd,
        );
        $scheduleTermIds = $schedules
            ->map(fn ($schedule) => $schedule->teacherAssignment?->classSubject?->classroomTerm?->academic_term_id)
            ->filter()
            ->unique()
            ->values();
        $isSchoolHoliday = SchoolHoliday::query()
            ->whereIn('academic_term_id', $scheduleTermIds)
            ->whereDate('holiday_date', $currentDate)
            ->exists();

        // Tafsir dibuat serentak untuk beberapa kelas. Agenda hanya membebaskan
        // sesi tersebut bila seluruh kelas dalam kelompok Tafsir tercakup.
        $tafsirAgendaByScheduleId = [];
        $tafsirGroups = $tafsirGroupingService->simultaneousGroupsForDate($schedules, $currentDate);
        $tafsirGroupByScheduleId = [];
        foreach ($tafsirGroups as $group) {
            $terms = $group['schedules']
                ->map(fn ($schedule) => $schedule->teacherAssignment?->classSubject?->classroomTerm)
                ->filter()
                ->unique('id')
                ->values();
            $agenda = $agendaService->forClassroomTerms($agendaEvents, $terms, $currentDate);
            foreach ($group['schedules'] as $schedule) {
                $tafsirGroupByScheduleId[$schedule->id] = $group;
                if ($agenda !== null) {
                    $tafsirAgendaByScheduleId[$schedule->id] = $agenda;
                }
            }
        }

        $attendanceStatuses = $attendanceClient->statusesForTeachers(
            $schedules
                ->map(fn ($schedule) => $schedule->teacherAssignment?->teacher)
                ->filter()
                ->unique('id')
                ->values(),
            Carbon::parse($currentDate, 'Asia/Jakarta')->startOfDay(),
            Carbon::parse($currentDate, 'Asia/Jakarta')->endOfDay(),
        );

        $missingJournals = [];
        $handledTafsirGroups = [];

        foreach ($schedules as $schedule) {
            // Agenda tanpa KBM dan libur sekolah bukan jurnal yang perlu ditagih.
            if ($isSchoolHoliday) {
                continue;
            }

            $assignment = $schedule->teacherAssignment;
            $session = $schedule->classSession;
            $tafsirGroup = $tafsirGroupByScheduleId[$schedule->id] ?? null;

            if ($tafsirGroup !== null) {
                if (isset($handledTafsirGroups[$tafsirGroup['key']])) {
                    continue;
                }
                $handledTafsirGroups[$tafsirGroup['key']] = true;
            }

            $groupSchedules = $tafsirGroup['schedules'] ?? collect([$schedule]);
            $assignmentIds = $groupSchedules->pluck('diniyyah_teacher_assignment_id')->unique()->values();
            $journalExists = DiniyyahClassJournal::whereIn('diniyyah_teacher_assignment_id', $assignmentIds)
                ->where('date', $currentDate)
                ->get()
                ->contains(fn ($journal) => $groupSchedules->contains(fn ($item) => $tafsirGroupingService->journalMatchesSchedule($journal, $item)));

            if (!$journalExists && $assignment->teacher) {
                $classroomTerm = $assignment->classSubject?->classroomTerm;
                $agenda = $tafsirGroup !== null
                    ? ($tafsirAgendaByScheduleId[$schedule->id] ?? null)
                    : ($classroomTerm
                        ? $agendaService->forClassroomTerm($agendaEvents, $classroomTerm, $currentDate)
                        : null);
                if ($agenda !== null) {
                    continue;
                }

                $teacherAttendance = $attendanceStatuses[(string) $assignment->teacher->id] ?? null;
                if (($teacherAttendance['available'] ?? false)
                    && $attendanceClient->isExempt($teacherAttendance['statuses'][$currentDate] ?? null)) {
                    continue;
                }

                $missingJournals[] = [
                    'teacher_name' => $assignment->teacher->name,
                    'whatsapp' => $assignment->teacher->whatsapp ?? $assignment->teacher->phone ?? '',
                    'class_name' => $tafsirGroup !== null
                        ? $groupSchedules->map(fn ($item) => $item->teacherAssignment?->classSubject?->classroomTerm?->name)->filter()->unique()->implode(', ')
                        : ($assignment->classSubject->classroomTerm->name ?? 'Unknown'),
                    'subject' => $tafsirGroup !== null ? 'Tafsir' : ($assignment->classSubject->subject->name ?? 'Unknown'),
                    'session_name' => $tafsirGroup !== null ? 'Tafsir serentak' : $session->session_name,
                    'starts_at' => $tafsirGroup['starts_at'] ?? $session->starts_at,
                    'ends_at' => $tafsirGroup['ends_at'] ?? $session->ends_at,
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'date' => $currentDate,
            'time' => $currentTime,
            'missing_count' => count($missingJournals),
            'data' => $missingJournals
        ]);
    }

}
