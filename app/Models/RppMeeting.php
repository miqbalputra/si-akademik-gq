<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rpp_id', 'urutan', 'isi_kegiatan', 'tanggal_kbm'])]
class RppMeeting extends Model
{
    protected function casts(): array { return ['tanggal_kbm' => 'date']; }
    public function rpp(): BelongsTo { return $this->belongsTo(Rpp::class); }
}
