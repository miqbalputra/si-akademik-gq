<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['diniyyah_class_journal_id', 'class_enrollment_id', 'status', 'notes'])]
class DiniyyahClassJournalAbsence extends Model
{
    use HasFactory;

    public function journal(): BelongsTo
    {
        return $this->belongsTo(DiniyyahClassJournal::class, 'diniyyah_class_journal_id');
    }

    public function classEnrollment(): BelongsTo
    {
        return $this->belongsTo(ClassEnrollment::class);
    }
}
