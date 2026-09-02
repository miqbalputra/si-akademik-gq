<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rpp_id', 'pengetahuan', 'keterampilan', 'sikap'])]
class RppAssessment extends Model
{
    public function rpp(): BelongsTo { return $this->belongsTo(Rpp::class); }
}
