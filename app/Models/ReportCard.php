<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use DomainException;

#[Fillable(['academic_term_id', 'classroom_term_id', 'class_enrollment_id', 'student_id', 'report_type', 'status', 'issue_date', 'total_score', 'average_score', 'rank_in_class', 'homeroom_note', 'published_at', 'published_by', 'locked_at', 'locked_by'])]
class ReportCard extends Model
{
    use HasFactory;

    private bool $revisionTransitionAuthorized = false;

    protected static function booted(): void
    {
        static::creating(function (ReportCard $reportCard): void {
            if (($reportCard->status ?? 'draft') !== 'draft') {
                throw new DomainException('Rapor baru harus berstatus draf dan melewati alur penguncian sebelum diterbitkan.');
            }
        });

        static::updating(function (ReportCard $reportCard): void {
            $previousStatus = $reportCard->getOriginal('status');
            $dirty = array_diff(array_keys($reportCard->getDirty()), ['updated_at']);

            if ($previousStatus === 'draft' && in_array('status', $dirty, true) && $reportCard->status !== 'locked') {
                throw new DomainException('Rapor draf hanya dapat dikunci melalui alur validasi rapor.');
            }

            if ($previousStatus === 'published') {
                $isOpeningRevision = $reportCard->revisionTransitionAuthorized
                    && $reportCard->status === 'draft'
                    && ! empty(array_intersect($dirty, ['status']))
                    && empty(array_diff($dirty, ['status', 'published_at', 'published_by', 'locked_at', 'locked_by']));

                if (! $isOpeningRevision) {
                    throw new DomainException('Rapor terbit tidak dapat diubah. Buka revisi melalui alur revisi yang tercatat.');
                }
            }

            if ($previousStatus === 'locked') {
                $isPublishing = $reportCard->status === 'published'
                    && in_array('status', $dirty, true)
                    && empty(array_diff($dirty, ['status', 'published_at', 'published_by']));

                if (! $isPublishing) {
                    throw new DomainException('Rapor terkunci tidak dapat diubah sebelum diterbitkan.');
                }
            }
        });

        static::deleting(function (ReportCard $reportCard): void {
            if ($reportCard->status !== 'draft') {
                throw new DomainException('Hanya rapor draf yang dapat dihapus.');
            }
        });
    }

    public function authorizeRevisionTransition(): void
    {
        $this->revisionTransitionAuthorized = true;
    }

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'total_score' => 'decimal:2',
            'average_score' => 'decimal:2',
            'published_at' => 'datetime',
            'locked_at' => 'datetime',
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

    public function classEnrollment(): BelongsTo
    {
        return $this->belongsTo(ClassEnrollment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ReportCardLine::class);
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(ReportCardAttendance::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ReportCardSnapshot::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(ReportCardSignature::class);
    }

    public function revisionLogs(): HasMany
    {
        return $this->hasMany(ReportCardRevisionLog::class)->orderBy('revision_number')->orderBy('performed_at');
    }
}
