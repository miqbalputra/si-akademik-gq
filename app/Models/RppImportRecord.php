<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['source', 'source_type', 'source_id', 'status', 'rpp_id', 'details'])]
class RppImportRecord extends Model
{
    protected function casts(): array { return ['details' => 'array']; }
    public function rpp(): BelongsTo { return $this->belongsTo(Rpp::class); }
}
