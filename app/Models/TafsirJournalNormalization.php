<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['diniyyah_class_journal_id', 'group_key', 'original_session_hour', 'normalized_by', 'normalized_at', 'reverted_by', 'reverted_at'])]
class TafsirJournalNormalization extends Model
{
    protected function casts(): array
    {
        return ['normalized_at' => 'datetime', 'reverted_at' => 'datetime'];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(DiniyyahClassJournal::class, 'diniyyah_class_journal_id');
    }

    public function normalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'normalized_by');
    }

    public function reverter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }
}
