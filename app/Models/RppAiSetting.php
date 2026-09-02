<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['enabled', 'endpoint', 'api_key', 'model', 'updated_by'])]
class RppAiSetting extends Model
{
    protected function casts(): array { return ['enabled' => 'boolean', 'api_key' => 'encrypted']; }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
