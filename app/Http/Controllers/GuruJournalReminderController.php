<?php

namespace App\Http\Controllers;

use App\Services\GuruJournalReminderPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuruJournalReminderController extends Controller
{
    public function snooze(Request $request, GuruJournalReminderPreferenceService $preferences): JsonResponse
    {
        $user = $request->user();

        abort_unless($user?->hasRole('guru') && $user->teacher, 403);

        $snoozedUntil = $preferences->snooze($user);

        return response()->json([
            'snoozed_until' => $snoozedUntil->toIso8601String(),
            'snoozed_until_label' => $snoozedUntil->format('H:i'),
        ]);
    }
}
