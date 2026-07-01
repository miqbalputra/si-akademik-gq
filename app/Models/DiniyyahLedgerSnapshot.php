<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'academic_term_id',
    'classroom_term_id',
    'title',
    'status',
    'generated_at',
    'generated_by',
    'validated_at',
    'validated_by',
    'locked_at',
    'locked_by',
    'published_at',
    'published_by',
    'snapshot_data',
])]
class DiniyyahLedgerSnapshot extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'validated_at' => 'datetime',
            'locked_at' => 'datetime',
            'published_at' => 'datetime',
            'snapshot_data' => 'array',
        ];
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function classroomTerm(): BelongsTo
    {
        return $this->belongsTo(ClassroomTerm::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(DiniyyahLedgerRow::class);
    }
}
