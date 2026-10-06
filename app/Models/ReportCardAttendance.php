<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['report_card_id', 'sick_count', 'permission_count', 'absent_count'])]
class ReportCardAttendance extends Model
{
    protected static function booted(): void
    {
        static::saving(function (ReportCardAttendance $attendance): void {
            if ($attendance->reportCard()->where('status', '!=', 'draft')->exists()) {
                throw new DomainException('Rekap kehadiran rapor terkunci. Buka revisi rapor terlebih dahulu.');
            }
        });

        static::deleting(function (ReportCardAttendance $attendance): void {
            if ($attendance->reportCard()->where('status', '!=', 'draft')->exists()) {
                throw new DomainException('Rekap kehadiran rapor terkunci. Buka revisi rapor terlebih dahulu.');
            }
        });
    }

    use HasFactory;

    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }
}
