<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardMetricSnapshot extends Model
{
    protected $fillable = [
        'academic_term_id',
        'classroom_term_id',
        'subject_id',
        'audience_role',
        'metric_key',
        'metric_label',
        'metric_value_numeric',
        'metric_value_text',
        'metric_payload',
        'generated_at',
        'expires_at',
    ];

    protected $casts = [
        'metric_value_numeric' => 'decimal:2',
        'metric_payload' => 'array',
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function academicTerm()
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function classroomTerm()
    {
        return $this->belongsTo(ClassroomTerm::class);
    }

    public function subject()
    {
        return $this->belongsTo(DiniyyahSubject::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}