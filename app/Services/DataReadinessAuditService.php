<?php

namespace App\Services;

use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassSubject;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DataReadinessAuditService
{
    /** @return array<string, mixed> */
    public function audit(): array
    {
        $sections = [
            $this->issue(
                key: 'students_without_guardians',
                title: 'Santri belum punya wali',
                description: 'Santri aktif yang belum punya relasi wali santri.',
                query: Student::query()
                    ->where('status', 'active')
                    ->whereDoesntHave('guardians'),
                sampleColumn: 'name',
            ),
            $this->issue(
                key: 'students_without_active_enrollment',
                title: 'Santri belum masuk kelas',
                description: 'Santri aktif yang belum masuk enrollment kelas aktif.',
                query: Student::query()
                    ->where('status', 'active')
                    ->whereDoesntHave('enrollments', fn (Builder $query) => $query->where('status', 'active')),
                sampleColumn: 'name',
            ),
            $this->issue(
                key: 'guardians_without_users',
                title: 'Wali belum punya akun',
                description: 'Wali aktif yang belum tersambung ke akun login.',
                query: Guardian::query()
                    ->where('status', 'active')
                    ->whereNull('user_id'),
                sampleColumn: 'name',
            ),
            $this->issue(
                key: 'guardians_without_students',
                title: 'Wali belum punya anak',
                description: 'Wali aktif yang belum tersambung ke data santri.',
                query: Guardian::query()
                    ->where('status', 'active')
                    ->whereDoesntHave('students'),
                sampleColumn: 'name',
            ),
            $this->issue(
                key: 'teachers_without_users',
                title: 'Guru belum punya akun',
                description: 'Guru aktif yang belum tersambung ke akun login.',
                query: Teacher::query()
                    ->where('status', 'active')
                    ->whereNull('user_id'),
                sampleColumn: 'name',
            ),
            $this->issue(
                key: 'teachers_without_diniyyah_assignments',
                title: 'Guru belum punya tugas Diniyyah',
                description: 'Guru aktif yang belum punya penugasan mapel/kelas Diniyyah.',
                query: Teacher::query()
                    ->where('status', 'active')
                    ->whereHas('teacherRoles', fn ($q) => $q->where('role_type', 'diniyyah_subject_teacher'))
                    ->whereDoesntHave('diniyyahTeacherAssignments'),
                sampleColumn: 'name',
            ),
            $this->issue(
                key: 'classroom_terms_without_enrollments',
                title: 'Kelas periode belum punya santri',
                description: 'Kelas aktif pada periode tertentu yang belum punya santri.',
                query: ClassroomTerm::query()
                    ->where('status', 'active')
                    ->whereDoesntHave('enrollments'),
                sampleColumn: 'name',
            ),
            $this->issue(
                key: 'classroom_terms_without_subjects',
                title: 'Kelas periode belum punya mapel',
                description: 'Kelas aktif yang belum punya mata pelajaran Diniyyah.',
                query: ClassroomTerm::query()
                    ->where('status', 'active')
                    ->whereDoesntHave('diniyyahClassSubjects'),
                sampleColumn: 'name',
            ),
            $this->classSubjectIssue(
                key: 'class_subjects_without_teachers',
                title: 'Mapel kelas belum punya guru',
                description: 'Mata pelajaran aktif di kelas yang belum punya guru pengampu.',
                query: DiniyyahClassSubject::query()
                    ->with(['classroomTerm', 'subject'])
                    ->where('is_active', true)
                    ->whereDoesntHave('teacherAssignments'),
            ),
            $this->classSubjectIssue(
                key: 'class_subjects_without_assessment_sets',
                title: 'Mapel kelas belum punya set nilai',
                description: 'Mata pelajaran aktif di kelas yang belum punya set penilaian.',
                query: DiniyyahClassSubject::query()
                    ->with(['classroomTerm', 'subject'])
                    ->where('is_active', true)
                    ->whereDoesntHave('assessmentSets'),
            ),
            $this->assessmentSetIssue(
                key: 'assessment_sets_without_components',
                title: 'Set nilai belum punya komponen',
                description: 'Set penilaian aktif yang belum punya komponen nilai.',
                query: DiniyyahAssessmentSet::query()
                    ->with(['classSubject.classroomTerm', 'classSubject.subject'])
                    ->whereIn('status', ['draft', 'active'])
                    ->whereDoesntHave('components'),
            ),
            $this->assessmentSetIssue(
                key: 'assessment_sets_not_active',
                title: 'Set nilai belum aktif',
                description: 'Set penilaian draft yang belum muncul di halaman input guru.',
                query: DiniyyahAssessmentSet::query()
                    ->with(['classSubject.classroomTerm', 'classSubject.subject'])
                    ->where('status', 'draft'),
            ),
        ];

        $totalIssues = collect($sections)->sum('count');
        $readySections = collect($sections)->where('count', 0)->count();
        $readinessPercentage = count($sections) > 0
            ? round($readySections / count($sections) * 100, 2)
            : 100.0;

        return [
            'sections' => $sections,
            'total_issues' => $totalIssues,
            'ready_sections' => $readySections,
            'total_sections' => count($sections),
            'readiness_percentage' => $readinessPercentage,
            'status' => $totalIssues === 0 ? 'ready' : 'needs_attention',
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @return array<string, mixed>
     */
    private function issue(string $key, string $title, string $description, Builder $query, string $sampleColumn): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'count' => (clone $query)->count(),
            'samples' => (clone $query)
                ->limit(8)
                ->pluck($sampleColumn)
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Builder<DiniyyahClassSubject>  $query
     * @return array<string, mixed>
     */
    private function classSubjectIssue(string $key, string $title, string $description, Builder $query): array
    {
        $records = (clone $query)->limit(8)->get();

        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'count' => (clone $query)->count(),
            'samples' => $records
                ->map(fn (DiniyyahClassSubject $classSubject): string => trim(($classSubject->classroomTerm?->name ?? '-').' - '.($classSubject->subject?->name ?? '-')))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Builder<DiniyyahAssessmentSet>  $query
     * @return array<string, mixed>
     */
    private function assessmentSetIssue(string $key, string $title, string $description, Builder $query): array
    {
        $records = (clone $query)->limit(8)->get();

        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'count' => (clone $query)->count(),
            'samples' => $records
                ->map(function (DiniyyahAssessmentSet $assessmentSet): string {
                    $classSubject = $assessmentSet->classSubject;

                    return trim($assessmentSet->title.' - '.($classSubject?->classroomTerm?->name ?? '-').' - '.($classSubject?->subject?->name ?? '-'));
                })
                ->values()
                ->all(),
        ];
    }
}
