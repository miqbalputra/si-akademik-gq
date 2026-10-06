<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'student_id',
    'type',
    'effective_date',
    'reason',
    'destination_school',
    'exited_by',
    'restored_at',
    'restored_by',
])]
class StudentDeparture extends Model
{
    public const TYPE_TRANSFER = 'transfer';

    public const TYPE_LEFT = 'left';

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'restored_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function exitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exited_by')->withTrashed();
    }

    public function restoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by')->withTrashed();
    }
}
