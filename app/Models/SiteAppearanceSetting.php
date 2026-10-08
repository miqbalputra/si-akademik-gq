<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteAppearanceSetting extends Model
{
    protected $fillable = ['palette'];

    protected $casts = [
        'palette' => 'array',
    ];
}
