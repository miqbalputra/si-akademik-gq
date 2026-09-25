<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

#[Fillable([
    'diniyyah_teacher_assignment_id',
    'class_session_id',
    'day_of_week',
    'effective_from',
    'effective_until',
    'version_status',
    'change_type',
    'change_reason',
    'request_reference',
])]
class DiniyyahTeachingSchedule extends Model
{
    use HasFactory;

    protected $attributes = [
        'version_status' => self::STATUS_LEGACY,
    ];

    public const STATUS_LEGACY = 'legacy';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUPERSEDED = 'superseded';

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    /** Jadwal yang masih berlaku atau data legacy yang belum ditinjau. */
    public function scopeVisibleVersions(Builder $query): Builder
    {
        return $query->whereIn('version_status', [self::STATUS_LEGACY, self::STATUS_ACTIVE]);
    }

    /** Jadwal yang mungkin berlaku pada salah satu tanggal dalam rentang. */
    public function scopeOverlappingRange(Builder $query, Carbon|string $start, Carbon|string $end): Builder
    {
        $from = Carbon::parse($start, 'Asia/Jakarta')->toDateString();
        $until = Carbon::parse($end, 'Asia/Jakarta')->toDateString();

        return $query->visibleVersions()->where(function (Builder $query) use ($from, $until): void {
            $query->where('version_status', self::STATUS_LEGACY)
                ->orWhere(function (Builder $query) use ($from, $until): void {
                    $query->where('version_status', self::STATUS_ACTIVE)
                        ->where(function (Builder $query) use ($until): void {
                            $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $until);
                        })
                        ->where(function (Builder $query) use ($from): void {
                            $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $from);
                        });
                });
        });
    }

    /** Jadwal yang berlaku pada tanggal tertentu; legacy tetap berperilaku lama sampai ditinjau. */
    public function scopeForDate(Builder $query, Carbon|string $date): Builder
    {
        $value = Carbon::parse($date, 'Asia/Jakarta')->toDateString();

        return $query->visibleVersions()->where(function (Builder $query) use ($value): void {
            $query->where('version_status', self::STATUS_LEGACY)
                ->orWhere(function (Builder $query) use ($value): void {
                    $query->where('version_status', self::STATUS_ACTIVE)
                        ->where(function (Builder $query) use ($value): void {
                            $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $value);
                        })
                        ->where(function (Builder $query) use ($value): void {
                            $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $value);
                        });
                });
        });
    }

    public function appliesOn(Carbon|string $date): bool
    {
        $date = Carbon::parse($date, 'Asia/Jakarta')->toDateString();
        if ($this->version_status === self::STATUS_LEGACY) {
            return true;
        }
        if ($this->version_status !== self::STATUS_ACTIVE) {
            return false;
        }

        return (! $this->effective_from || $this->effective_from->toDateString() <= $date)
            && (! $this->effective_until || $this->effective_until->toDateString() >= $date);
    }

    public function teacherAssignment(): BelongsTo
    {
        return $this->belongsTo(DiniyyahTeacherAssignment::class, 'diniyyah_teacher_assignment_id');
    }

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function scheduleChangeLogs(): HasMany
    {
        return $this->hasMany(DiniyyahScheduleChangeLog::class, 'diniyyah_teaching_schedule_id');
    }

}
