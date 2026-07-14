<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DiniyyahTeachingSchedule;
use App\Models\DiniyyahClassJournal;
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

        // Get schedules for today where the class session has ended
        $schedules = DiniyyahTeachingSchedule::with([
                'teacherAssignment.teacher',
                'teacherAssignment.classSubject.subject',
                'teacherAssignment.classSubject.classroomTerm.classroom',
                'classSession'
            ])
            ->where('day_of_week', $currentDayOfWeek)
            ->whereHas('classSession', function($query) use ($currentTime) {
                $query->where('ends_at', '<', $currentTime);
            })
            ->get();

        $missingJournals = [];

        foreach ($schedules as $schedule) {
            $assignment = $schedule->teacherAssignment;
            $session = $schedule->classSession;

            // Check if journal exists
            $journalExists = DiniyyahClassJournal::where('diniyyah_teacher_assignment_id', $assignment->id)
                ->where('date', $currentDate)
                ->where('session_hour', (string)$session->session_name)
                ->exists();

            if (!$journalExists && $assignment->teacher) {
                $missingJournals[] = [
                    'teacher_name' => $assignment->teacher->name,
                    'whatsapp' => $assignment->teacher->whatsapp ?? $assignment->teacher->phone ?? '',
                    'class_name' => $assignment->classSubject->classroomTerm->name ?? 'Unknown',
                    'subject' => $assignment->classSubject->subject->name ?? 'Unknown',
                    'session_name' => $session->session_name,
                    'starts_at' => $session->starts_at,
                    'ends_at' => $session->ends_at,
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
