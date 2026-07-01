<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanelSavedFilter extends Model
{
    protected $fillable = [
        'user_id',
        'panel_key',
        'page_key',
        'name',
        'filters',
        'is_default',
    ];

    protected $casts = [
        'filters' => 'array',
        'is_default' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}