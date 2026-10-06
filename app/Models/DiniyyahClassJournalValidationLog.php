<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['diniyyah_class_journal_id', 'action', 'performed_by', 'reason', 'performed_at'])]
class DiniyyahClassJournalValidationLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['performed_at' => 'datetime'];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(DiniyyahClassJournal::class, 'diniyyah_class_journal_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
