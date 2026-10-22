<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\ClassroomTerm;
use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahClassSubject;
use App\Models\DiniyyahKkmScope;
use App\Models\DiniyyahLedgerSnapshot;
use App\Models\ReportCard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiniyyahKkmResolver
{
    public static function mustawa(?string $name): ?int
    {
        return preg_match('/^(?:mustawa|m)\s*([1-6])$/i', trim($name ?? ''), $matches) ? (int) $matches[1] : null;
    }

    public function required(DiniyyahClassSubject $subject): bool
    {
        return (bool) $subject->classroomTerm?->academicTerm?->kkm_workflow_enabled;
    }

    public function scopeFor(DiniyyahClassSubject $subject, bool $create = false): ?DiniyyahKkmScope
    {
        $classroomTerm = $subject->classroomTerm;
        $level = self::mustawa($classroomTerm?->classroom?->level_name);
        if (! $classroomTerm || ! $level) {
            return null;
        }
        $key = ['academic_term_id' => $classroomTerm->academic_term_id, 'subject_id' => $subject->subject_id, 'mustawa' => $level];

        return $create ? DiniyyahKkmScope::firstOrCreate($key) : DiniyyahKkmScope::firstWhere($key);
    }

    public function classSubjects(DiniyyahKkmScope $scope, bool $includeArchived = false): Builder
    {
        $classroomIds = Classroom::withTrashed()->get(['id', 'level_name'])->filter(fn ($c) => self::mustawa($c->level_name) === (int) $scope->mustawa)->pluck('id');

        return DiniyyahClassSubject::query()->when($includeArchived, fn ($q) => $q->withTrashed())->where('subject_id', $scope->subject_id)
            ->whereHas('classroomTerm', fn ($q) => $q->where('academic_term_id', $scope->academic_term_id)->whereIn('classroom_id', $classroomIds));
    }

    public function resolve(DiniyyahClassSubject $subject, ?float $legacy = null): array
    {
        if (! $this->required($subject)) {
            return ['kkm' => $legacy ?? $subject->kkm, 'kkm_revision_id' => null];
        }
        $scope = $this->scopeFor($subject);
        $revision = $scope?->activeRevision;
        if ($subject->subject?->schoolKkm() !== null && ! $revision?->is_school_fixed) {
            $revision = app(DiniyyahSchoolKkm::class)->ensure($subject);
            if (! $revision) {
                $historicalScope = $scope ?? new DiniyyahKkmScope(['academic_term_id' => $subject->classroomTerm->academic_term_id, 'subject_id' => $subject->subject_id, 'mustawa' => self::mustawa($subject->classroomTerm->classroom?->level_name)]);
                if ($this->frozen($historicalScope)) {
                    return ['kkm' => $legacy ?? $subject->kkm, 'kkm_revision_id' => null];
                }
            }
        }

        return ['kkm' => $revision?->final_kkm, 'kkm_revision_id' => $revision?->id];
    }

    public function apply(DiniyyahClassSubject|DiniyyahAssessmentSet $model): void
    {
        $subject = $model instanceof DiniyyahClassSubject ? $model : $model->classSubject;
        if ($subject && $this->required($subject)) {
            $model->forceFill($this->resolve($subject));
        }
    }

    public function frozen(DiniyyahKkmScope $scope): array
    {
        $classIds = $this->classSubjects($scope, true)->pluck('classroom_term_id');
        $ledgers = DiniyyahLedgerSnapshot::whereIn('classroom_term_id', $classIds)->whereIn('status', ['locked', 'published'])->get()->map(fn ($s) => 'Leger #'.$s->id.' '.$s->title);
        $reports = ReportCard::whereIn('classroom_term_id', $classIds)->where('report_type', 'diniyyah')->whereIn('status', ['locked', 'published'])->get()->map(fn ($r) => 'Rapor #'.$r->id);

        return $ledgers->merge($reports)->all();
    }

    public function ensureOpen(DiniyyahKkmScope $scope): void
    {
        $documents = $this->frozen($scope);
        if ($documents) {
            throw ValidationException::withMessages(['kkm' => 'KKM terkunci oleh: '.implode(', ', $documents).'.']);
        }
    }

    /** All finalization paths acquire these same rows, in ID order, before locking documents. */
    public function withClassLocks(ClassroomTerm $classroomTerm, callable $callback): mixed
    {
        return DB::transaction(function () use ($classroomTerm, $callback) {
            if ($classroomTerm->academicTerm?->kkm_workflow_enabled) {
                $subjects = $classroomTerm->diniyyahClassSubjects()->with('subject')->where('is_active', true)->get();
                foreach ($subjects as $subject) {
                    if ($subject->subject?->schoolKkm() !== null) {
                        $this->resolve($subject);
                    }
                }
                $scopes = $subjects->map(fn ($s) => $this->scopeFor($s, true));
                if ($scopes->contains(null)) {
                    throw ValidationException::withMessages(['kkm' => 'Jenjang kelas belum dikenali. Lengkapi Tingkat dengan Mustawa 1–6.']);
                }
                DiniyyahKkmScope::whereIn('id', $scopes->pluck('id'))->orderBy('id')->lockForUpdate()->get();
            }

            return $callback();
        }, 3);
    }

    public function ensureApproved(DiniyyahAssessmentSet $set): array
    {
        $resolved = $this->resolve($set->classSubject, $set->kkm === null ? null : (float) $set->kkm);
        if ($this->required($set->classSubject) && ! $resolved['kkm_revision_id']) {
            throw ValidationException::withMessages(['kkm' => 'KKM '.$set->title.' belum disetujui. Input draf tetap tersedia.']);
        }

        return $resolved;
    }

    public function withAssessmentLock(DiniyyahAssessmentSet $set, callable $callback): mixed
    {
        return DB::transaction(function () use ($set, $callback) {
            if ($set->classSubject?->subject?->schoolKkm() !== null) {
                $this->resolve($set->classSubject, $set->kkm === null ? null : (float) $set->kkm);
            }
            if ($this->required($set->classSubject) && ($scope = $this->scopeFor($set->classSubject, true))) {
                DiniyyahKkmScope::whereKey($scope->id)->lockForUpdate()->firstOrFail();
            }

            return $callback();
        }, 3);
    }
}
