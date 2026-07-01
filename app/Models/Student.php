<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'gender', 'nis', 'nik', 'status'])]
class Student extends Model
{
    use HasFactory, SoftDeletes;

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians')
            ->withPivot(['relationship', 'is_primary', 'can_login'])
            ->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class);
    }

    public function tahfidzHalaqahMembers(): HasMany
    {
        return $this->hasMany(TahfidzHalaqahMember::class);
    }

    public function tahfidzWeeklyScores(): HasMany
    {
        return $this->hasMany(TahfidzWeeklyScore::class);
    }

    public function tahfidzMonthlyRecaps(): HasMany
    {
        return $this->hasMany(TahfidzMonthlyRecap::class);
    }

    public function tahfidzUasScores(): HasMany
    {
        return $this->hasMany(TahfidzUasScore::class);
    }

    public function tahfidzUasResult(): HasOne
    {
        return $this->hasOne(TahfidzUasResult::class);
    }
}
