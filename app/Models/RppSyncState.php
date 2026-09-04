<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RppSyncState extends Model
{
    protected $fillable = ['source', 'cursor', 'last_synced_at', 'last_error'];
    protected function casts(): array { return ['last_synced_at' => 'datetime']; }
}
