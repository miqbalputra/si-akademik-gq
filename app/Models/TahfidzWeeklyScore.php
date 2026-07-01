<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TahfidzWeeklyScore extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tahfidz_halaqah_id', 'tahfidz_halaqah_member_id', 'tahfidz_week_id',
        'student_id', 'surah_ayat', 'sabaq_amount', 'sabaq_baris',
        'score', 'category', 'notes', 'input_by', 'input_at', 'status',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'input_at' => 'datetime',
    ];

    public function halaqah(): BelongsTo
    {
        return $this->belongsTo(TahfidzHalaqah::class, "tahfidz_halaqah_id");
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(TahfidzHalaqahMember::class, "tahfidz_halaqah_member_id");
    }

    public function week(): BelongsTo
    {
        return $this->belongsTo(TahfidzWeek::class, "tahfidz_week_id");
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}