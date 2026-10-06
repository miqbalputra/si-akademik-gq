<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['diniyyah_class_journal_id', 'class_enrollment_id', 'status', 'notes'])]
class DiniyyahClassJournalAbsence extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (DiniyyahClassJournalAbsence $absence): void {
            if ($absence->journal()->where('status', 'validated')->exists()) {
                throw new DomainException('Presensi pada jurnal tervalidasi tidak dapat diubah sebelum validasi dibatalkan.');
            }
        });

        static::deleting(function (DiniyyahClassJournalAbsence $absence): void {
            if ($absence->journal()->where('status', 'validated')->exists()) {
                throw new DomainException('Presensi pada jurnal tervalidasi tidak dapat dihapus sebelum validasi dibatalkan.');
            }
        });
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(DiniyyahClassJournal::class, 'diniyyah_class_journal_id');
    }

    public function classEnrollment(): BelongsTo
    {
        return $this->belongsTo(ClassEnrollment::class);
    }
}
