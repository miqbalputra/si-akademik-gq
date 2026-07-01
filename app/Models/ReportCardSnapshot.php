<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['report_card_id', 'layout_version', 'snapshot_data', 'pdf_path', 'generated_at', 'generated_by'])]
class ReportCardSnapshot extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'snapshot_data' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }
}
