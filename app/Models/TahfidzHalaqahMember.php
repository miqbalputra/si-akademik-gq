<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TahfidzHalaqahMember extends Model
{
    protected $fillable = [
        'tahfidz_halaqah_id', 'student_id', 'class_enrollment_id',
        'joined_at', 'left_at', 'status', 'sort_order',
    ];

    protected $casts = [
        'joined_at' => 'date',
        'left_at' => 'date',
    ];

    public function halaqah(): BelongsTo
    {
        return $this->belongsTo(TahfidzHalaqah::class, 'tahfidz_halaqah_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classEnrollment(): BelongsTo
    {
        return $this->belongsTo(ClassEnrollment::class);
    }

    public function weeklyScores(): HasMany
    {
        return $this->hasMany(TahfidzWeeklyScore::class);
    }

    public function monthlyRecaps(): HasMany
    {
        return $this->hasMany(TahfidzMonthlyRecap::class);
    }

    public function semesterRecap(): HasOne
    {
        return $this->hasOne(TahfidzSemesterRecap::class);
    }
}