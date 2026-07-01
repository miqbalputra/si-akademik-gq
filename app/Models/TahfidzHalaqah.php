<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TahfidzHalaqah extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'academic_term_id', 'name', 'teacher_id', 'assistant_teacher_id',
        'status', 'notes', 'created_by',
    ];

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function assistantTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'assistant_teacher_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TahfidzHalaqahMember::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->hasMany(TahfidzHalaqahMember::class)->where('status', 'active');
    }

    public function weeklyScores(): HasMany
    {
        return $this->hasMany(TahfidzWeeklyScore::class);
    }

    public function validations(): HasMany
    {
        return $this->hasMany(TahfidzValidation::class);
    }
}