<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahKkmScope;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\ReportCard;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DiniyyahKkmAssignmentRoster
{
    public function __construct(private DiniyyahKkmResolver $resolver) {}

    public function assignments(AcademicTerm $term): Builder
    {
        return DiniyyahTeacherAssignment::query()
            ->when($term->ends_at, fn ($q) => $q->where(fn ($q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', $term->ends_at)))
            ->when($term->starts_at, fn ($q) => $q->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $term->starts_at)));
    }

    public function eligibleTeachers(DiniyyahKkmScope $scope): Builder
    {
        $subjectIds = $this->resolver->classSubjects($scope)->where('is_active', true)
            ->whereHas('classroomTerm.classroom')->pluck('id');

        return Teacher::query()->where('status', 'active')->whereHas('user', fn ($q) => $q->role('guru'))
            ->whereIn('id', $this->assignments($scope->academicTerm)->whereIn('diniyyah_class_subject_id', $subjectIds)->select('teacher_id'));
    }

    /** Teaching scopes exist independently of whether a KKM has been assigned or approved. */
    public function teachingScopes(User $user): Collection
    {
        $teacher = $user->teacher;
        if (! $user->hasRole('guru') || ! $teacher || $teacher->status !== 'active') {
            return collect();
        }

        return DiniyyahTeacherAssignment::where('teacher_id', $teacher->id)
            ->whereHas('classSubject', fn ($q) => $q->where('is_active', true)->whereHas('classroomTerm.classroom'))
            ->with(['classSubject.classroomTerm.classroom', 'classSubject.classroomTerm.academicTerm'])->get()
            ->map(function ($assignment) {
                $subject = $assignment->classSubject;
                $term = $subject?->classroomTerm?->academicTerm;
                $level = DiniyyahKkmResolver::mustawa($subject?->classroomTerm?->classroom?->level_name);
                if (! $term || ! $level
                    || ($term->ends_at && $assignment->starts_at && $assignment->starts_at > $term->ends_at)
                    || ($term->starts_at && $assignment->ends_at && $assignment->ends_at < $term->starts_at)) {
                    return null;
                }

                return ['academic_term_id' => $term->id, 'subject_id' => $subject->subject_id, 'mustawa' => $level];
            })->filter()->unique(fn ($s) => implode(':', $s))->values();
    }

    /** Derived data only: visiting this roster must never create scopes or assignments. */
    public function forTerm(AcademicTerm $term, ?int $mustawa = null, ?int $subjectId = null): array
    {
        $subjects = DiniyyahClassSubject::with(['subject', 'classroomTerm.classroom'])
            ->where('is_active', true)->whereHas('classroomTerm', fn ($q) => $q->where('academic_term_id', $term->id))
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))->orderBy('id')->get();
        $scopes = DiniyyahKkmScope::with('assignedTeacher')->where('academic_term_id', $term->id)->get()
            ->keyBy(fn ($s) => $s->subject_id.':'.$s->mustawa);
        $assignments = $this->assignments($term)->whereIn('diniyyah_class_subject_id', $subjects->pluck('id'))
            ->with(['teacher.user', 'schedules.classSession'])->orderBy('id')->get()->groupBy('diniyyah_class_subject_id');
        $eligibleIds = Teacher::where('status', 'active')->whereHas('user', fn ($q) => $q->role('guru'))->pluck('id')->flip();
        $frozenByScope = $this->frozenByScope($term);
        $rows = $issues = [];
        foreach ($subjects as $subject) {
            $ct = $subject->classroomTerm;
            $level = DiniyyahKkmResolver::mustawa($ct?->classroom?->level_name);
            if (! $level) {
                $issues[] = ['class' => $ct?->name ?? 'Kelas tidak tersedia', 'subject' => $subject->subject?->name, 'level' => $ct?->classroom?->level_name];

                continue;
            }
            if ($mustawa && $level !== $mustawa) {
                continue;
            }
            $key = $subject->subject_id.':'.$level;
            if (! isset($rows[$key])) {
                $scope = $scopes->get($key) ?? new DiniyyahKkmScope(['academic_term_id' => $term->id, 'subject_id' => $subject->subject_id, 'mustawa' => $level]);
                $frozen = $frozenByScope[$key] ?? [];
                $rows[$key] = [
                    'key' => $key, 'class_subject_id' => $subject->id, 'subject_id' => $subject->subject_id,
                    'subject' => $subject->subject?->name, 'mustawa' => $level, 'scope_id' => $scope->id,
                    'assigned_teacher_id' => $scope->assigned_teacher_id, 'assigned_teacher' => $scope->assignedTeacher?->name,
                    'classes' => [], 'teachers' => [], 'frozen' => $frozen,
                    'blocked' => ! $term->kkm_workflow_enabled ? 'Alur KKM semester ini belum aktif.' : ($frozen ? 'Terkunci oleh: '.implode(', ', $frozen) : null),
                ];
            }
            $rows[$key]['classes'][$ct->id] = $ct->name;
            foreach ($assignments->get($subject->id, collect()) as $assignment) {
                if (! $eligibleIds->has($assignment->teacher_id)) {
                    continue;
                }
                $teacher = $assignment->teacher;
                $teacherId = $teacher->id;
                if (! isset($rows[$key]['teachers'][$teacherId])) {
                    $rows[$key]['teachers'][$teacherId] = ['id' => $teacherId, 'name' => $teacher->name, 'gender' => $teacher->gender, 'classes' => []];
                }
                $rows[$key]['teachers'][$teacherId]['classes'][] = [
                    'name' => $ct->name, 'role' => $assignment->assignment_role === 'assistant' ? 'Pendamping' : 'Utama',
                    'starts_at' => $assignment->starts_at?->toDateString(), 'ends_at' => $assignment->ends_at?->toDateString(),
                    'schedules' => $this->schedules($assignment, $term),
                ];
            }
        }
        foreach ($rows as &$row) {
            $row['classes'] = array_values(array_unique($row['classes']));
            $row['teachers'] = collect($row['teachers'])->sortBy('name')->values()->all();
            // Signed preview snapshots detect changes to candidates, gender, classes and schedules.
            $snapshot = $row;
            unset($snapshot['scope_id']);
            $row['fingerprint'] = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
        }
        unset($row);

        return ['rows' => collect($rows)->sortBy([['mustawa', 'asc'], ['subject', 'asc']])->values()->all(), 'issues' => $issues];
    }

    /** Load document blockers together, including archived subjects/classes, without a query per scope. */
    private function frozenByScope(AcademicTerm $term): array
    {
        $classScopes = [];
        foreach (DiniyyahClassSubject::withTrashed()->with(['classroomTerm.classroom' => fn ($q) => $q->withTrashed()])
            ->whereHas('classroomTerm', fn ($q) => $q->where('academic_term_id', $term->id))->get() as $subject) {
            $level = DiniyyahKkmResolver::mustawa($subject->classroomTerm?->classroom?->level_name);
            if ($level) {
                $classScopes[$subject->classroom_term_id][$subject->subject_id.':'.$level] = true;
            }
        }
        $documents = DiniyyahLedgerSnapshot::whereIn('classroom_term_id', array_keys($classScopes))->whereIn('status', ['locked', 'published'])->orderBy('id')->get()
            ->map(fn ($s) => ['classroom_term_id' => $s->classroom_term_id, 'label' => 'Leger #'.$s->id.' '.$s->title]);
        $documents = $documents->concat(ReportCard::whereIn('classroom_term_id', array_keys($classScopes))->where('report_type', 'diniyyah')->whereIn('status', ['locked', 'published'])->orderBy('id')->get()
            ->map(fn ($r) => ['classroom_term_id' => $r->classroom_term_id, 'label' => 'Rapor #'.$r->id]));
        $frozen = [];
        foreach ($documents as $document) {
            foreach (array_keys($classScopes[$document['classroom_term_id']]) as $key) {
                $frozen[$key][] = $document['label'];
            }
        }

        return $frozen;
    }

    private function schedules(DiniyyahTeacherAssignment $assignment, AcademicTerm $term): array
    {
        $start = collect([$term->starts_at, $assignment->starts_at])->filter()->max();
        $end = collect([$term->ends_at, $assignment->ends_at])->filter()->min();
        $days = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

        return $assignment->schedules->filter(function ($schedule) use ($start, $end) {
            if (! $schedule->classSession || $schedule->classSession->is_break) {
                return false;
            }

            return $schedule->version_status === 'legacy'
                || ((! $end || ! $schedule->effective_from || $schedule->effective_from <= $end)
                    && (! $start || ! $schedule->effective_until || $schedule->effective_until >= $start));
        })->sortBy(fn ($s) => $s->day_of_week.'-'.$s->classSession->starts_at)->map(fn ($s) => ($days[$s->day_of_week] ?? '?').' '.substr($s->classSession->starts_at, 0, 5).'–'.substr($s->classSession->ends_at, 0, 5)
            .($s->version_status === 'active' ? ' ('.($s->effective_from?->toDateString() ?? 'awal').' s.d. '.($s->effective_until?->toDateString() ?? 'akhir').')' : '')
        )->unique()->values()->all();
    }
}
