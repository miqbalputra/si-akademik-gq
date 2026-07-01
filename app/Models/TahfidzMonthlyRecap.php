<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TahfidzMonthlyRecap extends Model
{
    protected $fillable = [
        'tahfidz_halaqah_id', 'tahfidz_halaqah_member_id', 'student_id',
        'academic_term_id', 'month_number', 'month_label',
        'sabaq_monthly', 'sabaq_monthly_baris', 'average_score',
        'total_hafalan', 'manzil_submitted', 'manzil_score', 'notes',
    ];

    protected $casts = [
        'average_score' => 'decimal:2',
        'manzil_score' => 'decimal:2',
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