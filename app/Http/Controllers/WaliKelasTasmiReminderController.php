<?php

namespace App\Http\Controllers;

use App\Models\HomeroomAssignment;
use App\Services\TasmiWaliReminderPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaliKelasTasmiReminderController extends Controller
{
    public function dismiss(Request $request, TasmiWaliReminderPreferenceService $preferences): JsonResponse
    {
        $user = $request->user();
        $teacher = $user?->teacher;
        abort_unless($user?->hasRole('guru') && $teacher, 403);
        abort_unless(HomeroomAssignment::query()->where('teacher_id', $teacher->id)->exists(), 403);

        $dismissedAt = $preferences->dismiss($user);

        return response()->json([
            'dismissed_at' => $dismissedAt->toIso8601String(),
        ]);
    }
}
