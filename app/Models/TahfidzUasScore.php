<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TahfidzUasScore extends Model
{
    protected $fillable = [
        'academic_term_id', 'tahfidz_uas_day_id', 'tahfidz_uas_category_id',
        'student_id', 'tahfidz_halaqah_id', 'score', 'input_by', 'notes',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    public function day(): BelongsTo
    {
        return $this->belongsTo(TahfidzUasDay::class, "tahfidz_uas_day_id");
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TahfidzUasCategory::class, "tahfidz_uas_category_id");
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function halaqah(): BelongsTo
    {
        return $this->belongsTo(TahfidzHalaqah::class, "tahfidz_halaqah_id");
    }
}