<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rpp_id', 'kind', 'disk', 'path', 'nama_file', 'mime_type', 'ukuran_byte', 'checksum'])]
class RppFile extends Model
{
    public function rpp(): BelongsTo { return $this->belongsTo(Rpp::class); }
}
