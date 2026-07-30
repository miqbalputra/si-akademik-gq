<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'classroom_term_id',
    'subject_id',
    'assessment_method',
    'kkm',
    'daily_weight',
    'exam_weight',
    'appears_on_ledger',
    'appears_on_report',
    'sort_order',
    'is_active',
    'created_by',
    'updated_by',
])]
class DiniyyahClassSubject extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'kkm' => 'decimal:2',
            'appears_on_ledger' => 'boolean',
            'appears_on_report' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function classroomTerm(): BelongsTo
    {
        return $this->belongsTo(ClassroomTerm::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(DiniyyahSubject::class, 'subject_id');
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(DiniyyahTeacherAssignment::class);
    }

    public function assessmentSets(): HasMany
    {
        return $this->hasMany(DiniyyahAssessmentSet::class);
    }

    /**
     * Opsi pencarian untuk Select Filament "Mapel Kelas": cocokkan nama kelas
     * (classroomTerm) atau nama mapel (subject), atau id eksak. Kembali array
     * [id => "Kelas - Mapel"].
     */
    public static function searchOptions(string $search): array
    {
        if (! filled($search)) {
            return [];
        }

        return static::query()
            ->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($search): void {
                $q->whereHas('classroomTerm', fn ($qq) => $qq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('subject', fn ($qq) => $qq->where('name', 'like', "%{$search}%"))
                    ->orWhere('id', $search);
            })
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (self $r) => [$r->id => "{$r->classroomTerm?->name} - {$r->subject?->name}"])
            ->all();
    }
}
