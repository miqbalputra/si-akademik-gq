<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['diniyyah_ledger_snapshot_id', 'class_enrollment_id', 'row_number', 'student_name', 'student_nis', 'total_diniyyah_score', 'average_diniyyah_score', 'rank_in_class'])]
class DiniyyahLedgerRow extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'total_diniyyah_score' => 'decimal:2',
            'average_diniyyah_score' => 'decimal:2',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(DiniyyahLedgerSnapshot::class, 'diniyyah_ledger_snapshot_id');
    }

    public function classEnrollment(): BelongsTo
    {
        return $this->belongsTo(ClassEnrollment::class);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(DiniyyahLedgerCell::class);
    }
}
