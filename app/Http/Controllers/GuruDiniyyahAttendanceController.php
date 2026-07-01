<?php

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahStudentAttendance;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class GuruDiniyyahAttendanceController extends Controller
{
    public function edit(Request $request, DiniyyahAssessmentSet $assessmentSet): View
    {
        Gate::authorize('inputScores', $assessmentSet);
        $assessmentSet->loadMissing('classSubject.classroomTerm.academicTerm', 'classSubject.subject');
        
        $classSubject = $assessmentSet->classSubject;
        $classroomTerm = $classSubject->classroomTerm;

        $meetings = range(1, 40);

        $enrollments = ClassEnrollment::query()
            ->with('student')
            ->where('classroom_term_id', $classroomTerm->id)
            ->where('status', 'active')
            ->orderBy('roll_number')
            ->orderBy('student_id')
            ->get();

        $attendances = DiniyyahStudentAttendance::query()
            ->where('diniyyah_class_subject_id', $classSubject->id)
            ->whereIn('class_enrollment_id', $enrollments->pluck('id'))
            ->get()
            ->keyBy(fn (DiniyyahStudentAttendance $attendance) => $attendance->class_enrollment_id.'-'.$attendance->meeting_number);

        [$studentTotals, $classTotals] = $this->totalsFor($enrollments, $meetings, $attendances);

        return view('guru.diniyyah-attendance.edit', [
            'assessmentSet' => $assessmentSet,
            'classSubject' => $classSubject,
            'classroomTerm' => $classroomTerm,
            'enrollments' => $enrollments,
            'attendances' => $attendances,
            'studentTotals' => $studentTotals,
            'classTotals' => $classTotals,
            'meetings' => $meetings,
            'canUpdate' => in_array($assessmentSet->status, ['active', 'needs_revision'], true) || $request->user()->hasAnyRole(['admin', 'kabag_diniyyah']),
        ]);
    }

    public function updateSingle(Request $request, DiniyyahAssessmentSet $assessmentSet): JsonResponse
    {
        Gate::authorize('inputScores', $assessmentSet);
        $canUpdate = in_array($assessmentSet->status, ['active', 'needs_revision'], true) || $request->user()->hasAnyRole(['admin', 'kabag_diniyyah']);
        abort_unless($canUpdate, 403);

        $validated = $request->validate([
            'class_enrollment_id' => ['required', 'exists:class_enrollments,id'],
            'meeting_number' => ['required', 'integer', 'min:1', 'max:40'],
            'code' => ['required', 'string', Rule::in(array_keys(\App\Models\StudentAttendance::codeOptions()))],
        ]);

        $classSubject = $assessmentSet->classSubject;

        // Verify the enrollment belongs to this class
        $enrollment = ClassEnrollment::where('id', $validated['class_enrollment_id'])
            ->where('classroom_term_id', $classSubject->classroom_term_id)
            ->firstOrFail();

        $status = \App\Models\StudentAttendance::statusFromCode($validated['code']);

        DiniyyahStudentAttendance::updateOrCreate(
            [
                'diniyyah_class_subject_id' => $classSubject->id,
                'class_enrollment_id' => $enrollment->id,
                'meeting_number' => $validated['meeting_number'],
            ],
            [
                'student_id' => $enrollment->student_id,
                'status' => $status,
                'input_by' => $request->user()->id,
            ]
        );

        $calculator = new \App\Services\DiniyyahScoreCalculator();
        $calculator->syncAttendanceScores($assessmentSet, $enrollment);
        $calculator->calculate($assessmentSet, $enrollment);

        return response()->json([
            'success' => true,
            'message' => 'Tersimpan otomatis'
        ]);
    }

    /**
     * @param  Collection<int, ClassEnrollment>  $enrollments
     * @param  array<int>  $meetings
     * @param  Collection<string, DiniyyahStudentAttendance>  $attendances
     * @return array{array<int, array{sick: int, permission: int, absent: int}>, array{sick: int, permission: int, absent: int}}
     */
    private function totalsFor(Collection $enrollments, array $meetings, Collection $attendances): array
    {
        $studentTotals = [];
        $classTotals = ['sick' => 0, 'permission' => 0, 'absent' => 0, 'holiday' => 0];

        foreach ($enrollments as $enrollment) {
            $totals = ['sick' => 0, 'permission' => 0, 'absent' => 0, 'holiday' => 0];

            foreach ($meetings as $meeting) {
                $attendance = $attendances->get($enrollment->id.'-'.$meeting);
                $status = $attendance?->status;

                if ($status === DiniyyahStudentAttendance::STATUS_SICK) {
                    $totals['sick']++;
                    $classTotals['sick']++;
                } elseif ($status === DiniyyahStudentAttendance::STATUS_PERMISSION) {
                    $totals['permission']++;
                    $classTotals['permission']++;
                } elseif ($status === DiniyyahStudentAttendance::STATUS_ABSENT) {
                    $totals['absent']++;
                    $classTotals['absent']++;
                } elseif ($status === DiniyyahStudentAttendance::STATUS_HOLIDAY) {
                    $totals['holiday']++;
                    $classTotals['holiday']++;
                }
            }

            $studentTotals[$enrollment->id] = $totals;
        }

        return [$studentTotals, $classTotals];
    }
}
