<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanelUserPreference extends Model
{
    protected $fillable = [
        'user_id',
        'panel_key',
        'preferences',
        'dashboard_layout',
    ];

    protected $casts = [
        'preferences' => 'array',
        'dashboard_layout' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}