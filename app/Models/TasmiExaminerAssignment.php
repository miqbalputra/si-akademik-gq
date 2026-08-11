<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TasmiExaminerAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_term_id',
        'teacher_id',
        'status',
        'assigned_by',
        'notes',
    ];

    protected $casts = [];

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function tasmiRecords(): HasMany
    {
        return $this->hasMany(TasmiRecord::class);
    }
}