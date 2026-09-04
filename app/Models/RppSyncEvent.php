<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RppSyncEvent extends Model
{
    protected $fillable = ['event_id', 'event_type', 'entity_id', 'occurred_at', 'payload_hash', 'status', 'error', 'processed_at'];
    protected function casts(): array { return ['occurred_at' => 'datetime', 'processed_at' => 'datetime']; }
}
