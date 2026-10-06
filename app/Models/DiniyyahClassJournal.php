<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use DomainException;

#[Fillable(['diniyyah_teacher_assignment_id', 'substitute_teacher_id', 'date', 'session_hour', 'session_starts_at', 'session_ends_at', 'material', 'jp_count', 'status', 'validated_by', 'validated_at', 'validation_revoked_by', 'validation_revoked_at', 'validation_revocation_reason'])]
class DiniyyahClassJournal extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'jp_count' => 'integer',
            'validated_at' => 'datetime',
            'validation_revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DiniyyahClassJournal $journal): void {
            if (($journal->status ?? 'draft') !== 'draft') {
                throw new DomainException('Jurnal baru harus dibuat sebagai draf dan divalidasi melalui alur admin.');
            }
        });

        static::updating(function (DiniyyahClassJournal $journal): void {
            $persistedStatus = static::query()->whereKey($journal->getKey())->value('status');
            if ($persistedStatus !== 'validated') {
                return;
            }

            $journal->guardValidatedChanges();
        });

        static::deleting(function (DiniyyahClassJournal $journal): void {
            if (static::query()->whereKey($journal->getKey())->where('status', 'validated')->exists()) {
                throw new DomainException('Jurnal tervalidasi harus dibatalkan validasinya oleh admin sebelum dihapus.');
            }
        });
    }

    public function teacherAssignment(): BelongsTo
    {
        return $this->belongsTo(DiniyyahTeacherAssignment::class, 'diniyyah_teacher_assignment_id');
    }

    /**
     * Guru pengganti yang benar-benar mengajar (nullable). Jika null, berarti
     * jurnal diisi oleh guru asli pemilik assignment.
     */
    public function substituteTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'substitute_teacher_id');
    }

    public function absences(): HasMany
    {
        return $this->hasMany(DiniyyahClassJournalAbsence::class);
    }

    public function validationLogs(): HasMany
    {
        return $this->hasMany(DiniyyahClassJournalValidationLog::class)
            ->latest('performed_at');
    }

    /**
     * Guru yang JP-nya dihitung untuk penggajian: pengganti jika ada, jika tidak
     * maka guru pemilik assignment (guru asli).
     */
    public function effectiveTeacher(): ?Teacher
    {
        return $this->substituteTeacher ?? $this->teacherAssignment?->teacher;
    }

    private function guardValidatedChanges(): void
    {
        $businessFields = [
            'diniyyah_teacher_assignment_id', 'substitute_teacher_id', 'date',
            'session_hour', 'session_starts_at', 'session_ends_at', 'material', 'jp_count',
        ];

        if ($this->isDirty($businessFields)) {
            throw new DomainException('Jurnal tervalidasi tidak dapat diubah. Admin harus membatalkan validasi terlebih dahulu.');
        }

        if ($this->isDirty('status') && (
            $this->status !== 'draft'
            || ! $this->validation_revoked_by
            || ! $this->validation_revoked_at
            || blank($this->validation_revocation_reason)
        )) {
            throw new DomainException('Pembatalan validasi harus dilakukan melalui alur admin dan mencantumkan alasan.');
        }
    }
}
