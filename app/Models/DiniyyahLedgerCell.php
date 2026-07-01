<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['diniyyah_ledger_row_id', 'column_key', 'label', 'source_type', 'source_id', 'value_numeric', 'value_text', 'sort_order'])]
class DiniyyahLedgerCell extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['value_numeric' => 'decimal:2'];
    }

    public function row(): BelongsTo
    {
        return $this->belongsTo(DiniyyahLedgerRow::class, 'diniyyah_ledger_row_id');
    }
}
