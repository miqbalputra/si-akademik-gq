<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['report_card_id', 'line_type', 'source_type', 'source_id', 'subject_name', 'subject_arabic_name', 'tested_material', 'kkm', 'score_numeric', 'score_letter', 'score_words', 'is_passed', 'sort_order'])]
class ReportCardLine extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kkm' => 'decimal:2',
            'score_numeric' => 'decimal:2',
            'is_passed' => 'boolean',
        ];
    }

    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }
}
