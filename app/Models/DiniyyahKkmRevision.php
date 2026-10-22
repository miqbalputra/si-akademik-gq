<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiniyyahKkmRevision extends Model
{
    protected $guarded = [];

    protected $attributes = ['status' => 'draft', 'lock_version' => 0];

    protected function casts(): array
    {
        return ['criteria' => 'array', 'average' => 'float', 'final_kkm' => 'integer', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'lock_version' => 'integer'];
    }

    public function scope(): BelongsTo
    {
        return $this->belongsTo(DiniyyahKkmScope::class, 'diniyyah_kkm_scope_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return ['draft' => 'Draf', 'submitted' => 'Menunggu ACC', 'needs_revision' => 'Perlu Revisi', 'approved' => 'Disetujui', 'superseded' => 'Versi Sebelumnya', 'school_fixed' => 'Ditetapkan Sekolah', 'cancelled_by_policy' => 'Digantikan Ketetapan Sekolah'][$this->status] ?? $this->status;
    }

    public function getIsSchoolFixedAttribute(): bool
    {
        return $this->status === 'school_fixed' || ($this->criteria['school_policy'] ?? null) === 'fixed_70_v1';
    }
}
