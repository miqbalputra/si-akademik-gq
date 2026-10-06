<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['report_card_id', 'revision_number', 'action', 'reason', 'before_data', 'after_data', 'performed_by', 'performed_at'])]
class ReportCardRevisionLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'before_data' => 'array',
            'after_data' => 'array',
            'performed_at' => 'datetime',
        ];
    }

    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
