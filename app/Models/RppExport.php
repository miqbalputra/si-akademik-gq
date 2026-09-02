<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rpp_id', 'type', 'disk', 'path', 'mime_type', 'content_hash', 'ukuran_byte'])]
class RppExport extends Model
{
    public function rpp(): BelongsTo { return $this->belongsTo(Rpp::class); }
}
