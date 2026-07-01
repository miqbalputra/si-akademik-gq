<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'school_event_id',
    'guardian_id',
    'attendance_status',
    'notes',
    'responded_at',
])]
class SchoolEventResponse extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    public function schoolEvent(): BelongsTo
    {
        return $this->belongsTo(SchoolEvent::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function statusLabel(): string
    {
        return match ($this->attendance_status) {
            'attending' => 'Hadir',
            'permission' => 'Izin',
            'not_attending' => 'Tidak Hadir',
            default => 'Belum Konfirmasi',
        };
    }
}
