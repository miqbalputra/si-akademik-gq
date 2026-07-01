<?php

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\DiniyyahAssessmentSet;
use Illuminate\Support\Collection;

class DiniyyahInputProgressService
{
    public function summaries(): Collection
    {
        return DiniyyahAssessmentSet::query()
            ->with([
                'classSubject.classroomTerm' => function ($query) {
                    $query->withCount(['enrollments as active_enrollments_count' => function ($q) {
                        $q->where('status', 'active');
                    }]);
                },
                'classSubject.subject',
                'classSubject.teacherAssignments.teacher',
                'results'
            ])
            ->latest()
            ->get()
            ->map(fn (DiniyyahAssessmentSet $assessmentSet) => $this->summary($assessmentSet));
    }

    /** @return array<string, mixed> */
    public function summary(DiniyyahAssessmentSet $assessmentSet): array
    {
        $assessmentSet->loadMissing([
            'classSubject.classroomTerm' => function ($query) {
                $query->withCount(['enrollments as active_enrollments_count' => function ($q) {
                    $q->where('status', 'active');
                }]);
            },
            'classSubject.subject',
            'classSubject.teacherAssignments.teacher',
            'results'
        ]);

        $totalStudents = $assessmentSet->classSubject?->classroomTerm?->active_enrollments_count;

        if ($totalStudents === null) {
            $totalStudents = ClassEnrollment::query()
                ->where('classroom_term_id', $assessmentSet->classSubject->classroom_term_id)
                ->where('status', 'active')
                ->count();
        }

        $completeStudents = $assessmentSet->results->where('is_complete', true)->count();
        $incompleteStudents = max($totalStudents - $completeStudents, 0);
        $progressPercentage = $totalStudents > 0 ? round($completeStudents / $totalStudents * 100, 2) : 0.0;

        return [
            'assessment_set' => $assessmentSet,
            'classroom_name' => $assessmentSet->classSubject?->classroomTerm?->name,
            'subject_name' => $assessmentSet->classSubject?->subject?->name,
            'teacher_names' => $assessmentSet->classSubject?->teacherAssignments
                ?->pluck('teacher.name')
                ->filter()
                ->unique()
                ->values()
                ->all() ?? [],
            'total_students' => $totalStudents,
            'complete_students' => $completeStudents,
            'incomplete_students' => $incompleteStudents,
            'progress_percentage' => $progressPercentage,
            'status' => $assessmentSet->status,
        ];
    }
}
