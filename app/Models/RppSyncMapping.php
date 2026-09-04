<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RppSyncMapping extends Model
{
    protected $fillable = ['mapping_type', 'source_id', 'target_id'];
}
