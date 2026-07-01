<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'school_id',
    'academic_term_id',
    'title',
    'event_type',
    'target_scope',
    'target_level_name',
    'target_gender_group',
    'starts_on',
    'ends_on',
    'location',
    'description',
    'show_to_teachers',
    'show_to_guardians',
])]
class SchoolEvent extends Model
{
    use HasFactory;

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function targetClassroomTerms(): BelongsToMany
    {
        return $this->belongsToMany(ClassroomTerm::class, 'school_event_targets')
            ->withTimestamps();
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SchoolEventResponse::class);
    }

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'show_to_teachers' => 'boolean',
            'show_to_guardians' => 'boolean',
        ];
    }

    public function scopeVisibleToTeachers(Builder $query): Builder
    {
        return $query->where('show_to_teachers', true);
    }

    public function scopeVisibleToGuardians(Builder $query): Builder
    {
        return $query->where('show_to_guardians', true);
    }

    public function scopeOverlapping(Builder $query, CarbonInterface $startsOn, CarbonInterface $endsOn): Builder
    {
        return $query
            ->whereDate('starts_on', '<=', $endsOn->toDateString())
            ->whereDate('ends_on', '>=', $startsOn->toDateString());
    }

    public function scopeRelevantToClassroomTerms(Builder $query, Collection|array $classroomTerms): Builder
    {
        $terms = collect($classroomTerms)
            ->filter()
            ->values();

        $ids = $terms
            ->map(fn ($term) => (int) (is_object($term) ? $term->id : $term))
            ->unique()
            ->values()
            ->all();
        $levelNames = $terms
            ->map(fn ($term) => is_object($term) ? $term->classroom?->level_name : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $genderGroups = $terms
            ->map(fn ($term) => is_object($term) ? $term->classroom?->gender_group : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $query->where(function (Builder $query) use ($ids, $levelNames, $genderGroups): void {
            $query->where('target_scope', 'all');

            if ($ids !== []) {
                $query->orWhere(function (Builder $query) use ($ids): void {
                    $query->where('target_scope', 'classes')
                        ->whereHas('targetClassroomTerms', function (Builder $query) use ($ids): void {
                            $query->whereIn('classroom_terms.id', $ids);
                        });
                });
            }

            if ($levelNames !== []) {
                $query->orWhere(function (Builder $query) use ($levelNames): void {
                    $query->where('target_scope', 'level')
                        ->whereIn('target_level_name', $levelNames);
                });
            }

            if ($genderGroups !== []) {
                $query->orWhere(function (Builder $query) use ($genderGroups): void {
                    $query->where('target_scope', 'gender')
                        ->whereIn('target_gender_group', $genderGroups);
                });
            }

            if ($levelNames !== [] && $genderGroups !== []) {
                $query->orWhere(function (Builder $query) use ($levelNames, $genderGroups): void {
                    $query->where('target_scope', 'level_gender')
                        ->whereIn('target_level_name', $levelNames)
                        ->whereIn('target_gender_group', $genderGroups);
                });
            }
        });
    }

    public function targetScopeLabel(): string
    {
        return match ($this->target_scope) {
            'classes' => 'Kelas Tertentu',
            'level' => 'Jenjang Tertentu',
            'gender' => 'Kelompok Gender',
            'level_gender' => 'Jenjang + Gender',
            default => 'Semua Sekolah',
        };
    }

    public function targetGenderLabel(): ?string
    {
        return match ($this->target_gender_group) {
            'male' => 'Ikhwan',
            'female' => 'Akhwat',
            'mixed' => 'Campuran',
            default => null,
        };
    }

    public function typeLabel(): string
    {
        return match ($this->event_type) {
            'outdoor' => 'Outdoor',
            'exam' => 'Ujian',
            'meeting' => 'Pertemuan',
            'religious' => 'Agenda Diniyyah',
            default => 'Agenda Sekolah',
        };
    }

    public function priorityKey(): string
    {
        return match ($this->event_type) {
            'exam' => 'high',
            'religious' => 'medium',
            'meeting' => 'medium',
            'outdoor' => 'medium',
            default => 'normal',
        };
    }

    public function priorityLabel(): string
    {
        return match ($this->priorityKey()) {
            'high' => 'Prioritas Tinggi',
            'medium' => 'Perlu Perhatian',
            default => 'Info Umum',
        };
    }

    public function targetSummary(int $limit = 2): string
    {
        $this->loadMissing('targetClassroomTerms.classroom');

        $targets = $this->targetClassroomTerms
            ->map(fn (ClassroomTerm $classroomTerm) => $classroomTerm->name)
            ->filter()
            ->values();

        if ($this->target_scope === 'level') {
            return 'Jenjang '.$this->target_level_name;
        }

        if ($this->target_scope === 'gender') {
            return $this->targetGenderLabel() ?? 'Kelompok gender';
        }

        if ($this->target_scope === 'level_gender') {
            $parts = collect([$this->target_level_name, $this->targetGenderLabel()])->filter()->implode(' - ');

            return $parts !== '' ? $parts : 'Jenjang + gender';
        }

        if ($targets->isEmpty()) {
            return 'Semua sekolah';
        }

        $visibleTargets = $targets->take($limit)->implode(', ');
        $remainingCount = $targets->count() - min($limit, $targets->count());

        return $remainingCount > 0
            ? $visibleTargets.' +'.$remainingCount.' kelas'
            : $visibleTargets;
    }
}
