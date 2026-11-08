<?php

namespace App\Services;

use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahSubject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DiniyyahAssessmentAccess
{
    public function __construct(private DiniyyahLedgerAccess $homerooms) {}

    public function classSubjects(User $user): Builder
    {
        $teacher = $user->teacher;
        $query = DiniyyahClassSubject::where('is_active', true);
        if (! $user->hasRole('guru') || ! $teacher || $teacher->status !== 'active') {
            return $query->whereIn('id', []);
        }
        $sulukIds = DiniyyahSubject::get(['id', 'code', 'name'])
            ->filter(fn ($subject) => $subject->isSuluk())->pluck('id');

        return $query->where(fn ($q) => $q
            ->where(fn ($q) => $q->whereIn('subject_id', $sulukIds)->whereIn('classroom_term_id', $this->homerooms->classrooms($user)->select('id')))
            ->orWhere(fn ($q) => $q->whereNotIn('subject_id', $sulukIds)
                ->whereHas('teacherAssignments', fn ($q) => $q->where('teacher_id', $teacher->id)
                    ->where(fn ($q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', today()))
                    ->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', today())))));
    }
}
