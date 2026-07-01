<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TahfidzSemesterRecap extends Model
{
    protected $fillable = [
        'tahfidz_halaqah_id', 'tahfidz_halaqah_member_id', 'student_id',
        'academic_term_id', 'sabaq_semester_score', 'sabaq_category',
        'manzil_average_score', 'manzil_category', 'sabqi_score',
        'semester_notes', 'status', 'validated_by', 'validated_at', 'locked_at',
    ];

    protected $casts = [
        'sabaq_semester_score' => 'decimal:2',
        'manzil_average_score' => 'decimal:2',
        'sabqi_score' => 'decimal:2',
        'validated_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function halaqah(): BelongsTo
    {
        return $this->belongsTo(TahfidzHalaqah::class, "tahfidz_halaqah_id");
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(TahfidzHalaqahMember::class, "tahfidz_halaqah_member_id");
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }
}