<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RppSyncConflict extends Model
{
    protected $fillable = ['source', 'source_type', 'source_id', 'reason', 'details', 'resolved_at'];
    protected function casts(): array { return ['details' => 'array', 'resolved_at' => 'datetime']; }
}
