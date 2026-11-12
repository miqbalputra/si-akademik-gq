<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiniyyahKkmScope extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'academic_term_id' => 'integer',
            'subject_id' => 'integer',
            'mustawa' => 'integer',
            'assigned_teacher_id' => 'integer',
            'active_revision_id' => 'integer',
            'working_revision_id' => 'integer',
        ];
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(DiniyyahSubject::class, 'subject_id');
    }

    public function assignedTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'assigned_teacher_id');
    }

    public function activeRevision(): BelongsTo
    {
        return $this->belongsTo(DiniyyahKkmRevision::class, 'active_revision_id');
    }

    public function workingRevision(): BelongsTo
    {
        return $this->belongsTo(DiniyyahKkmRevision::class, 'working_revision_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DiniyyahKkmRevision::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->subject?->name.' · Mustawa '.$this->mustawa.' · '.$this->academicTerm?->academicYear?->name.' '.$this->academicTerm?->name;
    }
}
