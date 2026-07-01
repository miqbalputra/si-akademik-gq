<?php

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
use App\Models\ClassroomTerm;
use App\Models\SchoolEvent;
use App\Models\SchoolEventResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GuardianSchoolEventResponseController extends Controller
{
    public function store(Request $request, SchoolEvent $event): RedirectResponse
    {
        abort_unless($request->user()->hasRole('wali_santri'), 403);

        $guardian = $request->user()->guardian;
        abort_unless($guardian, 403);

        $validated = $request->validate([
            'attendance_status' => ['required', 'in:attending,permission,not_attending'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $studentIds = $guardian->students()
            ->wherePivot('can_login', true)
            ->pluck('students.id');

        $classroomTerms = ClassroomTerm::query()
            ->with('classroom')
            ->whereIn('id', ClassEnrollment::query()
                ->whereIn('student_id', $studentIds)
                ->where('status', 'active')
                ->pluck('classroom_term_id'))
            ->get();

        $visibleEvent = SchoolEvent::query()
            ->whereKey($event->id)
            ->visibleToGuardians()
            ->relevantToClassroomTerms($classroomTerms)
            ->firstOrFail();

        SchoolEventResponse::updateOrCreate(
            [
                'school_event_id' => $visibleEvent->id,
                'guardian_id' => $guardian->id,
            ],
            [
                'attendance_status' => $validated['attendance_status'],
                'notes' => $validated['notes'] ?? null,
                'responded_at' => now(),
            ],
        );

        return back()->with('status', 'Respon kehadiran event berhasil disimpan.');
    }
}
